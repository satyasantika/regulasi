<?php

use App\Actions\Relasi\TambahRelasiDokumen;
use App\Enums\JenisRelasi;
use App\Enums\JenisTautan;
use App\Enums\PenyediaTautan;
use App\Enums\Peran;
use App\Enums\StatusBerlaku;
use App\Enums\StatusCekTautan;
use App\Enums\StatusEkstraksi;
use App\Enums\StatusPublikasi;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Dokumen;
use App\Models\DokumenRelasi;
use App\Models\EkstraksiTeks;
use App\Models\RiwayatStatusDokumen;
use App\Models\TautanBerkas;
use App\Notifications\RingkasanMingguan;
use Illuminate\Console\Scheduling\Event as TugasTerjadwal;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function tautanJadwal(Dokumen $dokumen, string $host = 'drive.google.com', JenisTautan $jenis = JenisTautan::NaskahUtama, StatusCekTautan $status = StatusCekTautan::Belum): TautanBerkas
{
    $id = '1Jadwal'.fake()->unique()->numberBetween(100000, 999999).'AbCdEf';
    $tautan = TautanBerkas::query()->create([
        'pemilik_type' => 'dokumen',
        'pemilik_id' => $dokumen->getKey(),
        'jenis' => $jenis,
        'url' => $host === 'drive.google.com' ? "https://drive.google.com/file/d/{$id}/view" : "https://{$host}/Details/{$id}",
        'penyedia' => $host === 'drive.google.com' ? PenyediaTautan::GoogleDrive : PenyediaTautan::SumberResmi,
        'drive_file_id' => $host === 'drive.google.com' ? $id : null,
    ]);
    $tautan->forceFill(['status_cek' => $status])->save();

    return $tautan;
}

test('jadwal memuat semua tugas dengan cron, zona Asia/Jakarta, tanpa tumpang tindih, dan satu server', function () {
    $tugas = collect(app(Schedule::class)->events());

    $harapan = [
        'regulasi:terapkan-status' => '15 0 * * *',
        'berkas:bersihkan-tmp' => '45 0 * * *',
        'regulasi:ekstraksi-ulang --gagal' => '0 * * * *',
        'model:prune' => '0 1 * * *',
        'activitylog:clean' => '30 1 * * *',
        'berkas:periksa-tautan' => '30 6 * * 1',
        'regulasi:ringkasan-mingguan' => '30 7 * * 1',
    ];

    // 7 tugas bisnis + 2 worker antrean + detak penjadwal + 2 pemangkasan (Profil Ringan)
    expect($tugas)->toHaveCount(count($harapan) + 5);

    foreach ($harapan as $perintah => $cron) {
        $event = $tugas->first(fn (TugasTerjadwal $e): bool => str_contains((string) $e->command, $perintah));

        expect($event)->not->toBeNull("Tugas {$perintah} tidak terjadwal")
            ->and($event->expression)->toBe($cron)
            ->and($event->timezone)->toBe('Asia/Jakarta')
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->onOneServer)->toBeTrue();
    }

    expect((string) $tugas->first(fn (TugasTerjadwal $e): bool => str_contains((string) $e->command, 'model:prune'))->command)
        ->toContain('LogBukaTautan');
});

test('Profil Ringan: dua worker, detak penjadwal, pemangkasan antrean, dan tanpa horizon:snapshot', function () {
    $jadwal = collect(app(Schedule::class)->events())
        ->map(fn ($e) => (string) ($e->command ?? $e->description))
        ->implode("\n");

    expect($jadwal)
        ->toContain('queue:work database --queue=default --stop-when-empty')
        ->toContain('queue:work database-berat --queue=berat --stop-when-empty')
        ->toContain('detak-penjadwal')
        ->toContain('queue:prune-failed')
        ->toContain('queue:prune-batches')
        ->not->toContain('horizon');
});

test('berkas:periksa-tautan mengantrekan satu job per tautan dokumen terbit dan menjeda per host', function () {
    Queue::fake();
    config(['regulasi.unduh_per_menit_per_host' => 2]);

    $terbit = Dokumen::factory()->terbit()->create();
    $driveA = tautanJadwal($terbit);
    tautanJadwal($terbit, jenis: JenisTautan::Lampiran);
    tautanJadwal($terbit, jenis: JenisTautan::Lampiran, status: StatusCekTautan::TidakDapatDiakses);
    tautanJadwal($terbit, 'peraturan.bpk.go.id', JenisTautan::HalamanSumber, StatusCekTautan::DapatDiakses);
    tautanJadwal(Dokumen::factory()->create());
    $dihapus = Dokumen::factory()->terbit()->create();
    tautanJadwal($dihapus);
    $dihapus->delete();

    $this->artisan('berkas:periksa-tautan')
        ->expectsOutputToContain('4 tautan dari 2 host diantrekan')
        ->assertSuccessful();

    Queue::assertPushedOn('default', PeriksaTautanBerkas::class);
    Queue::assertPushed(PeriksaTautanBerkas::class, 4);
    Queue::assertPushed(PeriksaTautanBerkas::class, fn (PeriksaTautanBerkas $job): bool => $job->tautan->is($driveA) && $job->delay === null);
    // Tautan Drive ke-3 melewati batas 2/menit → ditunda satu menit.
    Queue::assertPushed(PeriksaTautanBerkas::class, fn (PeriksaTautanBerkas $job): bool => $job->delay !== null);
});

