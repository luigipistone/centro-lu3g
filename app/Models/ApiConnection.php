<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ApiConnection extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function enabled(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }
}
