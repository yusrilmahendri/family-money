<?php

namespace App\Models;

use App\Enums\PortalAccessResourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PortalAccessToken extends Model
{
    /** @use HasFactory<\Database\Factories\PortalAccessTokenFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
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

    protected static function booted(): void
    {
        static::creating(function (PortalAccessToken $token): void {
            if (blank($token->name)) {
                throw new InvalidArgumentException('PortalAccess name is required.');
            }

            if (blank($token->public_id)) {
                $token->public_id = static::generatePublicId();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function grants(): HasMany
    {
        return $this->hasMany(PortalAccessGrant::class);
    }

    /**
     * Reuse the same 256-bit hex token as FinanceEntity access links.
     */
    public static function generatePlainToken(): string
    {
        return FinanceEntityAccessToken::generatePlainToken();
    }

    public static function hashToken(string $plainToken): string
    {
        return FinanceEntityAccessToken::hashToken($plainToken);
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->expires_at === null || ! $this->expires_at->isPast();
    }

    public function hasGrant(PortalAccessResourceType $type, FinanceEntity $entity): bool
    {
        $this->loadMissing('grants');

        return $this->grants->contains(
            fn (PortalAccessGrant $grant) => $grant->resource_type === $type
                && (int) $grant->finance_entity_id === (int) $entity->id
        );
    }

    public function hasFinanceGrant(FinanceEntity $entity): bool
    {
        return $this->hasGrant(PortalAccessResourceType::FINANCE, $entity);
    }

    public function hasPlantationGrant(FinanceEntity $entity): bool
    {
        return $this->hasGrant(PortalAccessResourceType::PLANTATION, $entity);
    }

    /**
     * @return list<string>
     */
    public function grantKeys(): array
    {
        $this->loadMissing('grants.financeEntity');

        return $this->grants
            ->filter(fn (PortalAccessGrant $grant) => $grant->financeEntity instanceof FinanceEntity)
            ->map(fn (PortalAccessGrant $grant) => $grant->resource_type->value.':'.$grant->financeEntity->public_id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function grantLabels(): array
    {
        $this->loadMissing('grants.financeEntity');

        return $this->grants
            ->filter(fn (PortalAccessGrant $grant) => $grant->financeEntity instanceof FinanceEntity)
            ->map(function (PortalAccessGrant $grant) {
                $entity = $grant->financeEntity;

                if ($grant->isPlantation()) {
                    return 'Management Kebun — '.$entity->name;
                }

                return $entity->isFamily()
                    ? 'Keuangan Keluarga — '.$entity->name
                    : 'Keuangan Usaha — '.$entity->name;
            })
            ->values()
            ->all();
    }
}
