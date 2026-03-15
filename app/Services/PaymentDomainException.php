<?php

namespace App\Services;

class PaymentDomainException extends \RuntimeException
{
    private array $errors;
    private int $status;
    private ?string $ipnCode;
    private ?string $ipnMessage;

    public function __construct(array $errors, int $status, ?string $ipnCode = null, ?string $ipnMessage = null)
    {
        parent::__construct('Payment domain exception.');
        $this->errors = $errors;
        $this->status = $status;
        $this->ipnCode = $ipnCode;
        $this->ipnMessage = $ipnMessage;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function ipnCode(): ?string
    {
        return $this->ipnCode;
    }

    public function ipnMessage(): ?string
    {
        return $this->ipnMessage;
    }
}
