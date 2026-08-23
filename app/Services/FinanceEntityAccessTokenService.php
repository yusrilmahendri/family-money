<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceEntityAccessTokenService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @return array{0: FinanceEntityAccessToken, 1: string} token record and one-time plaintext
     */
    public function issue(FinanceEntity $entity, ?string $label = null, ?Carbon $expiresAt = null, bool $audit = true): array
    {
        $plain = FinanceEntityAccessToken::generatePlainToken();

        $token = $entity->accessTokens()->make([
            'label' => $label,
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);
        $token->token_hash = FinanceEntityAccessToken::hashToken($plain);
        $token->save();

        if ($audit) {
            $this->audit->recordCreated($token, $entity);
        }

        return [$token, $plain];
    }

    /**
     * Revoke the current record and issue a replacement so history stays auditable.
     *
     * @return array{0: FinanceEntityAccessToken, 1: string}
     */
    public function regenerate(FinanceEntityAccessToken $token): array
    {
        return DB::transaction(function () use ($token) {
            $old = $this->audit->snapshot($token);
            $token->update(['is_active' => false]);
            $entity = $token->financeEntity;

            [$replacement, $plain] = $this->issue(
                $entity,
                $token->label,
                $token->expires_at,
                false
            );

            $this->audit->record(
                $replacement,
                AuditAction::REGENERATE,
                $entity,
                $old + ['revoked_token_id' => $token->id],
                $this->audit->snapshot($replacement) + ['replacement_token_id' => $replacement->id],
            );

            return [$replacement, $plain];
        });
    }

    public function revoke(FinanceEntityAccessToken $token): FinanceEntityAccessToken
    {
        $old = $this->audit->snapshot($token);
        $token->update(['is_active' => false]);
        $this->audit->record($token->fresh(), AuditAction::REVOKE, $token->financeEntity, $old, $this->audit->snapshot($token->fresh()));

        return $token->fresh();
    }

    public function activate(FinanceEntityAccessToken $token): FinanceEntityAccessToken
    {
        $old = $this->audit->snapshot($token);
        $token->update(['is_active' => true]);
        $this->audit->record($token->fresh(), AuditAction::ACTIVATE, $token->financeEntity, $old, $this->audit->snapshot($token->fresh()));

        return $token->fresh();
    }

    /**
     * @param  array{label?: ?string, expires_at?: mixed}  $data
     */
    public function updateMeta(FinanceEntityAccessToken $token, array $data): FinanceEntityAccessToken
    {
        $old = $this->audit->snapshot($token);
        $token->update($data);

        $fresh = $token->fresh();
        $this->audit->recordUpdated($fresh, $old, $fresh->financeEntity);

        return $fresh;
    }

    public function findUsableByPlainToken(string $plainToken): ?FinanceEntityAccessToken
    {
        $token = FinanceEntityAccessToken::query()
            ->with('financeEntity')
            ->where('token_hash', FinanceEntityAccessToken::hashToken($plainToken))
            ->first();

        if (! $token instanceof FinanceEntityAccessToken || ! $token->isUsable()) {
            return null;
        }

        return $token;
    }

    public function markUsed(FinanceEntityAccessToken $token): void
    {
        $token->forceFill(['last_used_at' => now()])->save();
    }

    public function delete(FinanceEntityAccessToken $token): void
    {
        $entity = $token->financeEntity;
        $old = array_merge($this->audit->snapshot($token), [
            'finance_entity_public_id' => $entity?->public_id,
            'access_token_id' => (int) $token->id,
        ]);

        $this->audit->record(
            $token,
            AuditAction::ACCESS_LINK_DELETED,
            $entity,
            $old,
        );

        $token->delete();
    }
}
