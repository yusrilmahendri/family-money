<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'finance_entity_id',
        'actor_type',
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log): void {
            if (blank($log->created_at)) {
                $log->created_at = now();
            }
        });

        static::updating(function (): never {
            throw new LogicException('Audit logs are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit logs are immutable.');
        });
    }

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function resourceLabel(): string
    {
        return class_basename((string) $this->auditable_type).' #'.$this->auditable_id;
    }

    public function actorLabel(): string
    {
        $type = $this->actor_type instanceof AuditActorType
            ? $this->actor_type
            : AuditActorType::tryFrom((string) $this->actor_type);

        return match ($type) {
            AuditActorType::ADMIN => 'Admin #'.($this->actor_id ?? '—'),
            AuditActorType::PRIVATE_LINK => 'Private link #'.($this->actor_id ?? '—'),
            AuditActorType::SYSTEM => 'System',
            default => (string) ($this->actor_type?->value ?? $this->actor_type),
        };
    }

    public function changeSummary(): string
    {
        $action = $this->action instanceof AuditAction
            ? $this->action->value
            : (string) $this->action;

        $new = is_array($this->new_values) ? $this->new_values : [];
        $old = is_array($this->old_values) ? $this->old_values : [];

        if ($action === AuditAction::CREATE->value) {
            return $this->summarizeSnapshot($new, 'created');
        }

        if ($action === AuditAction::DELETE->value
            || $action === AuditAction::FINANCE_ENTITY_DELETED->value
            || $action === AuditAction::ACCESS_LINK_DELETED->value) {
            return $this->summarizeSnapshot($old, 'deleted');
        }

        if ($old === [] && $new === []) {
            return $action;
        }

        $keys = array_unique([...array_keys($old), ...array_keys($new)]);
        $parts = [];

        foreach ($keys as $key) {
            if (in_array($key, ['counterpart_entity_public_id', 'counterpart_entity_type'], true)) {
                continue;
            }

            $from = $old[$key] ?? '—';
            $to = $new[$key] ?? '—';

            if ($from === $to) {
                continue;
            }

            $parts[] = $key.': '.$this->stringify($from).' → '.$this->stringify($to);
        }

        if ($parts === [] && isset($new['amount'])) {
            $parts[] = 'amount: '.$this->stringify($new['amount']);
        }

        return $parts === [] ? $action : implode('; ', array_slice($parts, 0, 6));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function summarizeSnapshot(array $values, string $verb): string
    {
        $preferred = ['name', 'label', 'amount', 'source', 'party_name', 'is_active', 'is_default'];
        $parts = [];

        foreach ($preferred as $key) {
            if (array_key_exists($key, $values)) {
                $parts[] = $key.': '.$this->stringify($values[$key]);
            }
        }

        return $parts === [] ? $verb : $verb.' '.implode(', ', $parts);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }

        return (string) $value;
    }
}
