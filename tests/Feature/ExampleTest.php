<?php

it('menampilkan halaman awal', function () {
    $this->get('/')->assertOk();
});
