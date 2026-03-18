<?php

namespace App\Services;

class ShopCheckoutDomainException extends \RuntimeException
{
    private array $errors;
    private int $status;

    public function __construct(array $errors, int $status)
    {
        parent::__construct('Shop checkout domain exception.');
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
