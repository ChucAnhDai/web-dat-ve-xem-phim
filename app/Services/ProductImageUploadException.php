<?php

namespace App\Services;

use RuntimeException;

class ProductImageUploadException extends RuntimeException
{
    private array $errors;

    public function __construct(array $errors, string $message = 'Product image upload is invalid.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
