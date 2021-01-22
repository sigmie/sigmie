<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class InArray implements Rule
{
    public function __construct(protected array $values)
    {
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        return in_array($value, $this->values);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        $values = implode(',', $this->values);

        return "The :attribute must contain one of the following values {$values}.";
    }
}
