<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token API Sanctum dengan primary key UUIDv7 (STANDAR-TEKNIS §4a butir 5).
 */
class TokenAkses extends PersonalAccessToken
{
    use HasUuids;

    protected $table = 'personal_access_tokens';
}
