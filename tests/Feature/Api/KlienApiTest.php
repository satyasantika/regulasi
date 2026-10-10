<?php

use App\Actions\Api\BuatTokenKlienApi;
use App\Enums\Peran;
use App\Filament\Admin\Resources\KlienApiResource\Pages\CreateKlienApi;
use App\Filament\Admin\Resources\KlienApiResource\Pages\ListKlienApi;
use App\Filament\Admin\Resources\KlienApiResource\Pages\ViewKlienApi;
use App\Filament\Admin\Resources\KlienApiResource\RelationManagers\TokenRelationManager;
use App\Http\Middleware\PastikanKlienApiAktif;
use App\Models\Aktivitas;
use App\Models\KategoriSistem;
use App\Models\KlienApi;
use App\Models\TokenAkses;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Cache::flush();

    // Rute uji dengan tumpukan middleware API baca (BR-18).
    Route::middleware(['auth:sanctum', 'abilities:dokumen:baca', PastikanKlienApiAktif::class])
        ->get('/api/uji-klien', fn () => ['ok' => true]);
});

test('super-admin membuat klien lalu token: tokenable klien_api dengan abilities benar, token polos tampil sekali', function () {
    $superAdmin = penggunaDenganPeran(Peran::SuperAdmin);
    $aset = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset']);

    Livewire::actingAs($superAdmin)->test(CreateKlienApi::class)
        ->fillForm([
            'nama' => 'Sistem Aset FKIP',
            'kategori_sistem_id' => $aset->id,
            'kontak_nama' => 'Pengelola BMN',
            'kontak_email' => 'bmn@unsil.ac.id',
            'aktif' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $klien = KlienApi::query()->where('nama', 'Sistem Aset FKIP')->sole();
    expect($klien->kategori_sistem_id)->toBe($aset->id);

    $this->actingAs($superAdmin)->get("/admin/klien-api/{$klien->id}")->assertOk()->assertSee('Sistem Aset FKIP');

    $komponen = Livewire::actingAs($superAdmin)
        ->test(TokenRelationManager::class, ['ownerRecord' => $klien, 'pageClass' => ViewKlienApi::class])
        ->callAction(TestAction::make('buatToken')->table(), [
            'nama' => 'Produksi',
            'baca_internal' => true,
            'masa_berlaku' => 180,
        ])
        ->assertHasNoFormErrors()
        ->assertActionMounted('tampilkanToken');

    $token = TokenAkses::query()->sole();
    expect($token->tokenable_type)->toBe('klien_api')
        ->and($token->tokenable_id)->toBe($klien->id)
        ->and($token->abilities)->toBe(['dokumen:baca', 'dokumen:baca-internal'])
        ->and($token->expires_at?->toDateString())->toBe(now()->addDays(180)->toDateString());

    // Token polos hanya ada di modal saat ini dan cocok dengan hash tersimpan.
    $polos = $komponen->instance()->mountedActions[1]['arguments']['token'];
    expect(hash('sha256', explode('|', $polos, 2)[1]))->toBe($token->token);

    // Sesi panel tidak terbawa ke API (rute API tanpa sesi).
    app('auth')->forgetGuards();
    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$polos])->assertOk()->assertJson(['ok' => true]);

    expect(Aktivitas::query()->where('event', 'token-dibuat')->exists())->toBeTrue();
});

test('token tanpa centang internal hanya ber-ability dokumen:baca', function () {
    $klien = KlienApi::factory()->create();

    $token = app(BuatTokenKlienApi::class)->handle($klien, 'Uji', false, 90, penggunaDenganPeran(Peran::SuperAdmin));

    expect($token->accessToken->abilities)->toBe(['dokumen:baca'])
        ->and($klien->tokens()->count())->toBe(1);
});

