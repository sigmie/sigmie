<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

use Sigmie\Helpers\ImageHelper;
use Sigmie\Mappings\NewSemanticField;

class Image extends Text
{
    public function configure(): void
    {
        // Images are stored as text (URL, base64, or file path)
        $this->unstructuredText();
    }

    /**
     * Override to identify this as an image field for DocumentProcessor
     */
    public function embeddingsType(): string
    {
        return 'image';
    }

    /**
     * Validation for image fields
     */
    public function validate(string $key, mixed $value): array
    {
        // Handle multiple images (array)
        if (is_array($value)) {
            foreach ($value as $imageSource) {
                if (!is_string($imageSource)) {
                    return [false, sprintf('The field %s contains a non-string value in the array', $key)];
                }

                if ($this->isSemantic() && !$this->isValidImageSource($imageSource)) {
                    return [false, sprintf('The field %s contains an invalid image source: %s. Must be a URL, base64 string, or existing file path.', $key, $imageSource)];
                }
            }

            return [true, ''];
        }

        if (!is_string($value)) {
            return [false, sprintf('The field %s mapped as image must be a string (URL, base64, or file path)', $key)];
        }

        // Validate it's a valid image source when semantic is enabled
        if ($this->isSemantic() && !$this->isValidImageSource($value)) {
            return [false, sprintf('The field %s contains an invalid image source: %s. Must be a URL, base64 string, or existing file path.', $key, $value)];
        }

        return [true, ''];
    }

    /**
     * Check if a string is a valid image source
     */
    protected function isValidImageSource(string $value): bool
    {
        // Use the ImageHelper to validate
        if (ImageHelper::isUrl($value)) {
            return true;
        }
        if (ImageHelper::isBase64($value)) {
            return true;
        }
        return ImageHelper::isFilePath($value);
    }

    /**
     * Override semantic to mark as image field type for proper similarity handling
     */
    public function semantic(
        string $api,
        int $accuracy = 3,
        int $dimensions = 256,
    ): NewSemanticField {
        return $this->newSemantic(
            fn($semantic) => $semantic
                ->accuracy($accuracy, $dimensions)
                ->api($api)
                ->fieldType('image')
        );
    }

    /**
     * No text-based queries for image fields (unless they have semantic embeddings)
     */
    public function queries(array|string $queryString): array
    {
        // Image fields don't support text queries unless they have semantic embeddings
        return [];
    }
}
