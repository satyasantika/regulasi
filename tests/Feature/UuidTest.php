<?php

use App\Models\TokenAkses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
