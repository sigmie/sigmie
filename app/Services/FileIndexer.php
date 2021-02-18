<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IndexingException;
use App\Models\FileType;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Sigmie\Base\Documents\Document;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Index;

class FileIndexer extends BaseIndexer
{
    protected FileType $type;

    protected function __invoke()
    {
        $tempPath = temp_file_path();
        $fetchLocation = $this->type->location;

        copy($fetchLocation, $tempPath);

        if (filesize($tempPath) > 1073741824) // 1 GB
        {
            throw new IndexingException('File fetched from ' . $fetchLocation . ' size bigger than 1GB.', $this->type->plan);
        }

        $contents = file_get_contents($tempPath);

        if (is_json($contents) === false) {
            throw new IndexingException('File fetched from ' . $fetchLocation . ' isn\'t a valid JSON.', $this->type->plan);
        }

        $json = json_decode($contents, true);

        $docs = [];

        foreach ($json as $doc) {
            $docs[] = new Document($doc);
        }

        $this->index->addAsyncDocuments($docs);

        Storage::delete($tempPath);
    }
}
