<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\DebtPayment;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\FinanceTransfer;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FinanceEntityAccess;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;

class AuditLogService
{
    /**
     * Keys that must never appear in audit payloads.
     *
     * @var list<string>
     */
    public const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'remember',
        'token',
        'plain_token',
        'plaintext',
        'plain',
        'private_token',
        'token_hash',
        'access_token',
        'api_token',
        'session',
        'session_id',
        '_token',
        'csrf',
        'csrf_token',
    ];

    /**
     * Important fields per model. Anything else is ignored.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const WHITELISTS = [
        FinanceEntity::class => ['name', 'slug', 'type', 'description', 'is_active', 'public_id'],
        FinanceEntityAccessToken::class => ['id', 'label', 'is_active', 'expires_at', 'last_used_at', 'finance_entity_id'],
        FinanceAccount::class => [
            'name', 'type', 'bank_name', 'account_number', 'description',
            'opening_balance', 'is_active', 'is_default', 'finance_entity_id', 'public_id',
        ],
        Transaction::class => [
            'amount', 'transaction_date', 'description', 'category_id',
            'finance_account_id', 'finance_entity_id', 'context',
        ],
        Income::class => [
            'amount', 'income_date', 'source', 'description', 'category_id',
            'finance_account_id', 'finance_entity_id', 'context',
        ],
        Budget::class => ['amount', 'periode', 'description', 'category_id', 'finance_entity_id'],
        BudgetActivity::class => ['name', 'amount', 'activity_date', 'description', 'finance_account_id', 'budget_id'],
        DebtPayment::class => ['amount', 'paid_on', 'notes', 'finance_account_id', 'debt_id'],
        GoalContribution::class => ['amount', 'contributed_on', 'finance_account_id', 'savings_goal_id'],
        FinanceTransfer::class => [
            'source_account_id', 'destination_account_id', 'amount',
            'transaction_date', 'description', 'finance_entity_id',
        ],
        BusinessCapitalContribution::class => [
            'source_entity_id', 'source_account_id', 'business_entity_id',
            'destination_account_id', 'amount', 'transaction_date', 'description',
        ],
        OwnerWithdrawal::class => [
            'business_entity_id', 'source_account_id', 'family_entity_id',
            'destination_account_id', 'amount', 'transaction_date', 'description',
        ],
        ProfitDistribution::class => [
            'business_entity_id', 'source_account_id', 'family_entity_id',
            'destination_account_id', 'amount', 'distribution_date',
            'period_start', 'period_end', 'description',
        ],
        Receivable::class => [
            'party_name', 'description', 'principal_amount', 'remaining_balance',
            'receivable_date', 'due_date', 'status', 'finance_entity_id',
        ],
        ReceivablePayment::class => [
            'amount', 'payment_date', 'description', 'finance_account_id', 'receivable_id',
        ],
    ];

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        Model $auditable,
        AuditAction $action,
        ?FinanceEntity $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?AuditActorType $actorType = null,
    ): AuditLog {
        if (! Schema::hasTable((new AuditLog)->getTable())) {
            return new AuditLog;
        }

        $entity ??= $this->entityFor($auditable);
        [$resolvedType, $actorId] = $this->resolveActor($entity, $actorType);

        if ($action === AuditAction::CREATE) {
            $oldValues = null;
            $newValues = $this->sanitize($newValues ?? $this->snapshot($auditable));
        } elseif ($action === AuditAction::AI_CHAT_REQUESTED) {
            $oldValues = null;
            $newValues = $this->sanitize($newValues ?? []);
        } elseif ($action === AuditAction::DELETE || $action === AuditAction::FINANCE_ENTITY_DELETED || $action === AuditAction::ACCESS_LINK_DELETED) {
            $oldValues = $this->sanitize($oldValues ?? $this->snapshot($auditable));
            $newValues = null;
        } elseif ($oldValues !== null || $newValues !== null) {
            $oldValues = $this->sanitize($oldValues ?? []);
            $newValues = $this->sanitize($newValues ?? []);
            [$oldValues, $newValues] = $this->changedOnly($oldValues, $newValues);
        } else {
            $oldValues = null;
            $newValues = $this->sanitize($this->snapshot($auditable));
        }

        [$ip, $userAgent] = $this->requestMetadata($resolvedType);

        return AuditLog::query()->create([
            'finance_entity_id' => $entity?->id,
            'actor_type' => $resolvedType,
            'actor_id' => $actorId,
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function recordCreated(Model $auditable, ?FinanceEntity $entity = null, ?AuditActorType $actorType = null, array $extra = []): AuditLog
    {
        return $this->record(
            $auditable,
            AuditAction::CREATE,
            $entity,
            null,
            array_merge($this->snapshot($auditable), $extra),
            $actorType,
        );
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $extra
     */
    public function recordUpdated(Model $auditable, array $oldValues, ?FinanceEntity $entity = null, ?AuditActorType $actorType = null, array $extra = []): AuditLog
    {
        return $this->record(
            $auditable,
            AuditAction::UPDATE,
            $entity,
            $oldValues,
            array_merge($this->snapshot($auditable), $extra),
            $actorType,
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     */
    public function recordDeleted(Model $auditable, ?array $oldValues = null, ?FinanceEntity $entity = null, ?AuditActorType $actorType = null): AuditLog
    {
        return $this->record(
            $auditable,
            AuditAction::DELETE,
            $entity,
            $oldValues ?? $this->snapshot($auditable),
            null,
            $actorType,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Model $model): array
    {
        $fields = self::WHITELISTS[$model::class] ?? [];
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = $this->normalize($model->getAttribute($field));
        }

        if (array_key_exists('account_number', $values)) {
            $values['account_number'] = $this->maskAccountNumber($values['account_number']);
        }

        return $this->sanitize($values);
    }

    /**
     * @return array{0: AuditActorType, 1: int|null}
     */
    public function resolveActor(?FinanceEntity $entity = null, ?AuditActorType $forced = null): array
    {
        if ($forced === AuditActorType::SYSTEM) {
            return [AuditActorType::SYSTEM, null];
        }

        $request = $this->currentRequest();
        $user = $request?->user();

        if ($user instanceof User && $user->isAdmin() && $request?->routeIs('admin.*')) {
            return [AuditActorType::ADMIN, (int) $user->id];
        }

        $accessEntity = $entity ?? $this->entityFromRequest($request);

        if ($accessEntity instanceof FinanceEntity && FinanceEntityAccess::hasCapability($accessEntity)) {
            return [AuditActorType::PRIVATE_LINK, FinanceEntityAccess::tokenIdFor($accessEntity)];
        }

        if ($user instanceof User && $user->isAdmin()) {
            return [AuditActorType::ADMIN, (int) $user->id];
        }

        return [AuditActorType::SYSTEM, null];
    }

    public function entityFor(Model $model): ?FinanceEntity
    {
        if ($model instanceof FinanceEntity) {
            return $model;
        }

        if ($model instanceof BusinessCapitalContribution) {
            return $model->sourceEntity ?? FinanceEntity::query()->find($model->source_entity_id);
        }

        if ($model instanceof OwnerWithdrawal || $model instanceof ProfitDistribution) {
            return $model->businessEntity ?? FinanceEntity::query()->find($model->business_entity_id);
        }

        if ($model instanceof DebtPayment) {
            return $model->debt?->financeEntity;
        }

        if ($model instanceof GoalContribution) {
            return $model->savingsGoal?->financeEntity;
        }

        if ($model instanceof BudgetActivity) {
            return $model->budget?->financeEntity;
        }

        if ($model instanceof ReceivablePayment) {
            return $model->receivable?->financeEntity;
        }

        $entityId = $model->getAttribute('finance_entity_id');

        if ($entityId) {
            if ($model->relationLoaded('financeEntity') && $model->getRelation('financeEntity') instanceof FinanceEntity) {
                return $model->getRelation('financeEntity');
            }

            return FinanceEntity::query()->find($entityId);
        }

        return null;
    }

    /**
     * @return array{
     *     missing_action: int,
     *     invalid_actor_type: int,
     *     sensitive_fields: int,
     *     malformed_json: int,
     *     invalid_entity_reference: int
     * }
     */
    public function integrityCheck(): array
    {
        if (! Schema::hasTable((new AuditLog)->getTable())) {
            return [
                'missing_action' => 0,
                'invalid_actor_type' => 0,
                'sensitive_fields' => 0,
                'malformed_json' => 0,
                'invalid_entity_reference' => 0,
            ];
        }

        $validEntityIds = FinanceEntity::query()->pluck('id');
        $missingAction = 0;
        $invalidActor = 0;
        $sensitive = 0;
        $malformed = 0;
        $invalidEntity = 0;

        foreach (DB::table('audit_logs')->orderBy('id')->get() as $row) {
            if (blank($row->action) || ! AuditAction::isValid((string) $row->action)) {
                $missingAction++;
            }

            if (! AuditActorType::isValid((string) $row->actor_type)) {
                $invalidActor++;
            }

            foreach (['old_values', 'new_values'] as $column) {
                $raw = $row->{$column};

                if ($raw === null || $raw === '') {
                    continue;
                }

                if (is_string($raw)) {
                    try {
                        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        $malformed++;

                        continue;
                    }
                } elseif (is_array($raw)) {
                    $decoded = $raw;
                } else {
                    $malformed++;

                    continue;
                }

                if ($this->containsSensitiveKey($decoded)) {
                    $sensitive++;
                }
            }

            if ($row->finance_entity_id !== null && ! $validEntityIds->contains((int) $row->finance_entity_id)) {
                $invalidEntity++;
            }
        }

        return [
            'missing_action' => $missingAction,
            'invalid_actor_type' => $invalidActor,
            'sensitive_fields' => $sensitive,
            'malformed_json' => $malformed,
            'invalid_entity_reference' => $invalidEntity,
        ];
    }

    public function hasIntegrityIssues(): bool
    {
        return array_sum($this->integrityCheck()) > 0;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function sanitize(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            }

            $clean[$key] = $this->normalize($value);
        }

        return $clean;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function requestMetadata(AuditActorType $actorType): array
    {
        if ($actorType === AuditActorType::SYSTEM && app()->runningInConsole()) {
            return [null, null];
        }

        $request = $this->currentRequest();

        if (! $request instanceof Request) {
            return [null, null];
        }

        $userAgent = $request->userAgent();

        return [
            $request->ip(),
            is_string($userAgent) ? mb_substr($userAgent, 0, 512) : null,
        ];
    }

    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function entityFromRequest(?Request $request): ?FinanceEntity
    {
        $entity = $request?->route('financeEntity');

        return $entity instanceof FinanceEntity ? $entity : null;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function changedOnly(array $old, array $new): array
    {
        $oldChanged = [];
        $newChanged = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            if ($before != $after) {
                $oldChanged[$key] = $before;
                $newChanged[$key] = $after;
            }
        }

        return [$oldChanged, $newChanged];
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return $value;
    }

    private function maskAccountNumber(mixed $number): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }

        $digits = preg_replace('/\s+/', '', (string) $number) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['password', 'token_hash', 'remember_token', 'csrf', 'session'] as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function containsSensitiveKey(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach ($payload as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                return true;
            }

            if (is_array($value) && $this->containsSensitiveKey($value)) {
                return true;
            }
        }

        return false;
    }
}