test('berkas:periksa-tautan --dokumen membatasi ke dokumen tertentu', function () {
    Queue::fake();
    $a = Dokumen::factory()->terbit()->create();
    $b = Dokumen::factory()->terbit()->create();
    $tautanA = tautanJadwal($a);
    tautanJadwal($b);

    $this->artisan('berkas:periksa-tautan', ['--dokumen' => [$a->getKey()]])->assertSuccessful();

    Queue::assertPushed(PeriksaTautanBerkas::class, 1);
    Queue::assertPushed(PeriksaTautanBerkas::class, fn (PeriksaTautanBerkas $job): bool => $job->tautan->is($tautanA));
});

test('berkas:bersihkan-tmp menghapus berkas tmp lebih dari 24 jam dan menyisakan yang baru', function () {
    $folder = storage_path('app/tmp/uji-jadwal-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($folder);
    $lama = "{$folder}/lama.pdf";
    $baru = "{$folder}/baru.pdf";
    File::put($lama, 'lama');
    File::put($baru, 'baru');
    File::put("{$folder}/.tersembunyi", 'x');
    touch("{$folder}/.tersembunyi", now()->subDays(3)->getTimestamp());
    touch($lama, now()->subHours(25)->getTimestamp());

    try {
        $this->artisan('berkas:bersihkan-tmp')->assertSuccessful();

        expect(File::exists($lama))->toBeFalse()
            ->and(File::exists($baru))->toBeTrue()
            ->and(File::exists("{$folder}/.tersembunyi"))->toBeTrue();
    } finally {
        File::deleteDirectory($folder);
    }
});

test('berkas:bersihkan-tmp melewati berkas yang sudah dihapus proses lain di tengah pemindaian', function () {
    $folder = storage_path('app/tmp/uji-jadwal-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($folder);
    $hilang = new SplFileInfo("{$folder}/sudah-dihapus.xlsx");

    try {
        File::partialMock()->shouldReceive('allFiles')->andReturn([$hilang]);

        $this->artisan('berkas:bersihkan-tmp')
            ->expectsOutput('0 berkas sementara dihapus.')
            ->assertSuccessful();
    } finally {
        File::deleteDirectory($folder);
    }
});

test('ringkasan mingguan memuat angka yang benar dan hanya dikirim lewat surel ke admin aktif', function () {
    Notification::fake();
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $adminNonaktif = penggunaDenganPeran(Peran::AdminRegulasi, ['aktif' => false]);
    $kontributor = penggunaDenganPeran(Peran::Kontributor);

    // Terbit 7 hari terakhir: baru1, baru2, sumberDekat, sumberJauh. Tanpa abstrak: baru2, lama, rujukan.
    $baru1 = Dokumen::factory()->terbit()->create(['abstrak' => 'Ringkasan isi.', 'sebutan_singkat' => 'Perdek 1/2026']);
    $naskahBaru1 = tautanJadwal($baru1);
    EkstraksiTeks::query()->create(['tautan_berkas_id' => $naskahBaru1->getKey()])
        ->forceFill(['status' => StatusEkstraksi::Berhasil, 'isi_berubah_pada' => now()->subDays(2)])->save();

    $baru2 = Dokumen::factory()->terbit()->create(['abstrak' => null]);
    $naskahBaru2 = tautanJadwal($baru2, status: StatusCekTautan::TidakDapatDiakses);
    EkstraksiTeks::query()->create(['tautan_berkas_id' => $naskahBaru2->getKey()])->forceFill(['status' => StatusEkstraksi::TanpaTeks])->save();

    $lama = Dokumen::factory()->terbit()->create(['abstrak' => '', 'dipublikasikan_pada' => now()->subDays(30)]);
    $lampiranLama = tautanJadwal($lama, jenis: JenisTautan::Lampiran);
    EkstraksiTeks::query()->create(['tautan_berkas_id' => $lampiranLama->getKey()])->forceFill(['status' => StatusEkstraksi::Gagal])->save();

    Dokumen::factory()->rujukan()->create(['abstrak' => null, 'dipublikasikan_pada' => now()->subDays(40)]);

    // Antrean verifikasi: 2 dokumen, yang tertua diajukan 5 hari lalu.
    $menunggu1 = Dokumen::factory()->berstatus(StatusPublikasi::MenungguVerifikasi)->create();
    $menunggu2 = Dokumen::factory()->berstatus(StatusPublikasi::MenungguVerifikasi)->create();
    foreach ([[$menunggu1, 5], [$menunggu2, 1]] as [$dokumen, $hari]) {
        RiwayatStatusDokumen::query()->create([
            'dokumen_id' => $dokumen->getKey(), 'jenis' => 'publikasi', 'dari' => 'draf', 'ke' => 'menunggu_verifikasi',
        ])->forceFill(['created_at' => now()->subDays($hari)])->saveQuietly();
    }

    // Relasi tertunda: sumber berlaku 10 hari lagi (masuk), 60 hari lagi (tidak masuk).
    $sumberDekat = Dokumen::factory()->terbit()->create(['tanggal_berlaku' => today()->addDays(10), 'sebutan_singkat' => 'Perdek 9/2026', 'abstrak' => 'x']);
    $sumberJauh = Dokumen::factory()->terbit()->create(['tanggal_berlaku' => today()->addDays(60), 'abstrak' => 'x']);
    $target = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2020-01-01', 'sebutan_singkat' => 'Perdek 2/2020', 'abstrak' => 'x', 'dipublikasikan_pada' => now()->subYear()]);
    app(TambahRelasiDokumen::class)->handle($sumberDekat, $target, JenisRelasi::Mencabut, null, $admin);
    app(TambahRelasiDokumen::class)->handle($sumberJauh, $target, JenisRelasi::Mengubah, null, $admin);

    $this->artisan('regulasi:ringkasan-mingguan')
        ->expectsOutputToContain('Antrean verifikasi: 2')
        ->expectsOutputToContain('Ringkasan dikirim ke 1 penerima')
        ->assertSuccessful();

    Notification::assertSentTo($admin, RingkasanMingguan::class, function (RingkasanMingguan $n, array $kanal) use ($admin): bool {
        $r = $n->ringkasan;

        expect($kanal)->toBe(['mail'])
            ->and($r['terbit_baru']['jumlah'])->toBe(4)
            ->and($r['terbit_baru']['daftar'])->toContain('Perdek 1/2026', 'Perdek 9/2026')
            ->and($r['antrean']['jumlah'])->toBe(2)
            ->and($r['antrean']['tertua'])->toStartWith(now()->subDays(5)->toDateString())
            ->and($r['kelengkapan'])->toBe(['tanpa_abstrak' => 3, 'tanpa_naskah_utama' => 4, 'tanpa_teks' => 1])
            ->and($r['tautan_tidak_dapat_diakses'])->toBe(1)
            ->and($r['ekstraksi'])->toBe(['gagal' => 1, 'tidak_dapat_diunduh' => 0])
            ->and($r['isi_berubah'])->toBe(1)
            ->and($r['relasi_tertunda']['jumlah'])->toBe(1)
            ->and($r['relasi_tertunda']['daftar'][0])->toStartWith('Perdek 9/2026 mencabut Perdek 2/2020')
            ->and(implode("\n", $n->toMail($admin)->introLines))->toContain('Antrean verifikasi: 2, tertua diajukan');

        return true;
    });
    Notification::assertNotSentTo([$adminNonaktif, $kontributor], RingkasanMingguan::class);
});

test('regulasi:terapkan-status idempoten bila dipanggil dua kali', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $sumber = Dokumen::factory()->terbit()->create(['tanggal_berlaku' => today()->addDays(2)]);
    $target = Dokumen::factory()->terbit()->create(['tanggal_penetapan' => '2020-01-01']);
    app(TambahRelasiDokumen::class)->handle($sumber, $target, JenisRelasi::Mencabut, null, $admin);
    $this->travel(3)->days();

    $this->artisan('regulasi:terapkan-status')->expectsOutputToContain('1 relasi diterapkan')->assertSuccessful();
    $this->artisan('regulasi:terapkan-status')->expectsOutputToContain('0 relasi diterapkan')->assertSuccessful();

    expect($target->refresh()->status_berlaku)->toBe(StatusBerlaku::Dicabut)
        ->and(RiwayatStatusDokumen::query()->where('dokumen_id', $target->getKey())->count())->toBe(1)
        ->and(DokumenRelasi::query()->where('efektif', true)->count())->toBe(2);
});
