<?php

declare(strict_types=1);

namespace Sigmie\Mappings\Types;

class Image extends Text
{
    protected bool $multiple = false;

    public function configure(): void
    {
        // Images are stored as text (URL, base64, or file path)
        $this->unstructuredText();
    }

    /**
     * Mark this field as supporting multiple images (array)
     */
    public function multiple(): static
    {
        $this->multiple = true;

        return $this;
    }

    /**
     * Check if this field supports multiple images
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
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
        if ($this->multiple) {
            if (!is_array($value)) {
                return [false, "The field {$key} mapped as multiple images must be an array"];
            }

            foreach ($value as $item) {
                if (!is_string($item)) {
                    return [false, "Each item in the field {$key} must be a string (URL, base64, or file path)"];
                }
                // Validate each item is a valid image source when semantic is enabled
                if ($this->isSemantic() && !$this->isValidImageSource($item)) {
                    return [false, "The field {$key} contains an invalid image source: {$item}. Must be a URL, base64 string, or existing file path."];
                }
            }

            return [true, ''];
        }

        if (!is_string($value)) {
            return [false, "The field {$key} mapped as image must be a string (URL, base64, or file path)"];
        }

        // Validate it's a valid image source when semantic is enabled
        if ($this->isSemantic() && !$this->isValidImageSource($value)) {
            return [false, "The field {$key} contains an invalid image source: {$value}. Must be a URL, base64 string, or existing file path."];
        }

        return [true, ''];
    }

    /**
     * Check if a string is a valid image source
     */
    protected function isValidImageSource(string $value): bool
    {
        // Use the ImageHelper to validate
        return \Sigmie\Helpers\ImageHelper::isUrl($value) ||
               \Sigmie\Helpers\ImageHelper::isBase64($value) ||
               \Sigmie\Helpers\ImageHelper::isFilePath($value);
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