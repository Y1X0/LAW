<?php

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * توكن مصادقة (زوج Access/Refresh) — يمثّل جلسة نشطة على جهاز.
 */
class AuthToken extends Model
{
    protected $fillable = [
        'user_id', 'name', 'access_token_hash', 'refresh_token_hash',
        'access_expires_at', 'refresh_expires_at',
        'ip_address', 'user_agent', 'last_used_at', 'revoked_at',
    ];

    protected $hidden = ['access_token_hash', 'refresh_token_hash'];

    protected $casts = [
        'access_expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
