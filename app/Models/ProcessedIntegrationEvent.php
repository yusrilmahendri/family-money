<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessedIntegrationEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'event_version',
        'plantation_entity_public_id',
        'finance_entity_id',
        'source_public_id',
        'payload_hash',
        'processed_at',
        'result_type',
        'result_public_id',
    ];

    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }
}
