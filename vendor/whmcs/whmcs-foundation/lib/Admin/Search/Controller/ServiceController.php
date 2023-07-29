<?php

namespace WHMCS\Admin\Search\Controller;

class ServiceController extends AbstractSearchController
{
    public function getSearchTerm($request)
    {
        return ["searchTerm" => $request->get("search", NULL), "clientId" => $request->get("clientId", 0)];
    }

    public function getSearchable($SearchInterface)
    {
        return new \WHMCS\Search\Service();
    }

    public function search($searchTerm)
    {
        if (is_array($searchTerm)) {
            $clientId = $searchTerm["clientId"] ?? NULL;
            $searchTerm = $searchTerm["searchTerm"] ?? NULL;
        } else {
            $clientId = NULL;
        }
        $searchFor = ["clientId" => $clientId, "searchTerm" => $searchTerm];
        return $this->getSearchable()->search($searchFor);
    }
}
