<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use ReflectionProperty;
use Sigmie\AI\APIs\InfinityClipApi;
use Sigmie\AI\APIs\JinaClipApi;
use Sigmie\AI\Contracts\EmbeddingsApi;
use Sigmie\Helpers\ImageHelper;
use Sigmie\Testing\FakeClipApi;

class ImageSearchCorrectnessTest extends TestCase
{
    public function test_clip_fake_embeds_literal_and_escaped_image_filenames_identically(): void
    {
        $clip = new FakeClipApi($this->clipStub());

        $this->assertSame(
            $clip->embed('https://example.com/basket-ball.jpeg', 32),
            $clip->embed('https://example.com/basket%2Dball.jpeg', 32),
        );
    }

    public function test_an_image_url_with_non_ascii_characters_is_a_url(): void
    {
        $this->assertTrue(ImageHelper::isUrl('https://example.com/bilder/größe.jpg'));
        $this->assertTrue(ImageHelper::isUrl('https://bücher.de/cover.jpg'));
        $this->assertFalse(ImageHelper::isUrl('größe.jpg'));
        $this->assertFalse(ImageHelper::isUrl('ein Bild von Bäumen'));
    }

    public function test_wrapped_base64_becomes_an_unwrapped_image_uri(): void
    {
        $gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $uri = 'data:image/gif;base64,'.$gif;
        $wrapped = chunk_split($gif, 20, "\r\n");

        $this->assertSame($uri, ImageHelper::base64ImageUri($wrapped));
        $this->assertSame($uri, ImageHelper::base64ImageUri('data:image/gif;base64,'.$wrapped));
    }

    public function test_image_data_uri_decodes_url_escapes_in_base64_payload(): void
    {
        $gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $escaped = 'data:image/gif;base64,'.rawurlencode($gif);

        $this->assertSame(base64_decode($gif), ImageHelper::fetchImageContent($escaped));
        $this->assertSame('data:image/gif;base64,'.$gif, ImageHelper::base64ImageUri($escaped));
    }

    /** @test */
    public function jina_sends_bare_base64_as_an_image_uri(): void
    {
        $gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $api = new JinaClipApi;
        $uris = [];
        $client = new Client(['handler' => function (RequestInterface $request) use (&$uris): Promise {
            $body = json_decode((string) $request->getBody(), true);
            $uris[] = $body['data'][0]['uri'];
            $promise = new Promise;
            $promise->resolve(new Response(200, [], '{"data":[{"embedding":[1]}]}'));

            return $promise;
        }]);
        (new ReflectionProperty($api, 'client'))->setValue($api, $client);
        $api->embed($gif, 512);
        $api->promiseEmbed($gif, 512)->wait();
        $api->batchEmbed([['text' => $gif]]);
        $this->assertSame(array_fill(0, 3, 'data:image/gif;base64,'.$gif), $uris);
    }

    /**
     * @test
     */
    public function fetch_image_content_accepts_short_wrapped_and_svg_sources(): void
    {
        $gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        $this->assertLessThan(100, strlen($gif));
        $this->assertSame('GIF89a', substr(ImageHelper::fetchImageContent($gif), 0, 6));

        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $wrapped = chunk_split(base64_encode($png), 76, "\n");

        $this->assertStringContainsString("\n", $wrapped);
        $this->assertSame($png, ImageHelper::fetchImageContent($wrapped));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';

        $this->assertSame($svg, ImageHelper::fetchImageContent('data:image/svg+xml;base64,'.base64_encode($svg)));
        $this->assertFalse(ImageHelper::isBase64('aGVsbG8='));
        $this->assertFalse(ImageHelper::isBase64('aaaa'));
    }

    /**
     * @test
     */
    public function resize_keeps_a_one_pixel_side(): void
    {
        $image = imagecreatetruecolor(400, 1);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        $resized = imagecreatefromstring(ImageHelper::resizeImage($jpeg));

        $this->assertNotFalse($resized);
        $this->assertSame(224, imagesx($resized));
        $this->assertSame(1, imagesy($resized));
        imagedestroy($resized);
    }

