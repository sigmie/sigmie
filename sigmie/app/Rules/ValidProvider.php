<?php

declare(strict_types=1);

namespace App\Rules;

use Exception;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

class ValidProvider implements Rule
{
    use \App\Helpers\InitializesGoogleFactory;

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        if ($value['id'] === 'google') {
            return $this->validateGoogle($value['creds']);
        }

        return false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Cloud provider is invalid.';
    }

    private function validateGoogle($serviceAccount)
    {
        $filename = Str::random(40) . '.json';
        $path = "temp/{$filename}";

        try {
            $provider = $this->newGoogleFactory($path, $serviceAccount)->create();
            $result = $provider->isActive();
        } catch (Exception $exception) {
            $result = false;
        }

        $this->googleFactoryCleanUp();

        return $result;
    }
}
