<?php

use App\Actions\Pengguna\AturPenugasanKategori;
use App\Enums\KelompokJenisDokumen;
use App\Enums\Peran;
use App\Filament\Admin\Resources\JenisDokumenResource\Pages\CreateJenisDokumen;
use App\Filament\Admin\Resources\JenisDokumenResource\Pages\EditJenisDokumen;
use App\Filament\Admin\Resources\JenisDokumenResource\Pages\ListJenisDokumen;
use App\Filament\Admin\Resources\KategoriSistemResource\Pages\EditKategoriSistem;
use App\Filament\Admin\Resources\TagResource\Pages\CreateTag;
use App\Filament\Admin\Resources\TagResource\Pages\ListTag;
use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Aktivitas;
use App\Models\JenisDokumen;
use App\Models\KategoriSistem;
use App\Models\Tag;
use App\Models\User;
use App\Support\CacheRegulasi;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\KategoriSistemSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('grup menu master berisi empat resource', function () {
    $this->actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->get('/admin/jenis-dokumen')
        ->assertOk()
        ->assertSee('Master')
        ->assertSee('Jenis Dokumen')
        ->assertSee('Badan Penetap')
        ->assertSee('Kategori Sistem')
        ->assertSee('Tag');
});

test('admin-regulasi dapat membuat jenis dokumen', function () {
    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(CreateJenisDokumen::class)
        ->fillForm([
            'kode' => 'PERDA',
            'nama' => 'Peraturan Daerah',
            'singkatan' => 'Perda',
            'kelompok' => KelompokJenisDokumen::Peraturan->value,
            'urutan' => 65,
        ])
        ->assertSchemaStateSet(['wajib_nomor' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $jenis = JenisDokumen::where('kode', 'PERDA')->firstOrFail();

    expect($jenis->slug)->toBe('peraturan-daerah')
        ->and($jenis->kelompok)->toBe(KelompokJenisDokumen::Peraturan)
        ->and($jenis->wajib_nomor)->toBeTrue()
        ->and($jenis->aktif)->toBeTrue();
});

test('kode jenis dokumen wajib unik dan berformat', function () {
    JenisDokumen::factory()->create(['kode' => 'SOP']);

    Livewire::actingAs(penggunaDenganPeran(Peran::AdminRegulasi))->test(CreateJenisDokumen::class)
        ->fillForm(['kode' => 'SOP', 'nama' => 'Duplikat', 'kelompok' => 'pendukung', 'urutan' => 1])
        ->call('create')
        ->assertHasFormErrors(['kode' => 'unique'])
        ->fillForm(['kode' => 'sop kecil'])
        ->call('create')
        ->assertHasFormErrors(['kode' => 'regex']);
});

test('kontributor dapat melihat daftar jenis tetapi 403 saat create', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $jenis = JenisDokumen::factory()->create(['nama' => 'Peraturan Menteri']);

    $this->actingAs($kontributor)->get('/admin/jenis-dokumen')->assertOk()->assertSee('Peraturan Menteri');
    $this->actingAs($kontributor)->get('/admin/jenis-dokumen/create')->assertForbidden();
    $this->actingAs($kontributor)->get("/admin/jenis-dokumen/{$jenis->slug}/edit")->assertForbidden();
    $this->actingAs($kontributor)->get('/admin/kategori-sistem/create')->assertForbidden();
    $this->actingAs($kontributor)->get('/admin/badan-penetap/create')->assertForbidden();

    Livewire::actingAs($kontributor)->test(ListJenisDokumen::class)
        ->assertActionHidden('create')
        ->assertActionHidden(TestAction::make('edit')->table($jenis))
        ->assertActionHidden(TestAction::make('delete')->table($jenis));
});

test('pengguna biasa tidak dapat membuka master', function () {
    $this->actingAs(penggunaDenganPeran(Peran::Pengguna))->get('/admin/jenis-dokumen')->assertForbidden();
});

test('kontributor dapat membuat tag tetapi tidak mengubahnya', function () {
    $kontributor = penggunaDenganPeran(Peran::Kontributor);

    Livewire::actingAs($kontributor)->test(CreateTag::class)
        ->fillForm(['nama' => 'Barang Milik Negara'])
        ->call('create')
        ->assertHasNoFormErrors();

    $tag = Tag::where('slug', 'barang-milik-negara')->firstOrFail();

    $this->actingAs($kontributor)->get("/admin/tag/{$tag->slug}/edit")->assertForbidden();

    Livewire::actingAs($kontributor)->test(ListTag::class)
        ->assertActionVisible('create')
        ->assertActionHidden(TestAction::make('edit')->table($tag))
        ->assertActionHidden(TestAction::make('delete')->table($tag));
});

test('kode tidak dapat diubah saat edit', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $jenis = JenisDokumen::factory()->create(['kode' => 'SE', 'nama' => 'Surat Edaran']);
    $kategori = KategoriSistem::factory()->create(['kode' => 'aset', 'nama' => 'Aset/BMN']);

    Livewire::actingAs($admin)->test(EditJenisDokumen::class, ['record' => $jenis->getRouteKey()])
        ->assertFormFieldDisabled('kode')
        ->fillForm(['kode' => 'SEX', 'nama' => 'Surat Edaran Rektor'])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::actingAs($admin)->test(EditKategoriSistem::class, ['record' => $kategori->getRouteKey()])
        ->assertFormFieldDisabled('kode')
        ->fillForm(['kode' => 'bmn'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($jenis->fresh()->kode)->toBe('SE')
        ->and($jenis->fresh()->nama)->toBe('Surat Edaran Rektor')
        ->and($kategori->fresh()->kode)->toBe('aset');
});

test('master dihapus lunak dan dapat dipulihkan dari tabel', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $jenis = JenisDokumen::factory()->create();

    Livewire::actingAs($admin)->test(ListJenisDokumen::class)
        ->callAction(TestAction::make('delete')->table($jenis));

    expect($jenis->fresh()->trashed())->toBeTrue();

    Livewire::actingAs($admin)->test(ListJenisDokumen::class)
        ->filterTable('trashed', false)
        ->assertActionDoesNotExist(TestAction::make('forceDelete')->table($jenis))
        ->callAction(TestAction::make('restore')->table($jenis));

    expect($jenis->fresh()->trashed())->toBeFalse();
});

test('urutan jenis dapat diubah dengan seret lepas dan cache master dibersihkan', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $a = JenisDokumen::factory()->create(['urutan' => 10]);
    $b = JenisDokumen::factory()->create(['urutan' => 20]);
    CacheRegulasi::master('jenis-dokumen');

    Livewire::actingAs($admin)->test(ListJenisDokumen::class)
        ->call('reorderTable', [$b->id, $a->id]);

    expect($b->fresh()->urutan)->toBeLessThan($a->fresh()->urutan)
        ->and(Cache::has('master:jenis-dokumen'))->toBeFalse();
});

