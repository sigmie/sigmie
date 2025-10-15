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

        throw new Exception("Invalid image source: {$source}. Must be a URL, base64 string, or file path.");
    }

    /**
     * Resize image to maximum dimension of 1024px while maintaining aspect ratio
     */
    public static function resizeImage(string $imageContent, int $maxDimension = self::MAX_DIMENSION): string
    {
        // Create image from string
        $image = @imagecreatefromstring($imageContent);
        if ($image === false) {
            throw new Exception("Failed to create image from content");
        }

        // Get current dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate new dimensions maintaining aspect ratio
        if ($width > $maxDimension || $height > $maxDimension) {
            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = (int) ($height * ($maxDimension / $width));
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int) ($width * ($maxDimension / $height));
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

            // Convert to JPEG format for consistency with lower quality for smaller size
            ob_start();
            imagejpeg($resized, null, 60); // Reduced quality for smaller base64
            $imageContent = ob_get_clean();

            imagedestroy($resized);
        } else {
            // Convert to JPEG even if not resizing for consistency
            ob_start();
            imagejpeg($image, null, 60); // Reduced quality for smaller base64
            $imageContent = ob_get_clean();
        }

        imagedestroy($image);

        return $imageContent;
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
        // Check for data URL scheme
        if (preg_match('/^data:image\/[a-z]+;base64,/', $source)) {
            return true;
        }

        // Check if it's pure base64 (no data URL prefix)
        if (preg_match('/^[a-zA-Z0-9\/+]+={0,2}$/', $source) && strlen($source) > 100) {
            // Try to decode and check if it's valid image data
            $decoded = @base64_decode($source, true);
            if ($decoded !== false) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_buffer($finfo, $decoded);
                finfo_close($finfo);
                return strpos($mimeType, 'image/') === 0;
            }
        }

        return false;
    }

    /**
     * Check if string is a file path
     */
    public static function isFilePath(string $source): bool
    {
        // Check if it's not a URL and not base64
        if (!self::isUrl($source) && !self::isBase64($source)) {
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
        // If it's a data URL, extract the base64 part
        if (preg_match('/^data:image\/[a-z]+;base64,(.+)$/i', $source, $matches)) {
            return base64_decode($matches[1]);
        }

        // If it's pure base64, decode it
        return base64_decode($source);
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
            throw new Exception("Failed to fetch image from URL: {$url}");
        }

        // Verify it's actually an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);

        if (strpos($mimeType, 'image/') !== 0) {
            throw new Exception("URL does not point to a valid image: {$url}");
        }

        return $content;
    }

    /**
     * Fetch image from file path
     */
    private static function fetchFromFile(string $path): string
    {
        if (!file_exists($path)) {
            throw new Exception("File does not exist: {$path}");
        }

        if (!is_readable($path)) {
            throw new Exception("File is not readable: {$path}");
        }

        $content = @file_get_contents($path);

        if ($content === false) {
            throw new Exception("Failed to read file: {$path}");
        }

        // Verify it's actually an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        if (strpos($mimeType, 'image/') !== 0) {
            throw new Exception("File is not a valid image: {$path}");
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
        if (strlen($base64) > 100000) {
            // If still too large, resize even smaller
            $resizedContent = self::resizeImage($imageContent, 128);
            $base64 = self::toBase64($resizedContent);
        }

        return $base64;
    }
}