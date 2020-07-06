<?php

namespace App\Rules;

use Exception;
use Illuminate\Contracts\Validation\Rule;
use Google_Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Google_Service_Compute;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Filesystem;
use Symfony\Component\HttpFoundation\ParameterBag;
use Throwable;

class ValidProvider implements Rule
{
    private function validateGoogle($serviceAccount)
    {
        /** @var Filesystem $filesystem */
        $filesystem = Storage::disk('local');

        /** @var  AbstractAdapter $adapter */
        $adapter = $filesystem->getAdapter();

        $storagePath  = $adapter->getPathPrefix();
        $path = 'temp/' . Str::random(40) . '.json';
        $fullPath = $storagePath . $path;

        $filesystem->put($path, $serviceAccount);

        try {
            $provider = new Google($fullPath, new Google_Service_Compute(new Google_Client()));
        } catch (Exception $exception) {
            return false;
        }

        $result = $provider->isActive();

        $filesystem->delete($path);

        return $result;
    }
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
}