test('admin-regulasi menugaskan kontributor ke kategori aset lewat aksi atur penugasan dan tercatat di log', function () {
    $this->seed(KategoriSistemSeeder::class);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $aset = KategoriSistem::where('kode', 'aset')->firstOrFail();

    Livewire::actingAs($admin)->test(ListUsers::class)
        ->assertActionHidden(TestAction::make('edit')->table($kontributor))
        ->assertActionVisible(TestAction::make('aturPenugasan')->table($kontributor))
        ->callAction(TestAction::make('aturPenugasan')->table($kontributor), data: ['kategori_sistem' => [$aset->id]])
        ->assertHasNoFormErrors();

    expect($kontributor->kategoriSistem()->pluck('kode')->all())->toBe(['aset']);

    $log = Aktivitas::where('subject_id', $kontributor->id)->where('event', 'penugasan-diubah')->firstOrFail();

    expect($log->causer_id)->toBe($admin->id)
        ->and($log->properties->get('ditambahkan'))->toBe(['aset'])
        ->and($log->properties->get('dicabut'))->toBe([]);
});

test('penugasan yang dicabut tercatat dan tanpa perubahan tidak menambah log', function () {
    $this->seed(KategoriSistemSeeder::class);
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $aset = KategoriSistem::where('kode', 'aset')->firstOrFail();
    $sdm = KategoriSistem::where('kode', 'sdm')->firstOrFail();
    $aksi = app(AturPenugasanKategori::class);

    $aksi->handle($kontributor, [$aset->id, $sdm->id], $admin);
    $aksi->handle($kontributor, [$sdm->id], $admin);
    $aksi->handle($kontributor, [$sdm->id], $admin);

    $log = Aktivitas::where('subject_id', $kontributor->id)->where('event', 'penugasan-diubah')->orderBy('id')->get();

    expect($log)->toHaveCount(2)
        ->and($log->last()->properties->get('dicabut'))->toBe(['aset'])
        ->and($kontributor->kategoriSistem()->pluck('kode')->all())->toBe(['sdm']);
});

test('atur penugasan hanya untuk kontributor dan hanya oleh pemilik izin penugasan', function () {
    $admin = penggunaDenganPeran(Peran::AdminRegulasi);
    $kontributor = penggunaDenganPeran(Peran::Kontributor);
    $pengguna = penggunaDenganPeran(Peran::Pengguna);
    $kategori = KategoriSistem::factory()->create();

    Livewire::actingAs($admin)->test(ListUsers::class)
        ->assertActionHidden(TestAction::make('aturPenugasan')->table($pengguna));

    expect(fn () => app(AturPenugasanKategori::class)->handle($kontributor, [$kategori->id], $kontributor))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(AturPenugasanKategori::class)->handle($pengguna, [$kategori->id], $admin))
        ->toThrow(AuthorizationException::class);
    expect($kontributor->kategoriSistem()->count())->toBe(0);
});

test('super-admin mengatur kategori lewat form pengguna hanya bila peran kontributor', function () {
    Notification::fake();
    $this->seed(KategoriSistemSeeder::class);
    $super = penggunaDenganPeran(Peran::SuperAdmin);
    $aset = KategoriSistem::where('kode', 'aset')->firstOrFail();

    Livewire::actingAs($super)->test(CreateUser::class)
        ->fillForm(['peran' => [Peran::Pengguna->value]])
        ->assertFormFieldHidden('kategori_sistem')
        ->fillForm([
            'name' => 'Pengelola Aset',
            'email' => 'aset@unsil.ac.id',
            'peran' => [Peran::Kontributor->value],
        ])
        ->assertFormFieldVisible('kategori_sistem')
        ->fillForm(['kategori_sistem' => [$aset->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $baru = User::where('email', 'aset@unsil.ac.id')->firstOrFail();
    expect($baru->kategoriSistem()->pluck('kode')->all())->toBe(['aset']);

    Livewire::actingAs($super)->test(EditUser::class, ['record' => $baru->getKey()])
        ->assertSchemaStateSet(['kategori_sistem' => [$aset->id]])
        ->fillForm(['kategori_sistem' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($baru->kategoriSistem()->count())->toBe(0);
});

test('seeder lokal menugaskan kontributor aset ke kategori aset', function () {
    app()->detectEnvironment(fn () => 'local');

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'kontributor.aset@unsil.test')->firstOrFail()->kategoriSistem()->pluck('kode')->all())
        ->toBe(['aset']);
});
