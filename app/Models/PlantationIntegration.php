<?php

namespace App\Models;

use App\Enums\PlantationIntegrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantationIntegration extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'finance_entity_id',
        'plantation_entity_public_id',
        'status',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlantationIntegrationStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }

    public function isActive(): bool
    {
        return $this->status === PlantationIntegrationStatus::ACTIVE;
    }
}
