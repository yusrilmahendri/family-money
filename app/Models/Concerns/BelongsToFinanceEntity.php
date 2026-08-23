<?php

namespace App\Models\Concerns;

use App\Models\FinanceEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToFinanceEntity
{
    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }

    public function scopeForEntity(Builder $query, FinanceEntity $entity): Builder
    {
        return $query->where($this->getTable().'.finance_entity_id', $entity->id);
    }
}
