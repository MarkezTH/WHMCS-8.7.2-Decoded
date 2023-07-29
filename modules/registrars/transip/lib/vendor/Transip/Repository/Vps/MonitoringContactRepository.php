<?php

namespace Transip\Api\Library\Repository\Vps;

class MonitoringContactRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "monitoring-contacts";

    public function getAll()
    {
        $response = $this->httpClient->get($this->getResourceUrl());
        $contactsArray = $this->getParameterFromResponse($response, "contacts");
        $contacts = [];
        foreach ($contactsArray as $contact) {
            $contacts[] = new \Transip\Api\Library\Entity\Vps\Contact($contact);
        }
        return $contacts;
    }

    public function create($name, $telephone, $email)
    {
        $params = ["name" => $name, "telephone" => $telephone, "email" => $email];
        $this->httpClient->post($this->getResourceUrl(), $params);
    }

    public function update($contact)
    {
        $this->httpClient->put($this->getResourceUrl($contact->getId()), ["contact" => $contact]);
    }

    public function delete($contactId)
    {
        $this->httpClient->delete($this->getResourceUrl($contactId), []);
    }
}
