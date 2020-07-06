<?php declare(strict_types=1);

namespace App\Rules;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Validation\Rule;

class DeliverableMail implements Rule
{
    /**
     * Guzzle client
     */
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        try {
            $response = $this->client->get(
                'https://api.mailgun.net/v4/address/validate',
                [
                    'auth' => [
                        'api',
                        config('services.mailgun.secret')
                    ],
                    'query' => ['address' => $value],
                    'connect_timeout' => 2.5
                ]
            );
        } catch (Exception $exception) {
            return true;
        }

        $content = $response->getBody()->getContents();
        $result = json_decode($content, true);

        if ($result['is_disposable_address']) {
            return false;
        }

        return $result['result'] === 'deliverable';
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The email address is not deliverable or is a disposable one.';
    }
}
