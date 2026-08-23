<?php

namespace App\Models\Concerns;

use App\Models\FinanceAccount;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToFinanceAccount
{
    public function financeAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class);
    }
}
