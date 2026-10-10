<?php

namespace App\Listeners;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * Mencatat pemberian dan pencabutan peran pengguna ke log aktivitas.
 */
class CatatPerubahanPeran
{
    public function handleRoleAttached(RoleAttachedEvent $event): void
    {
        $this->catat($event->model, $event->rolesOrIds, 'peran-ditambahkan', 'Peran ditambahkan');
    }

    public function handleRoleDetached(RoleDetachedEvent $event): void
    {
        $this->catat($event->model, $event->rolesOrIds, 'peran-dicabut', 'Peran dicabut');
    }

    private function catat(Model $model, mixed $rolesOrIds, string $peristiwa, string $deskripsi): void
    {
        if (! $model instanceof User) {
            return;
        }

        $nama = $this->namaPeran($rolesOrIds);

        if ($nama === []) {
            return;
        }

        activity('pengguna')
            ->performedOn($model)
            ->causedBy(auth()->user())
            ->event($peristiwa)
            ->withProperties(['peran' => $nama])
            ->log($deskripsi);
    }

    /**
     * @return list<string>
     */
    private function namaPeran(mixed $rolesOrIds): array
    {
        $daftar = $rolesOrIds instanceof Collection ? $rolesOrIds->all() : (is_array($rolesOrIds) ? $rolesOrIds : [$rolesOrIds]);

        $nama = [];
        $id = [];

        foreach ($daftar as $item) {
            if ($item instanceof Role) {
                $nama[] = $item->name;
            } elseif (is_string($item) || is_int($item)) {
                $id[] = $item;
            }
        }

        if ($id !== []) {
            $nama = [...$nama, ...Role::query()->whereKey($id)->pluck('name')->all()];
        }

        sort($nama);

        return array_values(array_unique($nama));
    }
}
