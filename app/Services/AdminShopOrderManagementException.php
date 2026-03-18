<?php

namespace App\Services;

use RuntimeException;

class AdminShopOrderManagementException extends RuntimeException
{
    private array $errors;
    private int $status;

    public function __construct(array $errors, int $status = 409)
    {
        parent::__construct('Admin shop order management request failed.');
        $this->errors = $errors;
        $this->status = $status;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }
}
