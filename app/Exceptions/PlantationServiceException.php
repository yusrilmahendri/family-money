<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PlantationServiceException extends RuntimeException
{
    public function __construct(
        string $message = 'Plantation Service sedang tidak dapat dihubungi.',
        public readonly int $status = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isUnavailable(): bool
    {
        return $this->status === 0 || $this->status >= 500;
    }
}
