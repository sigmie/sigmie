<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Google_Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Google_Service_Compute;
use Symfony\Component\HttpFoundation\ParameterBag;


class ValidProvider implements Rule
{
    private function validateGoogle($serviceAccount)
    {
        $value = json_decode($serviceAccount, true);

        $filename = Str::random(40) . '.json';

        if (isset($value['project_id']) === false) {
            return false;
        }

        $project = $value['project_id'];

        $googleClient = new Google_Client();
        $googleClient->useApplicationDefaultCredentials();
        $googleClient->addScope(Google_Service_Compute::COMPUTE);

        Storage::disk('local')->put($filename, json_encode($value));

        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        putenv("GOOGLE_APPLICATION_CREDENTIALS=" . $storagePath . $filename);

        $service = new Google_Service_Compute($googleClient);
        $provider = new Google($project, $service);

        $result = $provider->isActive();

        Storage::delete($filename);

        return $result;
    }
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
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
     *
     * @return string
     */
    public function message()
    {
        return 'Cloud provider is invalid.';
    }
}
