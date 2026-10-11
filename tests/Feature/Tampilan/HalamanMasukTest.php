<?php

test('halaman masuk kedua panel memakai tata letak kiri-kanan bertema landing', function (string $path, string $lencana) {
    $this->get($path)
        ->assertOk()
        ->assertSee('r-pisah__kiri', false)
        ->assertSee('r-pisah__kanan', false)
        ->assertSee(asset('css/regulasi.css'), false)
        ->assertSee($lencana)
        ->assertSee(route('beranda'), false)
        ->assertSee('Masuk ke akun Anda');
})->with([
    ['/admin/login', 'Panel staf'],
    ['/akun/login', 'Akun pengguna kampus'],
]);

test('halaman daftar akun memakai tata letak yang sama', function () {
    $this->get('/akun/register')->assertOk()->assertSee('r-pisah__kiri', false);
});

test('berkas tema bersama tersedia', function () {
    expect(is_file(public_path('css/regulasi.css')))->toBeTrue()
        ->and((string) file_get_contents(public_path('css/regulasi.css')))->toContain('.r-pisah')->toContain('.r-galat');
});
