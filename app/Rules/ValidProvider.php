<?php

declare(strict_types=1);

namespace App\Rules;

use Exception;
use Google_Client;
use Google_Service_Compute;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Sigmie\App\Core\Cloud\Providers\Google\Google;

class ValidProvider implements Rule
{
    private FilesystemAdapter $filesystem;

    public function __construct()
    {
        $this->filesystem = Storage::disk('local');
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

    private function validateGoogle($serviceAccount)
    {
        $filename = Str::random(40) . '.json';
        $path = "temp/{$filename}";

        $this->filesystem->put($path, $serviceAccount);

        $fullPath = $this->filesystem->path($path);

        try {
            $provider = new Google($fullPath, new Google_Service_Compute(new Google_Client()));

            $result = $provider->isActive();
        } catch (Exception $exception) {
            $result = false;
        }

        $this->filesystem->delete($path);

        return $result;
    }
}
