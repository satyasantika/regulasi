<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

/**
 * Tag subjek: kontributor boleh menambah (tag.buat); ubah/hapus hanya pengelola master.
 */
class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('master.lihat');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->can('master.lihat');
    }

    public function create(User $user): bool
    {
        return $user->can('tag.buat');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->can('master.kelola');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->can('master.kelola');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('master.kelola');
    }
}
