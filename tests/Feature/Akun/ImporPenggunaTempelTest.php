<?php

use App\Actions\Pengguna\ImporPenggunaTempel;
use App\Enums\Peran;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\KategoriSistem;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->super = penggunaDenganPeran(Peran::SuperAdmin);
    $this->aset = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset/BMN', 'aktif' => true]);
    $this->sdm = KategoriSistem::factory()->create(['kode' => 'sdm', 'nama' => 'SDM', 'aktif' => true]);
});

function teksTempel(): string
{
    return "Nama\tSurel\tPeran\tKategori\n"
        ."Dewi Lestari\tDewi@unsil.ac.id\tkontributor\taset|sdm\n"
        ."Budi Santoso\tbudi@unsil.ac.id\tpengguna\n"
        ."Rina Admin\trina@unsil.ac.id\tAdmin Regulasi Fakultas";
}

test('impor tab dari Excel membuat akun, peran, kategori kontributor, dan mengirim tautan atur sandi', function () {
    Notification::fake();

    $hasil = app(ImporPenggunaTempel::class)->handle(teksTempel(), $this->super);

    expect($hasil)->toMatchArray(['dibuat' => 3, 'dilewati' => [], 'galat' => []]);

    $dewi = User::where('email', 'dewi@unsil.ac.id')->sole();
    expect($dewi->hasRole(Peran::Kontributor->value))->toBeTrue()
        ->and($dewi->kategoriSistem->pluck('kode')->sort()->values()->all())->toBe(['aset', 'sdm'])
        ->and($dewi->email_verified_at)->not->toBeNull()
        ->and($dewi->wajib_ganti_sandi)->toBeFalse()
        ->and(User::where('email', 'rina@unsil.ac.id')->sole()->hasRole(Peran::AdminRegulasi->value))->toBeTrue();

    Notification::assertSentTo($dewi, ResetPassword::class);
    expect(Activity::query()->where('event', 'impor-pengguna')->sole()->properties['dibuat'])->toHaveCount(3);
});

test('impor dengan kata sandi awal menandai wajib ganti sandi dan tidak mengirim surel', function () {
    Notification::fake();

    app(ImporPenggunaTempel::class)->handle('Budi;budi@unsil.ac.id;pengguna', $this->super, 'sandi-awal-123');

    $budi = User::where('email', 'budi@unsil.ac.id')->sole();
    expect($budi->wajib_ganti_sandi)->toBeTrue()->and(Hash::check('sandi-awal-123', $budi->password))->toBeTrue();
    Notification::assertNothingSent();
});

test('idempoten: impor ulang data yang sama tidak membuat akun ganda dan tidak mengubah akun lama', function () {
    Notification::fake();
    app(ImporPenggunaTempel::class)->handle(teksTempel(), $this->super);
    User::where('email', 'budi@unsil.ac.id')->update(['name' => 'Budi Diubah']);

    $hasil = app(ImporPenggunaTempel::class)->handle(teksTempel(), $this->super);

    expect($hasil['dibuat'])->toBe(0)
        ->and($hasil['dilewati'])->toBe(['dewi@unsil.ac.id', 'budi@unsil.ac.id', 'rina@unsil.ac.id'])
        ->and(User::where('email', 'budi@unsil.ac.id')->sole()->name)->toBe('Budi Diubah')
        ->and(User::count())->toBe(4);
});

test('validasi: domain, peran, kategori, duplikat, dan kategori non-kontributor membatalkan seluruh impor', function () {
    $teks = "A\ta@gmail.com\tpengguna\n"
        ."B\tb@unsil.ac.id\tpenyihir\n"
        ."C\tc@unsil.ac.id\tkontributor\ttidak-ada\n"
        ."D\td@unsil.ac.id\tpengguna\taset\n"
        ."E\te@unsil.ac.id\tpengguna\n"
        ."E2\te@unsil.ac.id\tpengguna\n"
        ."F\tf@unsil.ac.id";

    $hasil = app(ImporPenggunaTempel::class)->handle($teks, $this->super);

    expect($hasil['dibuat'])->toBe(0)
        ->and(implode("\n", $hasil['galat']))
        ->toContain('Baris 1: surel harus berdomain @unsil.ac.id')
        ->toContain("Baris 2: peran 'penyihir' tidak dikenal")
        ->toContain("Baris 3: kategori 'tidak-ada' tidak ditemukan")
        ->toContain('Baris 4: kategori hanya untuk peran kontributor')
        ->toContain('Baris 6: surel e@unsil.ac.id ganda')
        ->toContain('Baris 7: minimal berisi nama, surel, dan peran')
        ->and(User::count())->toBe(1);
});

test('hanya super-admin yang dapat mengimpor', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);

    expect(fn () => app(ImporPenggunaTempel::class)->handle(teksTempel(), $admin))->toThrow(AuthorizationException::class);

    Filament::setCurrentPanel('admin');
    Livewire::actingAs($admin)->test(ListUsers::class)->assertActionHidden('imporTempel');
});

test('aksi impor di panel menampilkan pratinjau lalu membuat akun', function () {
    Notification::fake();
    Filament::setCurrentPanel('admin');

    expect(ListUsers::ringkasPratinjau(teksTempel())[0])->toBe('3 akun baru akan dibuat, 0 dilewati karena sudah terdaftar.');

    Livewire::actingAs($this->super)->test(ListUsers::class)
        ->callAction('imporTempel', ['teks' => teksTempel()])
        ->assertHasNoActionErrors()
        ->assertNotified('3 pengguna dibuat, 0 dilewati (sudah terdaftar)');

    expect(User::where('email', 'dewi@unsil.ac.id')->exists())->toBeTrue();
});
