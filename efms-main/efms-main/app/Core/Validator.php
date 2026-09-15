<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validator
 *
 * Small Laravel-style rule syntax: 'field' => 'required|numeric|max:255'
 * Enough for a financial app's form/API validation without a dependency.
 * Add new rules by adding a case to applyRule().
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    public function __construct(private array $data, private array $rules)
    {
        $this->run();
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $name, $param);
            }

            if (!isset($this->errors[$field]) && $value !== null) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param): void
    {
        $fail = fn (string $message) => $this->errors[$field][] = $message;

        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    $fail("$field is required.");
                }
                break;
            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $fail("$field must be numeric.");
                }
                break;
            case 'integer':
                if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $fail("$field must be an integer.");
                }
                break;
            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $fail("$field must be a valid email address.");
                }
                break;
            case 'max':
                if ($value !== null && strlen((string) $value) > (int) $param) {
                    $fail("$field must not exceed $param characters.");
                }
                break;
            case 'min':
                if ($value !== null && strlen((string) $value) < (int) $param) {
                    $fail("$field must be at least $param characters.");
                }
                break;
            case 'in':
                $allowed = explode(',', (string) $param);
                if ($value !== null && !in_array((string) $value, $allowed, true)) {
                    $fail("$field must be one of: $param.");
                }
                break;
            case 'date':
                if ($value !== null && $value !== '' && strtotime((string) $value) === false) {
                    $fail("$field must be a valid date.");
                }
                break;
            case 'decimal':
                if ($value !== null && $value !== '' && !preg_match('/^-?\d+(\.\d{1,4})?$/', (string) $value)) {
                    $fail("$field must be a valid decimal amount.");
                }
                break;
        }
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }
}
