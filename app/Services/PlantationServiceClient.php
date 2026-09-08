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
        return $this->data($this->send(
            'POST',
            '/api/internal/plantation-entities',
            $payload,
            retry: false,
            operation: 'create_entity',
            context: $this->entityContext($payload),
        ), 201);
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
            operation: 'update_entity',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'activate_entity',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'deactivate_entity',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'list_access_links',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'issue_access_link',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'revoke_access_link',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'activate_access_link',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'regenerate_access_link',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
        ));
    }

    public function deleteAccessLink(string $plantationEntityPublicId, int $tokenId): void
    {
        $this->send(
            'DELETE',
            '/api/internal/plantation-entities/'.$this->safeId($plantationEntityPublicId).'/access-links/'.$tokenId,
            [],
            retry: true,
            operation: 'delete_access_link',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
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
            operation: 'budget_allocation.upsert',
            context: [
                'finance_entity_public_id' => $payload['finance_entity_public_id'] ?? null,
                'budget_public_id' => $budgetPublicId,
            ],
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
            operation: 'list_harvest_sales',
            context: ['plantation_entity_public_id' => $plantationEntityPublicId],
        ));

        return array_is_list($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    private function send(
        string $method,
        string $path,
        array $payload,
        bool $retry,
        string $operation = 'http',
        array $context = [],
    ): Response {
        $baseUrl = rtrim((string) config('services.plantation.base_url'), '/');
        $token = (string) config('services.plantation.token');
        $safeContext = $this->safeLogContext($context);

        if ($baseUrl === '' || $token === '') {
            throw PlantationServiceException::configuration();
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

                $this->assertSuccessful($response, $method, $path, $operation, $safeContext);

                return $response;
            } catch (ConnectionException $exception) {
                $lastError = $exception;

                if ($attempt >= $attempts) {
                    $this->logFailure($method, $path, 0, $exception, $operation, $safeContext);

                    throw PlantationServiceException::connection($exception, $operation, $safeContext);
                }
            } catch (PlantationServiceException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $this->logFailure(
                    $method,
                    $path,
                    0,
                    $exception,
                    $operation,
                    $safeContext,
                    errorType: PlantationServiceException::TYPE_CLIENT_ERROR,
                );

                throw PlantationServiceException::clientError($exception, $operation, $safeContext);
            }
        }

        $this->logFailure($method, $path, 0, $lastError, $operation, $safeContext);

        throw PlantationServiceException::connection($lastError, $operation, $safeContext);
    }

    private function http(string $baseUrl, string $token): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.plantation.timeout', 15));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function assertSuccessful(
        Response $response,
        string $method,
        string $path,
        string $operation,
        array $context,
    ): void {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $validationErrors = $status === 422 ? $this->extractValidationErrors($response) : [];

        $this->logFailure($method, $path, $status, null, $operation, $context, $validationErrors);

        throw PlantationServiceException::fromStatus($status, $validationErrors, $operation, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response, int $expected = 200): array
    {
        if ($expected === 201 && $response->status() !== 201 && ! $response->successful()) {
            throw PlantationServiceException::fromStatus($response->status());
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
            throw PlantationServiceException::invalidIdentity();
        }

        return rawurlencode($publicId);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function entityContext(array $payload): array
    {
        return [
            'finance_entity_public_id' => $payload['finance_entity_public_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function safeLogContext(array $context): array
    {
        $allowed = ['finance_entity_public_id', 'budget_public_id', 'plantation_entity_public_id'];
        $safe = [];

        foreach ($allowed as $key) {
            $value = $context[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    /**
     * @return array<string, list<string>>
     */
    private function extractValidationErrors(Response $response): array
    {
        $json = $response->json();
        $errors = is_array($json) && isset($json['errors']) && is_array($json['errors'])
            ? $json['errors']
            : [];
        $safe = [];

        foreach ($errors as $field => $messages) {
            $fieldName = (string) $field;
            $normalized = strtolower($fieldName);

            if (
                in_array($normalized, ['token', 'authorization', 'password', 'service_token', 'access_token'], true)
                || str_contains($normalized, 'token')
            ) {
                continue;
            }

            $list = is_array($messages) ? $messages : [$messages];
            $clean = [];

            foreach ($list as $message) {
                if (is_string($message) && $message !== '') {
                    $clean[] = $message;
                }
            }

            if ($clean !== []) {
                $safe[$fieldName] = $clean;
            }
        }

        return $safe;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, list<string>>  $validationErrors
     */
    private function logFailure(
        string $method,
        string $path,
        int $status,
        ?Throwable $exception = null,
        string $operation = 'http',
        array $context = [],
        array $validationErrors = [],
        ?string $errorType = null,
    ): void {
        $payload = [
            'operation' => $operation,
            'method' => $method,
            'path' => $path,
            'status' => $status,
            'error_type' => $errorType ?? PlantationServiceException::typeForStatus($status),
            ...$this->safeLogContext($context),
        ];

        if ($validationErrors !== []) {
            $payload['validation_errors'] = $validationErrors;
        }

        if ($exception !== null) {
            $payload['exception_class'] = $exception::class;
        }

        Log::warning('plantation.http_failed', $payload);
    }
}
