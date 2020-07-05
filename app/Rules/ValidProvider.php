<?php

namespace App\Rules;

use Exception;
use Illuminate\Contracts\Validation\Rule;
use Google_Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Google_Service_Compute;
use Symfony\Component\HttpFoundation\ParameterBag;
use Throwable;

class ValidProvider implements Rule
{
    private function validateGoogle($serviceAccount)
    {
        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();
        $path = 'temp/' . Str::random(40) . '.json';
        $fullPath = $storagePath . $path;

        Storage::disk('local')->put($path, $serviceAccount);

        try {
            $provider = new Google($fullPath, new Google_Service_Compute(new Google_Client()));
        } catch (Exception $exception) {
            return false;
        }

        $result = $provider->isActive();

        Storage::delete($path);

        return $result;
    }
    /**
     * Determine if the validation rule passes.
     */
    public function passes(string $attribute, $value): bool
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
}
