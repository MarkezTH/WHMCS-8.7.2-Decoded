<?php

namespace Transip\Api\Library\Repository;

class SshKeyRepository extends ApiRepository
{
    const RESOURCE_NAME = "ssh-keys";

    public function getAll()
    {
        $sshKeys = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $sshKeysArray = $this->getParameterFromResponse($response, "sshKeys");
        foreach ($sshKeysArray as $sshKeyArray) {
            $sshKeys[] = new \Transip\Api\Library\Entity\SshKey($sshKeyArray);
        }
        return $sshKeys;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $invoices = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $sshKeysArray = $this->getParameterFromResponse($response, "sshKeys");
        $sshKeys = [];
        foreach ($sshKeysArray as $sshKeyArray) {
            $sshKeys[] = new \Transip\Api\Library\Entity\SshKey($sshKeyArray);
        }
        return $sshKeys;
    }

    public function getById($SshKey, $sshKeyId)
    {
        $response = $this->httpClient->get($this->getResourceUrl($sshKeyId));
        $sshKeyArray = $this->getParameterFromResponse($response, "sshKey");
        return new \Transip\Api\Library\Entity\SshKey($sshKeyArray);
    }

    public function create($sshKey, $sshKeyDescription)
    {
        $this->httpClient->post($this->getResourceUrl(), ["sshKey" => $sshKey, "description" => $sshKeyDescription]);
    }

    public function update($sshKeyId, $sshKeyDescription)
    {
        $this->httpClient->put($this->getResourceUrl($sshKeyId), ["description" => $sshKeyDescription]);
    }

    public function delete($sshKeyId)
    {
        $this->httpClient->delete($this->getResourceUrl($sshKeyId));
    }
}
