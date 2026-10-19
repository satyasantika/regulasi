<?php

use App\Models\Aktivitas;
use App\Models\TokenAkses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('id pengguna adalah uuid versi 7', function () {
    $id = User::factory()->create()->id;

    expect(Str::isUuid($id))->toBeTrue()
        ->and($id[14])->toBe('7');
});

test('kolom users.id bertipe uuid atau char(36)', function () {
    expect(Schema::getColumnType('users', 'id'))->toBeIn(['uuid', 'char']);
});

test('route model binding dengan uuid berjalan', function () {
    $user = User::factory()->create();

    Route::get('/uji-uuid/{user}', fn (User $user) => $user->email)
        ->middleware('web')
        ->whereUuid('user');

    $this->get('/uji-uuid/'.$user->id)->assertOk()->assertSee($user->email);
    $this->get('/uji-uuid/bukan-uuid')->assertNotFound();
});

test('token sanctum memakai uuid', function () {
    $user = User::factory()->create();
    $user->createToken('uji');

    $token = TokenAkses::query()->firstOrFail();

    expect(Str::isUuid($token->id))->toBeTrue()
        ->and($token->tokenable_id)->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Gerbang skema UUID (STANDAR-TEKNIS §4a, Profil Ringan 16 Oktober 2026)
|--------------------------------------------------------------------------
| Tipe kolom mengikuti Blueprint Laravel 13: MariaDB >= 10.7 memakai tipe UUID native, driver lain char(36).
| Hanya DUA query information_schema untuk seluruh skema (Schema::getForeignKeys() per tabel sangat lambat).
*/
const TIPE_UUID = ['uuid', 'char(36)'];

// tabel kerangka kerja / paket dengan kunci non-UUID yang diizinkan (§4a butir 6)
const TABEL_KUNCI_BUKAN_UUID = [
    'migrations', 'jobs', 'failed_jobs', 'job_batches', 'sessions',
    'imports', 'exports', 'failed_import_rows',
];

/**
 * @return array<string, array<string, string>> [tabel => [kolom => tipe]]
 */
function petaTipeKolom(): array
{
    $peta = [];

    foreach (DB::select(
        'SELECT TABLE_NAME AS tabel, COLUMN_NAME AS kolom, COLUMN_TYPE AS tipe
         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
    ) as $baris) {
        $peta[$baris->tabel][$baris->kolom] = strtolower($baris->tipe);
    }

    return $peta;
}

it('memakai tipe uuid pada kolom id semua tabel aplikasi', function () {
    foreach (petaTipeKolom() as $tabel => $kolom) {
        if (in_array($tabel, TABEL_KUNCI_BUKAN_UUID, true) || ! array_key_exists('id', $kolom)) {
            continue;
        }

        expect($kolom['id'])->toBeIn(TIPE_UUID, "{$tabel}.id bertipe {$kolom['id']}");
    }
});

it('menyamakan tipe kolom foreign key dengan kolom yang diacunya', function () {
    $peta = petaTipeKolom();

    $fk = DB::select(
        'SELECT TABLE_NAME AS tabel, COLUMN_NAME AS kolom, REFERENCED_TABLE_NAME AS acuan_tabel,
                REFERENCED_COLUMN_NAME AS acuan_kolom
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
    );

    expect($fk)->not->toBeEmpty();

    foreach ($fk as $b) {
        expect($peta[$b->tabel][$b->kolom])->toBe(
            $peta[$b->acuan_tabel][$b->acuan_kolom],
            "FK {$b->tabel}.{$b->kolom} ({$peta[$b->tabel][$b->kolom]}) tidak sama dengan {$b->acuan_tabel}.{$b->acuan_kolom}"
        );
    }
});

it('mengurutkan 100 id UUIDv7 sesuai urutan pembuatan pada ORDER BY id', function () {
    $dibuat = collect(range(1, 100))->map(fn () => User::factory()->create()->id);

    expect(User::query()->orderBy('id')->pluck('id')->all())->toBe($dibuat->all());
});

it('menyimpan relasi polimorfik dengan id UUID', function () {
    $pengguna = User::factory()->create();

    activity()->performedOn($pengguna)->log('uji-uuid');

    $aktivitas = Aktivitas::query()->where('subject_id', $pengguna->id)->firstOrFail();

    expect($aktivitas->subject_id)->toBe($pengguna->id)
        ->and($aktivitas->subject->is($pengguna))->toBeTrue();
});
