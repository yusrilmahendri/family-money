<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalFinancialReference extends Model
{
    protected $fillable = [
        'finance_entity_id',
        'source_system',
        'event_type',
        'source_public_id',
        'record_type',
        'record_id',
    ];

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }
}
