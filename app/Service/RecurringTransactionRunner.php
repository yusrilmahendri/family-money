<?php

namespace App\Service;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class RecurringTransactionRunner
{
    /**
     * Post semua recurring transaction yang sudah jatuh tempo.
     * Untuk frequency monthly/weekly/yearly bisa lebih dari 1 posting kalau lama tidak dibuka.
     *
     * @return int Jumlah transaction baru yang dibuat.
     */
    public function runDue(): int
    {
        if (! Schema::hasTable('recurring_transactions')) {
            return 0;
        }

        $today = Carbon::today();
        $created = 0;

        RecurringTransaction::query()
            ->where('active', true)
            ->where('next_due', '<=', $today)
            ->get()
            ->each(function (RecurringTransaction $rt) use ($today, &$created) {
                $guard = 100; // batas iterasi agar tidak infinite loop

                while ($rt->active && $rt->next_due && $rt->next_due->lte($today) && $guard-- > 0) {
                    if ($rt->end_date && $rt->next_due->gt($rt->end_date)) {
                        $rt->active = false;
                        $rt->save();
                        break;
                    }

                    Transaction::create([
                        'category_id' => $rt->category_id,
                        'amount' => $rt->amount,
                        'transaction_date' => $rt->next_due,
                        'description' => '[Otomatis] '.$rt->name,
                        'keterangan_detail' => $rt->description,
                    ]);

                    $created++;
                    $rt->last_posted_at = $rt->next_due;
                    $rt->next_due = $rt->calculateNextDue($rt->next_due->copy()->addDay());
                    $rt->save();
                }
            });

        return $created;
    }
}
