<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menampilkan halaman awal', function () {
    $this->get('/')->assertOk();
});
