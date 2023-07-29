<?php

namespace WHMCS\Admin\Search\Controller;

class UserController extends AbstractSearchController
{
    public function getSearchTerm($request)
    {
        return ["searchTerm" => $request->get("search", ""), "clientId" => $request->get("clientId", 0)];
    }

    public function getSearchable($User)
    {
        return new \WHMCS\Search\User();
    }

    public function search($searchTerm)
    {
        if (is_array($searchTerm)) {
            $clientId = isset($searchTerm["clientId"]) ? $searchTerm["clientId"] : 0;
            $searchTerm = isset($searchTerm["searchTerm"]) ? $searchTerm["searchTerm"] : "";
        } else {
            $clientId = 0;
        }
        $searchFor = ["clientId" => $clientId, "searchTerm" => $searchTerm];
        return $this->getSearchable()->search($searchFor);
    }
}
