<?php

namespace App\Services;

use App\Exceptions\PlantationServiceException;
use App\Models\FinanceEntity;
use App\Support\FinanceEntityAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ApplicationPortalService
{
    public const HANDOFF_TTL_MINUTES = 15;

    public function __construct(
        private readonly PlantationIntegrationService $integrations,
    ) {}

    /**
     * Destinations the current private session may open.
     * Authorization is re-checked from the live token capability.
     *
     * @return Collection<int, array{
     *     type: string,
     *     title: string,
     *     subtitle: string,
     *     description: string,
     *     badge: string,
     *     target_url: string,
     *     method: string,
     *     icon: string,
     *     aria_label: string
     * }>
     */
    public function destinations(): Collection
    {
        $cards = collect();

        foreach (FinanceEntityAccess::authorizedEntities() as $entity) {
            if ($entity->isFamily()) {
                $cards->push($this->financeCard(
                    $entity,
                    'finance_personal',
                    'Keuangan Keluarga',
                    'Kelola pemasukan, pengeluaran, tabungan, hutang, dan kebutuhan keluarga.',
                    'fa-home',
                ));

                continue;
            }

            if (! $entity->isBusiness()) {
                continue;
            }

            $cards->push($this->financeCard(
                $entity,
                'finance_business',
                'Keuangan Usaha',
                'Pantau kas, anggaran, biaya operasional, modal, laba, dan laporan usaha.',
                'fa-briefcase',
            ));

            if ($entity->hasActivePlantationIntegration()) {
                $cards->push($this->plantationCard($entity));
            }
        }

        return $cards->values();
    }

    public function hasMultipleDestinations(): bool
    {
        return $this->destinations()->count() > 1;
    }

    public function accessLabel(): ?string
    {
        if (FinanceEntityAccess::authorizedEntities()->isEmpty()) {
            return null;
        }

        return 'Sesi aktif';
    }

    /**
     * Issue a short-lived Plantation access URL for this request only.
     * The URL is never stored on Finance.
     */
    public function issuePlantationHandoffUrl(FinanceEntity $entity): string
    {
        if (! FinanceEntityAccess::isAuthorized($entity) || ! $entity->hasActivePlantationIntegration()) {
            throw new InvalidArgumentException('Management Kebun tidak tersedia untuk sesi ini.');
        }

        $issued = $this->integrations->issueAccessLink($entity, [
            'label' => 'Finance portal '.Str::lower((string) Str::ulid()),
            'expires_at' => now()->addMinutes(self::HANDOFF_TTL_MINUTES)->toDateTimeString(),
        ]);

        $url = is_string($issued['access_url'] ?? null) ? $issued['access_url'] : '';
        $safe = $this->allowlistedPlantationUrl($url);

        if ($safe === null) {
            Log::warning('portal.plantation_handoff_rejected_url', [
                'finance_entity_public_id' => $entity->public_id,
            ]);

            throw new PlantationServiceException('Management Kebun mengembalikan tautan yang tidak valid.');
        }

        return $safe;
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     subtitle: string,
     *     description: string,
     *     badge: string,
     *     target_url: string,
     *     method: string,
     *     icon: string,
     *     aria_label: string
     * }
     */
    private function financeCard(
        FinanceEntity $entity,
        string $type,
        string $badge,
        string $description,
        string $icon,
    ): array {
        return $this->card(
            $entity,
            $type,
            $entity->name,
            $badge,
            $description,
            $icon,
            route('entity.dashboard', $entity),
            'GET',
            null,
        );
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     subtitle: string,
     *     description: string,
     *     badge: string,
     *     target_url: string,
     *     method: string,
     *     icon: string,
     *     aria_label: string
     * }
     */
    private function plantationCard(FinanceEntity $entity): array
    {
        return $this->card(
            $entity,
            'plantation',
            'Management Kebun',
            'Management Kebun',
            'Kelola pekerjaan kebun, pekerja, persediaan, panen, dan penjualan.',
            'fa-leaf',
            route('portal.plantation.handoff', $entity),
            'POST',
            $entity->name,
        );
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     subtitle: string,
     *     description: string,
     *     badge: string,
     *     target_url: string,
     *     method: string,
     *     icon: string,
     *     aria_label: string
     * }
     */
    private function card(
        FinanceEntity $entity,
        string $type,
        string $title,
        string $badge,
        string $description,
        string $icon,
        string $targetUrl,
        string $method,
        ?string $subtitle,
    ): array {
        $entityName = $entity->name;

        return [
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle ?? '',
            'description' => $description,
            'badge' => $badge,
            'target_url' => $targetUrl,
            'method' => $method,
            'icon' => $icon,
            'aria_label' => 'Buka '.$badge.' untuk '.$entityName,
        ];
    }

    private function allowlistedPlantationUrl(string $url): ?string
    {
        if ($url === '' || strlen($url) > 2048) {
            return null;
        }

        $base = rtrim((string) config('services.plantation.base_url'), '/');

        if ($base === '') {
            return null;
        }

        $parsed = parse_url($url);
        $allowed = parse_url($base);

        if (! is_array($parsed) || ! is_array($allowed)) {
            return null;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $allowedScheme = strtolower((string) ($allowed['scheme'] ?? ''));
        $allowedHost = strtolower((string) ($allowed['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $allowedHost === '') {
            return null;
        }

        if ($scheme !== $allowedScheme || $host !== $allowedHost) {
            return null;
        }

        $allowedPort = $allowed['port'] ?? ($allowedScheme === 'https' ? 443 : 80);
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        if ((int) $port !== (int) $allowedPort) {
            return null;
        }

        $userInfo = ($parsed['user'] ?? '').($parsed['pass'] ?? '');

        if ($userInfo !== '') {
            return null;
        }

        return $url;
    }
}
