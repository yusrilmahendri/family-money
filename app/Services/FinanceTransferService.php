<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceTransferService
{
    public function __construct(
        private readonly FinanceAccountBalanceService $balances,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array{source_account_id: int, destination_account_id: int, amount: float, transaction_date: mixed, description?: ?string}  $data
     */
    public function create(FinanceEntity $entity, array $data): FinanceTransfer
    {
        return DB::transaction(function () use ($entity, $data) {
            $sourceId = (int) $data['source_account_id'];
            $destinationId = (int) $data['destination_account_id'];
            $amount = (float) $data['amount'];

            if ($sourceId === $destinationId) {
                throw ValidationException::withMessages([
                    'destination_account_id' => 'Account tujuan harus berbeda dari account sumber.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah harus lebih dari 0.',
                ]);
            }

            $locked = FinanceAccount::query()
                ->where('finance_entity_id', $entity->id)
                ->whereIn('id', [$sourceId, $destinationId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $source = $locked->get($sourceId);
            $destination = $locked->get($destinationId);

            if (! $source instanceof FinanceAccount) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Account sumber tidak ditemukan pada entity ini.',
                ]);
            }

            if (! $destination instanceof FinanceAccount) {
                throw ValidationException::withMessages([
                    'destination_account_id' => 'Account tujuan tidak ditemukan pada entity ini.',
                ]);
            }

            if (! $source->is_active) {
                throw ValidationException::withMessages([
                    'source_account_id' => 'Account sumber harus aktif.',
                ]);
            }

            if (! $destination->is_active) {
                throw ValidationException::withMessages([
                    'destination_account_id' => 'Account tujuan harus aktif.',
                ]);
            }

            $sourceBalance = $this->balances->balance($source);

            if ($amount > $sourceBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah transfer melebihi saldo sumber.',
                ]);
            }

            $transfer = $entity->transfers()->create([
                'source_account_id' => $source->id,
                'destination_account_id' => $destination->id,
                'amount' => $amount,
                'transaction_date' => $data['transaction_date'],
                'description' => $data['description'] ?? null,
            ]);

            $this->audit->record($transfer, AuditAction::TRANSFER, $entity);

            return $transfer;
        });
    }

    /**
     * @return array{
     *     cross_entity_accounts: int,
     *     same_source_and_destination: int,
     *     invalid_account_relation: int,
     *     non_positive_amount: int,
     *     orphan_transfers: int
     * }
     */
    public function audit(): array
    {
        $validEntityIds = FinanceEntity::query()->pluck('id');
        $validAccountIds = FinanceAccount::query()->pluck('id');

        $orphan = FinanceTransfer::query()
            ->where(function ($query) use ($validEntityIds): void {
                $query->whereNull('finance_entity_id')
                    ->orWhereNotIn('finance_entity_id', $validEntityIds);
            })
            ->count();

        $invalidRelation = FinanceTransfer::query()
            ->where(function ($query) use ($validAccountIds): void {
                $query->whereNull('source_account_id')
                    ->orWhereNull('destination_account_id')
                    ->orWhereNotIn('source_account_id', $validAccountIds)
                    ->orWhereNotIn('destination_account_id', $validAccountIds);
            })
            ->count();

        $sameAccounts = FinanceTransfer::query()
            ->whereColumn('source_account_id', 'destination_account_id')
            ->count();

        $nonPositive = FinanceTransfer::query()
            ->where('amount', '<=', 0)
            ->count();

        $crossEntity = FinanceTransfer::query()
            ->leftJoin('finance_accounts as source_accounts', 'source_accounts.id', '=', 'finance_transfers.source_account_id')
            ->leftJoin('finance_accounts as destination_accounts', 'destination_accounts.id', '=', 'finance_transfers.destination_account_id')
            ->where(function ($query): void {
                $query->whereColumn('source_accounts.finance_entity_id', '!=', 'finance_transfers.finance_entity_id')
                    ->orWhereColumn('destination_accounts.finance_entity_id', '!=', 'finance_transfers.finance_entity_id')
                    ->orWhereColumn('source_accounts.finance_entity_id', '!=', 'destination_accounts.finance_entity_id');
            })
            ->count();

        return [
            'cross_entity_accounts' => $crossEntity,
            'same_source_and_destination' => $sameAccounts,
            'invalid_account_relation' => $invalidRelation,
            'non_positive_amount' => $nonPositive,
            'orphan_transfers' => $orphan,
        ];
    }

    public function hasCriticalInconsistencies(): bool
    {
        return array_sum($this->audit()) > 0;
    }
}
