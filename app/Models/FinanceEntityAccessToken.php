<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceEntityAccessToken extends Model
{
    /** @use HasFactory<\Database\Factories\FinanceEntityAccessTokenFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }

    /**
     * 256-bit cryptographically secure token (64 hex characters).
     * Never derived from entity id, slug, public_id, name, or time.
     */
    public static function generatePlainToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        $entity = $this->financeEntity;

        return $entity instanceof FinanceEntity && $entity->is_active;
    }
}
