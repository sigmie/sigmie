<?php

namespace App\Services;

use App\Contracts\MailingList;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class MailchimpList implements MailingList
{
    /**
     * Mailchimp key
     */
    private string $key;

    /**
     * Mailchimp data center
     */
    private string $dataCenter;

    /**
     * Mailchimp API url
     */
    private string $url;

    private Client $client;

    public function __construct(Client $client, array $config)
    {
        $this->client = $client;
        $this->key = $config['key'];
        $this->dataCenter = $config['data_center'];

        $this->url = "https://{$this->dataCenter}.api.mailchimp.com/3.0";
    }

    /**
     * Add to email to email list
     */
    public function addToList(string $list, string $address, bool $subscribed = false, bool $upsert = false): array
    {
        $memberId = $this->createMemberId($address);

        // "subscribed","unsubscribed","cleaned","pending"
        $status = ($subscribed) ? 'subscribed' : 'pending';

        $method = ($upsert) ? 'put' : 'post';

        $endpoint = ($upsert) ? "/lists/${list}/members/${memberId}" : "/lists/${list}/members";

        $response = $this->client->$method(
            $this->url . $endpoint,
            [
                'auth' => [
                    'anystring',
                    $this->key
                ],
                'json' => [
                    'email_address' => $address,
                    'status'        => $status
                ]
            ]
        );

        return $this->formatResponse($response);
    }

    /**
     * Remove from email list
     */
    public function removeFromList(string $list, string $address): array
    {
        $memberId = $this->createMemberId($address);

        $response = $this->client->post(
            "{$this->url}/lists/${list}/members/${memberId}/actions/delete-permanent",
            [
                'auth' => [
                    'anystring',
                    $this->key
                ]
            ]
        );


        return $this->formatResponse($response);
    }

    /**
     * Confirm list subscription
     */
    public function confirmSubscription(string $list, string $address): array
    {
        $memberId = $this->createMemberId($address);

        $response = $this->client->put(
            "{$this->url}/lists/${list}/members/${memberId}",
            [
                'auth' => [
                    'anystring',
                    $this->key
                ],
                'json' => [
                    'status' => 'subscribed'
                ]
            ]
        );


        return $this->formatResponse($response);
    }

    /**
     * Revoke list subscription
     */
    public function revokeSubscription(string $list, string $address): array
    {
        $memberId = $this->createMemberId($address);

        $response = $this->client->put(
            "{$this->url}/lists/${list}/members/${memberId}",
            [
                'auth' => [
                    'anystring',
                    $this->key
                ],
                'json' => [
                    'status' => 'unsubscribed'
                ]
            ]
        );


        return $this->formatResponse($response);
    }

    /**
     * Retrieve a list member
     */
    public function retrieveMember(string $list, string $address): array
    {
        $memberId = $this->createMemberId($address);

        $response = $this->client->get(
            "{$this->url}/lists/${list}/members/${memberId}",
            [
                'auth' => [
                    'anystring',
                    $this->key
                ]
            ]
        );


        return $this->formatResponse($response);
    }

    /**
     * Hast the address given creating
     * the member id
     */
    private function createMemberId($address)
    {
        return md5(strtolower($address));
    }

    /**
     * Decode guzzle response
     */
    private function formatResponse(ResponseInterface $response): array
    {
        $jsonResult = $response->getBody()->getContents();

        $result = json_decode($jsonResult, true);

        if ($result === null) {
            return [];
        }

        return $result;
    }
}
