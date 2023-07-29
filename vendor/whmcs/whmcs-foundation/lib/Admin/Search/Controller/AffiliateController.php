<?php

namespace WHMCS\Admin\Search\Controller;

class AffiliateController extends AbstractSearchController
{
    public function getSearchTerm(\WHMCS\Http\Message\ServerRequest $request)
    {
        return $request->get("dropdownsearchq", NULL);
    }

    public function getSearchable()
    {
        return new \WHMCS\Search\Affiliate();
    }

    public function search($searchTerm = NULL)
    {
        return $this->getSearchable()->search($searchTerm);
    }
}
