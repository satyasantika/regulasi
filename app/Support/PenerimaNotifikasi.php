<?php

namespace App\Support;

use App\Enums\Peran;
use App\Models\Dokumen;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifikasi;

/**
 * Penentuan penerima notifikasi (02-ARSITEKTUR §10). Semua kueri hanya mengambil pengguna aktif dan memakai
 * whereHas (bukan scope role()/permission() spatie) agar tidak galat bila peran/izin belum di-seed.
 */
final class PenerimaNotifikasi
{
    /**
     * Verifikator: pengguna aktif ber-permission dokumen.verifikasi (admin-regulasi). Super-admin hanya dipakai
     * sebagai cadangan bila tidak ada satu pun admin aktif, agar tidak bising.
     *
     * @return Collection<int, User>
     */
    public static function admin(): Collection
    {
        $izin = fn (Builder $q) => $q->where('name', 'dokumen.verifikasi');

        $admin = User::query()
            ->where('aktif', true)
            ->where(fn (Builder $q) => $q->whereHas('roles.permissions', $izin)->orWhereHas('permissions', $izin))
            ->get();

        if ($admin->isNotEmpty()) {
            return $admin;
        }

        return self::berperan(Peran::SuperAdmin);
    }

    /**
     * Pengguna aktif dengan peran tertentu (tanpa galat bila peran belum di-seed).
     *
     * @return Collection<int, User>
     */
    public static function berperan(Peran $peran): Collection
    {
        return User::query()
            ->where('aktif', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', $peran->value))
            ->get();
    }

    /**
     * Kontributor aktif yang ditugaskan ke salah satu kategori sistem dokumen (US-REL-04).
     *
     * @return Collection<int, User>
     */
    public static function kontributorKategori(Dokumen $dokumen): Collection
    {
        $kategori = $dokumen->kategoriSistem()->pluck('kategori_sistem.id');

        if ($kategori->isEmpty()) {
            return new Collection;
        }

        return User::query()
            ->where('aktif', true)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', Peran::Kontributor->value))
            ->whereHas('kategoriSistem', fn (Builder $q) => $q->whereKey($kategori->all()))
            ->get();
    }

    /**
     * Pengguna aktif menurut id (mis. pembuat draf, penambah tautan).
     *
     * @return Collection<int, User>
     */
    public static function pengguna(?string $id): Collection
    {
        if ($id === null) {
            return new Collection;
        }

        return User::query()->whereKey($id)->where('aktif', true)->get();
    }

    /**
     * Kirim ke penerima unik yang aktif, tanpa pelaku aksi itu sendiri.
     *
     * @param  iterable<User>  $penerima
     */
    public static function kirim(iterable $penerima, Notification $notifikasi, ?string $kecualiId = null): void
    {
        $penerima = collect($penerima)
            ->filter(fn (User $u): bool => $u->aktif && $u->getKey() !== $kecualiId)
            ->unique(fn (User $u): string => (string) $u->getKey())
            ->values();

        if ($penerima->isNotEmpty()) {
            Notifikasi::send($penerima, $notifikasi);
        }
    }
}
