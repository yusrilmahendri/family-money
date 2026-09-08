<?php

namespace App\Exceptions;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PlantationServiceException extends RuntimeException
{
    public const TYPE_CONNECTION = 'connection';

    public const TYPE_AUTHENTICATION = 'authentication';

    public const TYPE_VALIDATION = 'validation';

    public const TYPE_SERVER = 'server';

    public const TYPE_CLIENT = 'client';

    public const TYPE_CLIENT_ERROR = 'client_error';

    public const TYPE_CONFIGURATION = 'configuration';

    /**
     * @param  array<string, list<string>>  $validationErrors
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        ?Throwable $previous = null,
        public readonly string $errorType = self::TYPE_CONNECTION,
        public readonly array $validationErrors = [],
        public readonly array $context = [],
        public readonly ?string $operation = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function connection(?Throwable $previous = null, ?string $operation = null, array $context = []): self
    {
        return new self(
            self::messageForType(self::TYPE_CONNECTION),
            0,
            $previous,
            self::TYPE_CONNECTION,
            [],
            $context,
            $operation,
        );
    }

    /**
     * @param  array<string, list<string>>  $validationErrors
     * @param  array<string, mixed>  $context
     */
    public static function clientError(?Throwable $previous = null, ?string $operation = null, array $context = []): self
    {
        return new self(
            self::messageForType(self::TYPE_CLIENT_ERROR),
            0,
            $previous,
            self::TYPE_CLIENT_ERROR,
            [],
            $context,
            $operation,
        );
    }

    public static function fromStatus(
        int $status,
        array $validationErrors = [],
        ?string $operation = null,
        array $context = [],
        ?Throwable $previous = null,
    ): self {
        $type = self::typeForStatus($status);

        return new self(
            self::messageForType($type, $operation),
            $status,
            $previous,
            $type,
            $validationErrors,
            $context,
            $operation,
        );
    }

    public static function configuration(): self
    {
        return new self(
            'Plantation Service belum dikonfigurasi.',
            0,
            null,
            self::TYPE_CONFIGURATION,
        );
    }

    public static function invalidIdentity(): self
    {
        return new self(
            'Identitas Plantation tidak valid.',
            400,
            null,
            self::TYPE_CLIENT,
        );
    }

    public function isConnectionFailure(): bool
    {
        return $this->errorType === self::TYPE_CONNECTION;
    }

    public function isAuthenticationFailure(): bool
    {
        return $this->errorType === self::TYPE_AUTHENTICATION;
    }

    public function isValidationFailure(): bool
    {
        return $this->errorType === self::TYPE_VALIDATION;
    }

    public function isServerFailure(): bool
    {
        return $this->errorType === self::TYPE_SERVER;
    }

    public function isClientError(): bool
    {
        return $this->errorType === self::TYPE_CLIENT_ERROR;
    }

    public function isUnavailable(): bool
    {
        return $this->isConnectionFailure() || $this->isServerFailure();
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }

    public static function flashMessage(Throwable $exception, string $fallback): string
    {
        if ($exception instanceof self) {
            return $exception->userMessage();
        }

        if ($exception instanceof InvalidArgumentException) {
            return $exception->getMessage();
        }

        return $fallback;
    }

    public static function typeForStatus(int $status): string
    {
        return match (true) {
            $status === 0 => self::TYPE_CONNECTION,
            in_array($status, [401, 403], true) => self::TYPE_AUTHENTICATION,
            $status === 422 => self::TYPE_VALIDATION,
            $status >= 500 => self::TYPE_SERVER,
            $status >= 400 => self::TYPE_CLIENT,
            default => self::TYPE_CONNECTION,
        };
    }

    public static function messageForType(string $type, ?string $operation = null): string
    {
        if ($type === self::TYPE_VALIDATION) {
            return $operation === 'budget_allocation.upsert'
                ? 'Data anggaran ditolak oleh Plantation Service.'
                : 'Permintaan ke Plantation Service gagal diproses.';
        }

        return match ($type) {
            self::TYPE_CONNECTION => 'Plantation Service tidak dapat dihubungi.',
            self::TYPE_AUTHENTICATION => 'Autentikasi Plantation Service gagal.',
            self::TYPE_SERVER => 'Plantation Service mengalami kesalahan.',
            self::TYPE_CLIENT_ERROR => 'Terjadi kesalahan saat memproses integrasi Plantation Service.',
            default => 'Permintaan ke Plantation Service gagal diproses.',
        };
    }
}
