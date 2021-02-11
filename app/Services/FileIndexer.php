<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;

class FileIndexer extends BaseIndexer
{
    use IndexActions;

    protected FileType $type;

    public function index()
    {
        $filesystem = Storage::disk('local');
        $filename = Str::random(40);
        $path = "temp/{$filename}";
        $fullPath = $filesystem->path($path);

        copy($this->type->location, $fullPath);

        $contents = file_get_contents($fullPath);
        $json = json_decode($contents, true);

        $timestamp = Carbon::now()->format('YmdHis');

        $index = new Index($timestamp);

        $this->createIndex($index);

        $docs = [];
        foreach ($json as $doc) {
            $docs[] = new Document($doc);
        }

        $index->addAsyncDocuments($docs);
    }
}