test('admin-regulasi dapat melihat klien tetapi tidak dapat membuat klien maupun token', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $klien = KlienApi::factory()->create(['nama' => 'Sistem Akreditasi']);

    $this->actingAs($admin)->get('/admin/klien-api')->assertOk()->assertSee('Sistem Akreditasi');
    $this->actingAs($admin)->get('/admin/klien-api/create')->assertForbidden();

    Livewire::actingAs($admin)->test(ListKlienApi::class)->assertActionHidden('create');

    Livewire::actingAs($admin)
        ->test(TokenRelationManager::class, ['ownerRecord' => $klien, 'pageClass' => ViewKlienApi::class])
        ->assertActionHidden(TestAction::make('buatToken')->table());

    expect(fn () => app(BuatTokenKlienApi::class)->handle($klien, 'Uji', false, 90, $admin))
        ->toThrow(AuthorizationException::class)
        ->and(TokenAkses::count())->toBe(0);
});

test('kontributor tidak dapat membuka klien API', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Kontributor))->get('/admin/klien-api')->assertForbidden();
});

test('super-admin mencabut token: permintaan berikutnya 401', function () {
    $superAdmin = penggunaDenganPeran(Peran::SuperAdmin);
    $klien = KlienApi::factory()->create();
    $token = app(BuatTokenKlienApi::class)->handle($klien, 'Uji', false, 90, $superAdmin);

    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertOk();

    Livewire::actingAs($superAdmin)
        ->test(TokenRelationManager::class, ['ownerRecord' => $klien, 'pageClass' => ViewKlienApi::class])
        ->callAction(TestAction::make('cabut')->table($token->accessToken));

    expect(TokenAkses::count())->toBe(0);

    app('auth')->forgetGuards();
    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
});

test('klien nonaktif → 401 JSON:API', function () {
    $klien = KlienApi::factory()->nonaktif()->create();
    $token = $klien->createToken('Uji', ['dokumen:baca']);

    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertExactJson(['errors' => [['status' => '401', 'title' => 'Klien API tidak aktif']]]);
});

test('menonaktifkan klien membuat token yang tadinya sah ditolak', function () {
    $klien = KlienApi::factory()->create();
    $token = $klien->createToken('Uji', ['dokumen:baca']);

    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertOk();

    $klien->update(['aktif' => false]);
    app('auth')->forgetGuards();

    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
});

test('pengguna (bukan klien API) dengan token Sanctum → 401', function () {
    $pengguna = penggunaDenganPeran(Peran::Pengguna);
    $token = $pengguna->createToken('pribadi', ['dokumen:baca']);

    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
});

test('terakhir_dipakai_pada diperbarui maksimal sekali per menit', function () {
    $this->freezeSecond();
    $klien = KlienApi::factory()->create();
    $token = $klien->createToken('Uji', ['dokumen:baca']);
    $header = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->getJson('/api/uji-klien', $header)->assertOk();
    $pertama = $klien->refresh()->terakhir_dipakai_pada;
    expect($pertama?->toDateTimeString())->toBe(now()->toDateTimeString());

    $this->travel(30)->seconds();
    $this->getJson('/api/uji-klien', $header)->assertOk();
    expect($klien->refresh()->terakhir_dipakai_pada?->toDateTimeString())->toBe($pertama->toDateTimeString());

    $this->travel(31)->seconds();
    $this->getJson('/api/uji-klien', $header)->assertOk();
    expect($klien->refresh()->terakhir_dipakai_pada?->toDateTimeString())->toBe(now()->toDateTimeString());
});

test('token kedaluwarsa → 401', function () {
    $klien = KlienApi::factory()->create();
    $token = $klien->createToken('Uji', ['dokumen:baca'], now()->addDays(90));

    $this->travel(91)->days();

    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertUnauthorized();
});

test('token tanpa ability dokumen:baca → 403', function () {
    $klien = KlienApi::factory()->create();
    $token = $klien->createToken('Uji', ['lain']);

    $this->getJson('/api/uji-klien', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertForbidden();
});
