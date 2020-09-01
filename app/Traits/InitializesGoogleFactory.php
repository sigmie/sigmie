<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Sigmie\App\Core\GoogleFactory;

trait InitializesGoogleFactory
{
    private $serviceAccountPath;

    private $filesystem;

    public function newGoogleFactory($serviceAccountPath, $serviceAccountContent): GoogleFactory
    {
        $this->filesystem = Storage::disk('local');

        $this->serviceAccountPath  = $serviceAccountPath;

        $this->filesystem->put($serviceAccountPath, $serviceAccountContent);

        $fullPath = $this->filesystem->path($serviceAccountPath);

        return new GoogleFactory($fullPath);
    }

    public function googleFactoryCleanUp()
    {
        $this->filesystem->delete($this->serviceAccountPath);
    }
}
