<?php

namespace WHMCS\Search;

class Contact implements SearchInterface
{
    public function search($searchTerm = NULL)
    {
        if (is_array($searchTerm)) {
            $clientId = isset($searchTerm["clientId"]) ? $searchTerm["clientId"] : NULL;
            $searchTerm = isset($searchTerm["searchTerm"]) ? $searchTerm["searchTerm"] : "";
        } else {
            $clientId = NULL;
        }
        $data = [];
        if (!is_null($searchTerm) && $clientId) {
            $client = \WHMCS\User\Client::find($clientId);
            if ($client) {
                $data = $this->fuzzySearch($searchTerm, $client);
            }
        }
        return $data;
    }

    public function fuzzySearch($searchTerm, \WHMCS\User\Client $client)
    {
        $searchResults = [];
        $matchingContacts = $client->contacts();
        if ($searchTerm) {
            $matchingContacts->where(\WHMCS\Database\Capsule::raw("CONCAT(firstname, ' ', lastname)"), "LIKE", "%" . $searchTerm . "%")->orWhere("email", "LIKE", "%" . $searchTerm . "%")->orWhere("companyname", "LIKE", "%" . $searchTerm . "%");
            if (is_numeric($searchTerm)) {
                $matchingContacts->orWhere("id", "=", (int) $searchTerm)->orWhere("id", "LIKE", "%" . (int) $searchTerm . "%");
            }
        } else {
            $matchingContacts->limit(30);
        }
        foreach ($matchingContacts->get() as $contact) {
            $searchResults[] = ["id" => $contact->id, "type" => "contact", "name" => \WHMCS\Input\Sanitize::decode($contact->fullName), "companyname" => \WHMCS\Input\Sanitize::decode($contact->companyname), "email" => \WHMCS\Input\Sanitize::decode($contact->email)];
        }
        return $searchResults;
    }
}
