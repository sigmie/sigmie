<?php

declare(strict_types=1);

namespace Sigmie\Helpers;

use Exception;

class ImageHelper
{
    /**
     * Maximum dimension for resized images
     */
    private const MAX_DIMENSION = 224; // Standard CLIP input size

    /**
     * Fetch image content from various sources (URL, base64, file path)
     */
    public static function fetchImageContent(string $source): string
    {
        // Check if it's a base64 image
        if (self::isBase64($source)) {
            return self::extractBase64Content($source);
        }

        // Check if it's a URL
        if (self::isUrl($source)) {
            return self::fetchFromUrl($source);
        }

        // Check if it's a file path
        if (self::isFilePath($source)) {
            return self::fetchFromFile($source);
        }

        throw new Exception(sprintf('Invalid image source: %s. Must be a URL, base64 string, or file path.', $source));
    }

    /**
     * Resize image to maximum dimension of 1024px while maintaining aspect ratio
     */
    public static function resizeImage(string $imageContent, int $maxDimension = self::MAX_DIMENSION): string
    {
        // Create image from string
        $image = @imagecreatefromstring($imageContent);
        if ($image === false) {
            throw new Exception('Failed to create image from content');
        }

        // Get current dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate new dimensions maintaining aspect ratio
        try {
            if ($width > $maxDimension || $height > $maxDimension) {
                // A 1px side scaled by (int) becomes 0. GD rejects a 0 dimension.
                if ($width > $height) {
                    $newWidth = $maxDimension;
                    $newHeight = max(1, (int) ($height * ($maxDimension / $width)));
                } else {
                    $newHeight = $maxDimension;
                    $newWidth = max(1, (int) ($width * ($maxDimension / $height)));
                }

                // Create new image with new dimensions
                $resized = imagecreatetruecolor($newWidth, $newHeight);

                // Preserve transparency for PNG/GIF images
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

                // Resize the image
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                $imageContent = self::jpegBytes($resized);
            } else {
                $imageContent = self::jpegBytes($image);
            }
        } finally {
            if (isset($resized) && $resized instanceof \GdImage) {
                imagedestroy($resized);
            }

            imagedestroy($image);
        }

        return $imageContent;
    }

    /**
     * JPEG refuses a side above 65500 and then writes nothing.
     */
    private static function jpegBytes(\GdImage $image): string
    {
        ob_start();
        $wrote = @imagejpeg($image, null, 60);
        $bytes = ob_get_clean();

        if ($wrote !== true || ! is_string($bytes) || $bytes === '') {
            throw new Exception('Failed to create image from content');
        }

        return $bytes;
    }

    /**
     * Convert image content to base64
     */
    public static function toBase64(string $imageContent): string
    {
        return base64_encode($imageContent);
    }

    /**
     * Check if string is a URL
     */
    public static function isUrl(string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if string is base64 encoded image
     */
    public static function isBase64(string $source): bool
    {
        return self::base64ImageMimeType($source) !== null;
    }

    public static function base64ImageUri(string $source): ?string
    {
        $mime = self::base64ImageMimeType($source);
        if ($mime === null) {
            return null;
        }

        $header = strncasecmp($source, 'data:', 5) === 0
            ? substr($source, 0, strpos($source, ',') + 1)
            : 'data:'.$mime.';base64,';

        return $header.self::base64ImagePayload($source);
    }

    public static function base64ImageMimeType(string $source): ?string
    {
        $payload = self::base64ImagePayload($source);

        if ($payload === null) {
            return null;
        }

        $decoded = base64_decode($payload, true);

        if ($decoded === false || $decoded === '') {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $decoded);
        finfo_close($finfo);

        return is_string($mimeType) && str_starts_with($mimeType, 'image/') ? $mimeType : null;
    }

    /**
     * Check if string is a file path
     */
    public static function isFilePath(string $source): bool
    {
        // Check if it's not a URL and not base64
        if (! self::isUrl($source) && ! self::isBase64($source)) {
            // Check if file exists
            return file_exists($source) && is_file($source);
        }

        return false;
    }

    /**
     * Extract base64 content from data URL
     */
    private static function extractBase64Content(string $source): string
    {
        $decoded = base64_decode(self::base64ImagePayload($source) ?? '', true);

        return $decoded === false ? '' : $decoded;
    }

    private static function base64ImagePayload(string $source): ?string
    {
        if (preg_match('/^data:image\/[a-z0-9.+-]+(?:;[^;,]*)*;base64,(.*)$/is', $source, $matches) === 1) {
            $source = rawurldecode($matches[1]);
        }

        $source = preg_replace('/\s+/', '', $source) ?? '';

        if ($source === '' || preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $source) !== 1) {
            return null;
        }

        return $source;
    }

    /**
     * Fetch image from URL
     */
    private static function fetchFromUrl(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Sigmie/1.0',
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new Exception('Failed to fetch image from URL: '.$url);
        }

        // Verify it's actually an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = @finfo_buffer($finfo, $content);
        finfo_close($finfo);

        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'image/')) {
            throw new Exception('URL does not point to a valid image: '.$url);
        }

        return $content;
    }

    /**
     * Fetch image from file path
     */
    private static function fetchFromFile(string $path): string
    {
        if (! file_exists($path)) {
            throw new Exception('File does not exist: '.$path);
        }

        // @codeCoverageIgnoreStart
        if (! is_readable($path)) {
            throw new Exception('File is not readable: '.$path);
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            throw new Exception('Failed to read file: '.$path);
        }

        // @codeCoverageIgnoreEnd

        // Verify it's actually an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = @finfo_file($finfo, $path);
        finfo_close($finfo);

        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'image/')) {
            throw new Exception('File is not a valid image: '.$path);
        }

        return $content;
    }

    /**
     * Process an image for embedding: fetch, resize, and convert to base64
     */
    public static function processImageForEmbedding(string $source, int $maxDimension = self::MAX_DIMENSION): string
    {
        $imageContent = self::fetchImageContent($source);
        $resizedContent = self::resizeImage($imageContent, $maxDimension);
        $base64 = self::toBase64($resizedContent);

        // Ensure the base64 string isn't too large for the API
        // @codeCoverageIgnoreStart
        if (strlen($base64) > 100000) {
            // If still too large, resize even smaller
            $resizedContent = self::resizeImage($imageContent, 128);
            $base64 = self::toBase64($resizedContent);
        }

        // @codeCoverageIgnoreEnd

        return $base64;
    }
}
