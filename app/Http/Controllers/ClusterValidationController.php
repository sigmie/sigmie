<?php

namespace App\Http\Controllers;

use App\Cluster;
use Google_Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Sigmie\App\Core\Cloud\Providers\Google\Google;
use Google_Service_Compute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

class ClusterValidationController extends Controller
{
    public function name(string $name)
    {
        $valid = Cluster::firstWhere('name', $name) === null;

        return response()->json(['valid' => $valid]);
    }

    public function serviceaccount(Request $request)
    {
        /** @var  ParameterBag $data */
        $data = $request->json();
        $project = null;
        $filename = Str::random(40) . '.json';

        if ($data->has('project_id')) {
            $project = $data->get('project_id');
        }

        $googleClient = new Google_Client();
        $googleClient->useApplicationDefaultCredentials();
        $googleClient->addScope(Google_Service_Compute::COMPUTE);

        Storage::disk('local')->put($filename, json_encode($data->all()));

        $storagePath  = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        putenv("GOOGLE_APPLICATION_CREDENTIALS=" . $storagePath . $filename);

        $service = new Google_Service_Compute($googleClient);
        $provider = new Google($project, $service);

        $result = $provider->isActive();

        Storage::delete($filename);

        return response()->json(['valid' => $result]);
    }
}
