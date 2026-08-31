<?php

namespace App\Models;

use App\Enums\PortalAccessResourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalAccessGrant extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'resource_type',
        'finance_entity_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_type' => PortalAccessResourceType::class,
        ];
    }

    public function portalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PortalAccessToken::class);
    }

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }

    public function isFinance(): bool
    {
        return $this->resource_type === PortalAccessResourceType::FINANCE;
    }

    public function isPlantation(): bool
    {
        return $this->resource_type === PortalAccessResourceType::PLANTATION;
    }
}
