<?php

namespace App\Contracts;

interface MailingList
{
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
    public function addToList(string $list, string $address, bool $subscribed = false, bool $upsert = false): array;

    /**
     * Remove from email list
     *
     * @param string $list
     * @param string $email
     *
     * @return array
     */
    public function removeFromList(string $list, string $email): array;

    /**
     * Confirm list subscription
     *
     * @param string $list
     * @param string $email
     *
     * @return array
     */
    public function confirmSubscription(string $list, string $email): array;

    /**
     * Revoke list subscription
     *
     * @param string $list
     * @param string $email
     *
     * @return array
     */
    public function revokeSubscription(string $list, string $email): array;

    /**
     * Retrieve a list member
     *
     * @param string $list
     * @param string $email
     *
     * @return array
     */
    public function retrieveMember(string $list, string $email): array;
}
