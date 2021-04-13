<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IndexingException;
use App\Models\FileType;
use ErrorException;
use Illuminate\Support\Facades\Storage;
use Sigmie\Base\Documents\Document;

use function App\Helpers\is_json;
use function App\Helpers\temp_file_path;

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
            throw IndexingException::copy($fetchLocation, $this->type->plan);
        }

        if (filesize($tempPath) > 1073741824) { // 1 GB
            throw IndexingException::filesize($fetchLocation, '1', $this->type->plan);
        }

        $contents = file_get_contents($tempPath);

        if (!is_json($contents)) {
            throw IndexingException::json($fetchLocation, $this->type->plan);
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
