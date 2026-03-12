<?php

namespace App\Core;

class Validator
{
    public static function required(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field][] = 'Field is required.';
            }
        }

        return $errors;
    }

    public static function email(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email format.';
    }

    public static function minLength(?string $value, int $min): ?string
    {
        if ($value === null) {
            return null;
        }
        return mb_strlen($value) >= $min ? null : "Minimum length is {$min}.";
    }
}
