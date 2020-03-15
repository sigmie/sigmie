<?php

namespace App\Services;

use App\Contracts\MailingList;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class MailgunList implements MailingList
{
    /**
     * Mailgun $domain
     *
     * @var string
     */
    private $domain;

    /**
     * Mailgun secret
     *
     * @var string
     */
    private $secret;

    /**
     * Mailgun api endpoint
     *
     * @var string
     */
    private $endpoint;

    /**
     * Guzzle HTTP Client
     *
     * @var Client
     */
    private $client;

    /**
     * Constructor
     *
     * @param Client $client
     * @param array $config
     */
    public function __construct($client, $config)
    {
        $this->client = $client;
        $this->domain = $config['domain'];
        $this->secret = $config['secret'];
        $this->endpoint = $config['endpoint'];
    }

    /**
     * Add to email to email list
     *
     * @param bool $subscribed
     * @param string $list
     * @param string $address
     * @param bool $subscribed
     * @param bool $upsert
     *
     * @return array
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
     *
     * @param string $list
     * @param string $email
     *
     * @return array
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
     *
     * @param string $list
     * @param string $email
     *
     * @return array
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
     *
     * @param string $list
     * @param string $email
     *
     * @return array
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
     *
     * @param string $list
     * @param string $email
     *
     * @return array
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
     *
     * @param ResponseInterface $response
     * @return array
     */
    private function formatResponse(ResponseInterface $response): array
    {
        $jsonResult = $response->getBody()->getContents();

        return json_decode($jsonResult, true);
    }
}
