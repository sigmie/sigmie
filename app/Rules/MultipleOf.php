<?php

declare(strict_types=1);

namespace App\Rules;

use App\Traits\InitializesGoogleFactory;
use Illuminate\Contracts\Validation\Rule;

class MultipleOf implements Rule
{
    use InitializesGoogleFactory;

    protected int $of;

    protected array $or;

    public function __construct(int $of, array $or = [])
    {
        $this->of = $of;
        $this->or = $or;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        $value = (int) $value;

        return $value % $this->of == 0 || in_array($value, $this->or);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return "Value is not a multiple of {$this->of}";
    }
}