    /**
     * @test
     *
     * @requires extension openssl
     */
    public function an_image_host_with_an_untrusted_certificate_is_refused(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $request = openssl_csr_new(['commonName' => '127.0.0.1'], $key, ['digest_alg' => 'sha256']);
        openssl_x509_export(openssl_csr_sign($request, null, $key, 1, ['digest_alg' => 'sha256']), $certificate);
        openssl_pkey_export($key, $privateKey);
        file_put_contents($pem = tempnam(sys_get_temp_dir(), 'sigmie-tls'), $certificate.$privateKey);

        // A TLS server with a self-signed certificate that answers one request with a 1x1 GIF.
        $server = <<<'PHP_WRAP'
$context = stream_context_create(['ssl' => ['local_cert' => $argv[1]]]);
$server = stream_socket_server('tls://127.0.0.1:0', $code, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
echo stream_socket_get_name($server, false), PHP_EOL;
if ($client = @stream_socket_accept($server, 10)) {
    fread($client, 8192);
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: image/gif\r\nContent-Length: ".strlen($gif)."\r\nConnection: close\r\n\r\n".$gif);
}
PHP_WRAP;
        $process = proc_open([PHP_BINARY, '-r', $server, '--', $pem], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $address = trim((string) fgets($pipes[1]));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to fetch image from URL');

        try {
            ImageHelper::fetchImageContent('https://'.$address.'/pixel.gif');
        } finally {
            proc_terminate($process);
            unlink($pem);
        }
    }

    /**
     * @test
     */
    public function resize_puts_transparent_pixels_on_white(): void
    {
        foreach ([400, 100] as $side) {
            $image = imagecreatetruecolor($side, $side);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefilledrectangle($image, 0, 0, $side - 1, $side - 1, imagecolorallocatealpha($image, 0, 0, 0, 127));
            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            imagedestroy($image);

            $resized = imagecreatefromstring(ImageHelper::resizeImage($png));
            $pixel = imagecolorsforindex($resized, imagecolorat($resized, 10, 10));
            imagedestroy($resized);

            $this->assertGreaterThan(245, min($pixel['red'], $pixel['green'], $pixel['blue']), $side.'px transparent PNG');
        }
    }

    /**
     * @test
     */
    public function a_file_finfo_cannot_read_is_not_an_image(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File is not a valid image: /proc/self/stack');

        ImageHelper::fetchImageContent('/proc/self/stack');
    }

    /**
     * @test
     */
    public function a_jpeg_the_encoder_refuses_is_an_error(): void
    {
        $image = imagecreatetruecolor(65501, 1);
        $color = imagecolorallocate($image, 200, 10, 10);
        imagefilledrectangle($image, 0, 0, 65500, 0, $color);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to create image from content');

        ImageHelper::resizeImage($png, 65501);
    }

    /**
     * @test
     */
    public function a_data_url_may_carry_a_charset_parameter(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';

        $this->assertSame(
            $svg,
            ImageHelper::fetchImageContent('data:image/svg+xml;charset=utf-8;base64,'.base64_encode($svg)),
        );
    }

    /**
     * @test
     */
    public function a_mixed_clip_batch_keeps_text_modality(): void
    {
        $api = new InfinityClipApi('http://example.com');
        $bodies = [];
        $client = new Client(['handler' => function (RequestInterface $request) use (&$bodies): Promise {
            $body = json_decode((string) $request->getBody(), true);
            $bodies[] = $body;
            $inputs = is_array($body['input']) ? $body['input'] : [$body['input']];
            $promise = new Promise;
            $promise->resolve(new Response(200, [], json_encode([
                'data' => array_map(fn (): array => ['embedding' => [0.1]], $inputs),
            ])));

            return $promise;
        }]);
        (new ReflectionProperty($api, 'client'))->setValue($api, $client);

        $api->batchEmbed([
            ['text' => 'portable computer', 'dims' => 512],
            ['text' => 'https://example.com/red-car.jpg', 'dims' => 512],
        ]);

        $text = null;
        $image = null;

        foreach ($bodies as $body) {
            $inputs = is_array($body['input']) ? $body['input'] : [$body['input']];

            if ($body['modality'] === 'image' && in_array('portable computer', $inputs, true)) {
                $this->fail('text was sent with image modality');
            }

            if (in_array('portable computer', $inputs, true)) {
                $text = $body['modality'];
            }

            if (in_array('https://example.com/red-car.jpg', $inputs, true)) {
                $image = $body['modality'];
            }
        }

        $this->assertSame('text', $text);
        $this->assertSame('image', $image);
    }

    /**
     * @test
     */
    public function a_clip_image_source_embeds_the_decoded_bytes(): void
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $payload = base64_encode($bytes);
        $sources = [
            'data:image/jpeg;base64,'.$payload,
            'data:image/JPEG;base64,'.$payload,
            'data:image/jpeg;charset=utf-8;base64,'.$payload,
            $payload,
        ];

        $clip = new FakeClipApi($this->clipStub());
        $phrase = $clip->embed('data image', 32);

        foreach ($sources as $source) {
            $this->assertTrue(ImageHelper::isBase64($source));
            $this->assertSame($phrase, $clip->embed($source, 32));
        }
    }

    /**
     * @test
     */
    public function a_clip_embed_records_the_call(): void
    {
        $clip = new FakeClipApi($this->clipStub());
        $clip->embed('plain text', 8);

        $this->assertSame([
            ['text' => 'plain text', 'dimensions' => 8],
        ], $clip->getEmbedCalls());
    }

    private function clipStub(): EmbeddingsApi
    {
        return new class implements EmbeddingsApi
        {
            public function embed(string $text, int $dimensions): array
            {
                return [];
            }

            public function batchEmbed(array $payload): array
            {
                return [];
            }

            public function promiseEmbed(string $text, int $dimensions): Promise
            {
                return new Promise;
            }

            public function model(): string
            {
                return 'stub';
            }

            public function maxBatchSize(): int
            {
                return 8;
            }
        };
    }
}
