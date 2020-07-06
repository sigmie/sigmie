<?php declare(strict_types=1);

namespace App\Services;

use App\Contracts\MailingList;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class MailgunList implements MailingList
{

    /**
     * Mailgun secret
     */
    private $secret;

    /**
     * Mailgun api endpoint
     */
    private string $endpoint;

    private Client $client;

    public function __construct(Client $client, array $config)
    {
        $this->client = $client;
        $this->secret = $config['secret'];
        $this->endpoint = $config['endpoint'];
    }

    /**
     * Add to email to email list
     */
    public function addToList(string $list, string $address, bool $subscribed = false, bool $upsert = false): array
    {
        $response = $this->client->post(
            "https://{$this->endpoint}/v3/lists/{$list}/members",
            [
                'auth' => [
                    'api',
                    $this->secret
                ],
                'form_params' => [
                    'address' => $address,
                    'subscribed' => ($subscribed)  ? 'yes' : 'no',
                    'upsert' => ($upsert)  ? 'yes' : 'no'
                ]
            ]
        );

        return $this->formatResponse($response);
    }

    /**
     * Remove from email list
     */
    public function removeFromList(string $list, string $email): array
    {
        $response = $this->client->delete(
            "https://{$this->endpoint}/v3/lists/{$list}/members/${email}",
            [
                'auth' => [
                    'api',
                    $this->secret
                ]
            ]
        );

        return $this->formatResponse($response);
    }

    /**
     * Confirm list subscription
     */
    public function confirmSubscription(string $list, string $email): array
    {
        $response = $this->client->put(
            "https://{$this->endpoint}/v3/lists/{$list}/members/${email}",
            [
                'auth' => [
                    'api',
                    $this->secret
                ],
                'form_params' => [
                    'subscribed' => 'yes',
                ],
            ]
        );

        return $this->formatResponse($response);
    }

    /**
     * Revoke list subscription
     */
    public function revokeSubscription(string $list, string $email): array
    {
        $response = $this->client->put(
            "https://{$this->endpoint}/v3/lists/{$list}/members/${email}",
            [
                'auth' => [
                    'api',
                    $this->secret
                ],
                'form_params' => [
                    'subscribed' => 'no',
                ],
            ]
        );

        return $this->formatResponse($response);
    }

    /**
     * Retrieve a list member
     */
    public function retrieveMember(string $list, string $email): array
    {
        $response = $this->client->get(
            "https://{$this->endpoint}/v3/lists/${list}/members/${email}",
            [
                'auth' => [
                    'api',
                    env('MAILGUN_SECRET')
                ],
                'connect_timeout' => 2.5
            ]
        );

        return $this->formatResponse($response);
    }

    /**
     * Decode guzzle response
     */
    private function formatResponse(ResponseInterface $response): array
    {
        $jsonResult = $response->getBody()->getContents();

        return json_decode($jsonResult, true);
    }
}
