<?php

namespace App\Services;

use App\Enums\AuditActorType;
use App\Models\FinanceAccount;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Support\FinanceOwnership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RecurringTransactionRunner
{
    /**
     * Post semua recurring transaction yang sudah jatuh tempo.
     *
     * Account behavior:
     * - finance_account_id yang tersimpan disalin ke Transaction;
     * - jika kosong, fallback ke default account entity (legacy);
     * - jika account tersimpan tidak aktif / bukan milik entity, posting di-skip.
     *   next_due tidak digeser agar bisa diposting ulang setelah account diaktifkan.
     *   Tidak ada fallback diam-diam ke account lain.
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
                $guard = 100;

                while ($rt->active && $rt->next_due && $rt->next_due->lte($today) && $guard-- > 0) {
                    if ($rt->end_date && $rt->next_due->gt($rt->end_date)) {
                        $rt->active = false;
                        $rt->save();
                        break;
                    }

                    $account = $this->accountForPosting($rt);

                    if (! $account instanceof FinanceAccount) {
                        Log::warning('Recurring transaction skipped because its finance account is missing or inactive.', [
                            'recurring_id' => $rt->id,
                            'finance_entity_id' => $rt->finance_entity_id,
                            'finance_account_id' => $rt->finance_account_id,
                            'next_due' => $rt->next_due?->toDateString(),
                        ]);
                        break;
                    }

                    $entity = $rt->financeEntity;
                    $context = $entity
                        ? FinanceOwnership::contextFor($entity)
                        : ($rt->category?->context ?? 'PRIBADI');

                    DB::transaction(function () use ($rt, $account, $context, $entity): void {
                        $transaction = Transaction::create([
                            'finance_entity_id' => $rt->finance_entity_id,
                            'finance_account_id' => $account->id,
                            'context' => $context,
                            'category_id' => $rt->category_id,
                            'amount' => $rt->amount,
                            'transaction_date' => $rt->next_due,
                            'description' => '[Otomatis] '.$rt->name,
                            'keterangan_detail' => $rt->description,
                            'detail_description' => $rt->description,
                        ]);

                        $rt->last_posted_at = $rt->next_due;
                        $rt->next_due = $rt->calculateNextDue($rt->next_due->copy()->addDay());
                        $rt->save();

                        app(AuditLogService::class)->recordCreated(
                            $transaction,
                            $entity,
                            AuditActorType::SYSTEM,
                        );
                    });

                    $created++;
                }
            });

        return $created;
    }

    /**
     * Resolve the account that a recurring rule may post to.
     * Returns null when posting must be skipped.
     */
    public function accountForPosting(RecurringTransaction $rt): ?FinanceAccount
    {
        $entity = $rt->financeEntity;

        if ($rt->finance_account_id) {
            $account = $rt->financeAccount ?? FinanceAccount::query()->find($rt->finance_account_id);

            if (
                $account instanceof FinanceAccount
                && $account->is_active
                && $entity
                && (int) $account->finance_entity_id === (int) $entity->id
            ) {
                return $account;
            }

            return null;
        }

        if (! $entity) {
            return null;
        }

        return app(FinanceAccountService::class)->ensureDefaultAccount($entity);
    }
}
