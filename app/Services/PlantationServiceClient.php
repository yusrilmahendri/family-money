<?php

namespace App\Services;

use App\Exceptions\PlantationServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlantationServiceClient
{
    private const MAX_RETRY_ATTEMPTS = 2;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createEntity(array $payload): array
    {
        return $this->data($this->send('POST', '/api/internal/plantation-entities', $payload, retry: false), 201);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateEntity(string $plantationEntityPublicId, array $payload): array
    {
        return $this->data($this->send(
            'PUT',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId),
            $payload,
            retry: true,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function activateEntity(string $plantationEntityPublicId): array
    {
        return $this->data($this->send(
            'POST',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/activate',
            [],
            retry: true,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivateEntity(string $plantationEntityPublicId): array
    {
        return $this->data($this->send(
            'POST',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/deactivate',
            [],
            retry: true,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAccessLinks(string $plantationEntityPublicId): array
    {
        $data = $this->data($this->send(
            'GET',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/access-links',
            [],
            retry: true,
        ));

        return array_is_list($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function issueAccessLink(string $plantationEntityPublicId, array $payload): array
    {
        return $this->data($this->send(
            'POST',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/access-links',
            $payload,
            retry: false,
        ), 201);
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeAccessLink(string $plantationEntityPublicId, int $tokenId): array
    {
        return $this->data($this->send(
            'POST',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/access-links/'.$tokenId.'/revoke',
            [],
            retry: true,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function activateAccessLink(string $plantationEntityPublicId, int $tokenId): array
    {
        return $this->data($this->send(
            'POST',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/access-links/'.$tokenId.'/activate',
            [],
            retry: true,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function regenerateAccessLink(string $plantationEntityPublicId, int $tokenId): array
    {
        return $this->data($this->send(
            'POST',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/access-links/'.$tokenId.'/regenerate',
            [],
            retry: false,
        ));
    }

    public function deleteAccessLink(string $plantationEntityPublicId, int $tokenId): void
    {
        $this->send(
            'DELETE',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/access-links/'.$tokenId,
            [],
            retry: true,
        );
    }

    /**
     * Idempotent upsert. Retries are safe because Plantation keys on budget_public_id.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upsertBudgetAllocation(string $budgetPublicId, array $payload): array
    {
        return $this->data($this->send(
            'PUT',
            '/api/internal/budget-allocations/'.$this->safeId($budgetPublicId),
            $payload,
            retry: true,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listHarvestSales(string $plantationEntityPublicId): array
    {
        $data = $this->data($this->send(
            'GET',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/harvest-sales',
            [],
            retry: true,
        ));

        return array_is_list($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload, bool $retry): Response
    {
        $baseUrl = rtrim((string) config('services.plantation.base_url'), '/');
        $token = (string) config('services.plantation.token');

        if ($baseUrl === '' || $token === '') {
            throw new PlantationServiceException('Plantation Service belum dikonfigurasi.');
        }

        $attempts = $retry ? self::MAX_RETRY_ATTEMPTS : 1;
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = $this->http($baseUrl, $token);
                $response = $method === 'GET'
                    ? $request->get($path)
                    : $request->send($method, $path, ['json' => $payload]);

                if ($retry && $response->serverError() && $attempt < $attempts) {
                    continue;
                }

                $this->assertSuccessful($response, $method, $path);

                return $response;
            } catch (ConnectionException $exception) {
                $lastError = $exception;

                if ($attempt >= $attempts) {
                    $this->logFailure($method, $path, 0);

                    throw new PlantationServiceException(
                        'Plantation Service sedang tidak dapat dihubungi.',
                        0,
                        $exception,
                    );
                }
            } catch (PlantationServiceException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $this->logFailure($method, $path, 0, $exception);

                throw new PlantationServiceException(
                    'Plantation Service sedang tidak dapat dihubungi.',
                    0,
                    $exception,
                );
            }
        }

        $this->logFailure($method, $path, 0, $lastError);

        throw new PlantationServiceException('Plantation Service sedang tidak dapat dihubungi.', 0, $lastError);
    }

    private function http(string $baseUrl, string $token): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.plantation.timeout', 15));
    }

    private function assertSuccessful(Response $response, string $method, string $path): void
    {
        if ($response->successful()) {
            return;
        }

        $this->logFailure($method, $path, $response->status());

        if ($response->serverError() || $response->status() === 0) {
            throw new PlantationServiceException(
                'Plantation Service sedang tidak dapat dihubungi.',
                $response->status(),
            );
        }

        throw new PlantationServiceException(
            $this->clientErrorMessage($response),
            $response->status(),
        );
    }

    private function clientErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json) && isset($json['message']) && is_string($json['message']) && $json['message'] !== '') {
            return $json['message'];
        }

        if (is_array($json) && isset($json['errors']) && is_array($json['errors'])) {
            foreach ($json['errors'] as $messages) {
                if (is_array($messages) && isset($messages[0]) && is_string($messages[0]) && $messages[0] !== '') {
                    return $messages[0];
                }
            }
        }

        return 'Permintaan ke Plantation Service gagal diproses.';
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response, int $expected = 200): array
    {
        if ($expected === 201 && $response->status() !== 201 && ! $response->successful()) {
            throw new PlantationServiceException('Plantation Service mengembalikan data tidak valid.', $response->status());
        }

        $json = $response->json();
        $data = is_array($json) ? ($json['data'] ?? $json) : [];

        if (! is_array($data)) {
            return [];
        }

        if (! array_is_list($data)) {
            return $this->sanitizePayload($data);
        }

        return array_values(array_map(function ($row) {
            if (! is_array($row)) {
                return $row;
            }

            unset($row['token'], $row['access_url'], $row['token_hash']);

            return $this->sanitizePayload($row);
        }, $data));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $blocked = ['token_hash', 'authorization', 'password', 'service_token'];
        $clean = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);

            if (in_array($normalized, $blocked, true) || str_contains($normalized, 'token_hash')) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitizePayload($value) : $value;
        }

        return $clean;
    }

    private function safeId(string $publicId): string
    {
        $publicId = trim($publicId);

        if ($publicId === '' || str_contains($publicId, '/') || str_contains($publicId, '..')) {
            throw new PlantationServiceException('Identitas Plantation tidak valid.');
        }

        return rawurlencode($publicId);
    }

    private function logFailure(string $method, string $path, int $status, ?Throwable $exception = null): void
    {
        Log::warning('plantation.http_failed', [
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'error' => $exception?->getMessage() ? 'connection_failed' : null,
        ]);
    }
}
