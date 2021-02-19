<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IndexingException;
use App\Models\FileType;
use ErrorException;
use Illuminate\Support\Facades\Storage;
use Sigmie\Base\Documents\Document;

class FileIndexer extends BaseIndexer
{
    protected FileType $type;

    public function __invoke()
    {
        $tempPath = temp_file_path();
        $fetchLocation = $this->type->location;

        try {
            copy($fetchLocation, $tempPath);
        } catch (ErrorException $e) {
            throw new IndexingException($e->getMessage(), $this->type->plan);
        }

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
