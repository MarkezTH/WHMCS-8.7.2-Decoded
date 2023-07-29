<?php

namespace WHMCS\Admin\Search\Controller;

class ClientController extends AbstractSearchController
{
    public function getSearchTerm(\WHMCS\Http\Message\ServerRequest $request)
    {
        return ["searchTerm" => $request->get("dropdownsearchq", NULL), "clientId" => $request->get("clientId", NULL), "showNoneOption" => $request->get("showNoneOption", false)];
    }

    public function getSearchable()
    {
        return new \WHMCS\Search\Client();
    }

    public function search($searchTerm = NULL)
    {
        if (is_array($searchTerm)) {
            $clientId = isset($searchTerm["clientId"]) ? $searchTerm["clientId"] : NULL;
            $showNoneOption = isset($searchTerm["showNoneOption"]) ? $searchTerm["showNoneOption"] : false;
            $searchTerm = isset($searchTerm["searchTerm"]) ? $searchTerm["searchTerm"] : NULL;
        } else {
            $showNoneOption = false;
            $clientId = NULL;
        }
        $searchFor = ["clientId" => $clientId, "searchTerm" => $searchTerm, "showNoneOption" => $showNoneOption];
        return $this->getSearchable()->search($searchFor);
    }
}
