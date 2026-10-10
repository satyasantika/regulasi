<?php

use App\Enums\KelompokJenisDokumen;
use App\Enums\TingkatBadanPenetap;
use App\Models\Aktivitas;
use App\Models\BadanPenetap;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('kelima tabel master tersedia dengan kolom sesuai skema', function () {
    expect(Schema::getColumnListing('jenis_dokumen'))->toEqualCanonicalizing([
        'id', 'kode', 'nama', 'singkatan', 'slug', 'kelompok', 'urutan', 'wajib_nomor', 'deskripsi', 'aktif', 'created_at', 'updated_at', 'deleted_at',
    ])
        ->and(Schema::getColumnListing('badan_penetap'))->toEqualCanonicalizing([
            'id', 'nama', 'teu', 'singkatan', 'slug', 'tingkat', 'induk_id', 'kode_eksternal', 'urutan', 'aktif', 'created_at', 'updated_at', 'deleted_at',
        ])
        ->and(Schema::getColumnListing('kategori_sistem'))->toEqualCanonicalizing([
            'id', 'kode', 'nama', 'slug', 'deskripsi', 'ikon', 'warna', 'urutan', 'aktif', 'created_at', 'updated_at', 'deleted_at',
        ])
        ->and(Schema::getColumnListing('kategori_sistem_user'))->toEqualCanonicalizing(['kategori_sistem_id', 'user_id', 'created_at'])
        ->and(Schema::getColumnListing('tag'))->toEqualCanonicalizing(['id', 'nama', 'slug', 'created_at', 'updated_at']);
});

test('slug dibuat otomatis dari nama dan dijamin unik', function () {
    $pertama = JenisDokumen::factory()->create(['nama' => 'Peraturan Menteri']);
    $kedua = JenisDokumen::factory()->create(['nama' => 'Peraturan Menteri']);
    $tag = Tag::factory()->create(['nama' => 'Barang Milik Negara']);

    expect($pertama->slug)->toBe('peraturan-menteri')
        ->and($kedua->slug)->toBe('peraturan-menteri-2')
        ->and($tag->slug)->toBe('barang-milik-negara')
        ->and($pertama->getRouteKeyName())->toBe('slug')
        ->and(Str::isUuid($pertama->id))->toBeTrue();
});

test('slug baris yang dihapus lunak tetap dianggap terpakai', function () {
    KategoriSistem::factory()->create(['nama' => 'Aset/BMN'])->delete();

    expect(KategoriSistem::factory()->create(['nama' => 'Aset/BMN'])->slug)->toBe('aset-bmn-2');
});

test('slug yang diisi manual tidak ditimpa dan duplikat ditolak basis data', function () {
    BadanPenetap::factory()->create(['nama' => 'Universitas Siliwangi', 'slug' => 'unsil']);

    expect(BadanPenetap::where('slug', 'unsil')->exists())->toBeTrue();
    expect(fn () => BadanPenetap::factory()->create(['slug' => 'unsil']))->toThrow(UniqueConstraintViolationException::class);
});

test('scope terurut mengurutkan berdasarkan urutan lalu nama dan scope aktif menyaring', function () {
    JenisDokumen::factory()->create(['nama' => 'Zeta', 'urutan' => 10]);
    JenisDokumen::factory()->create(['nama' => 'Alfa', 'urutan' => 20]);
    JenisDokumen::factory()->create(['nama' => 'Beta', 'urutan' => 10]);
    JenisDokumen::factory()->nonaktif()->create(['nama' => 'Gama', 'urutan' => 1]);

    expect(JenisDokumen::query()->terurut()->pluck('nama')->all())->toBe(['Gama', 'Beta', 'Zeta', 'Alfa'])
        ->and(JenisDokumen::query()->aktif()->terurut()->pluck('nama')->all())->toBe(['Beta', 'Zeta', 'Alfa']);
});

test('penugasan pengguna ke kategori sistem dua arah', function () {
    $aset = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset/BMN']);
    $sdm = KategoriSistem::factory()->create(['kode' => 'sdm', 'nama' => 'SDM']);
    $user = User::factory()->create();

    $user->kategoriSistem()->attach([$aset->id, $sdm->id]);

    expect($user->kategoriSistem()->pluck('kode')->sort()->values()->all())->toBe(['aset', 'sdm'])
        ->and($aset->pengguna()->pluck('users.id')->all())->toBe([$user->id])
        ->and(DB::table('kategori_sistem_user')->where('user_id', $user->id)->whereNotNull('created_at')->count())->toBe(2);

    $user->delete();

    expect(DB::table('kategori_sistem_user')->count())->toBe(0);
});

test('badan penetap memiliki induk dan anak, induk dihapus menjadi null', function () {
    $unsil = BadanPenetap::factory()->create(['nama' => 'Universitas Siliwangi', 'tingkat' => TingkatBadanPenetap::Universitas]);
    $fkip = BadanPenetap::factory()->create(['induk_id' => $unsil->id, 'tingkat' => TingkatBadanPenetap::Fakultas]);

    expect($fkip->induk()->first()?->is($unsil))->toBeTrue()
        ->and($unsil->anak()->pluck('id')->all())->toBe([$fkip->id]);

    $unsil->forceDelete();

    expect($fkip->fresh()->induk_id)->toBeNull();
});

test('jenis dokumen dihapus lunak', function () {
    $jenis = JenisDokumen::factory()->create();

    $jenis->delete();

    expect(JenisDokumen::find($jenis->id))->toBeNull()
        ->and(JenisDokumen::withTrashed()->find($jenis->id)?->trashed())->toBeTrue();

    $jenis->restore();

    expect(JenisDokumen::find($jenis->id))->not->toBeNull();
});

test('enum di-cast pada model', function () {
    $jenis = JenisDokumen::factory()->create(['kelompok' => 'keputusan']);
    $badan = BadanPenetap::factory()->create(['tingkat' => 'kementerian']);

    expect($jenis->fresh()->kelompok)->toBe(KelompokJenisDokumen::Keputusan)
        ->and($badan->fresh()->tingkat)->toBe(TingkatBadanPenetap::Kementerian)
        ->and($jenis->fresh()->wajib_nomor)->toBeTrue()
        ->and(DB::table('jenis_dokumen')->where('id', $jenis->id)->value('kelompok'))->toBe('keputusan');
});

test('wajibNomor benar per kelompok', function (KelompokJenisDokumen $kelompok, bool $wajib) {
    expect($kelompok->wajibNomor())->toBe($wajib)
        ->and($kelompok->getLabel())->toBe($kelompok->label());
})->with([
    [KelompokJenisDokumen::Peraturan, true],
    [KelompokJenisDokumen::Keputusan, true],
    [KelompokJenisDokumen::Kebijakan, false],
    [KelompokJenisDokumen::Pendukung, false],
]);

test('perubahan master tercatat di log aktivitas', function () {
    $tag = Tag::factory()->create(['nama' => 'Dosen']);
    $tag->update(['nama' => 'Dosen dan Tenaga Kependidikan']);

    $log = Aktivitas::where('subject_id', $tag->id)->where('event', 'updated')->firstOrFail();

    expect($log->log_name)->toBe('master')
        ->and($log->attribute_changes?->get('attributes'))->toBe(['nama' => 'Dosen dan Tenaga Kependidikan']);
});
