<?php

namespace WHMCS\Admin\Search\Controller;

class IntelligentSearchController extends AbstractSearchController
{
    public function getSearchTerm(\WHMCS\Http\Message\ServerRequest $request)
    {
        return ["term" => $request->get("searchterm", ""), "hideInactive" => $request->get("hide_inactive", 1), "numResults" => $request->get("numresults", "10"), "more" => $request->get("more", "")];
    }

    public function getSearchable()
    {
        return new \WHMCS\Search\IntelligentSearch();
    }

    public function search($searchTerm = NULL)
    {
        return $this->getSearchable()->search($searchTerm);
    }

    public function setAutoSearch(\WHMCS\Http\Message\ServerRequest $request)
    {
        $status = $request->get("autosearch");
        \WHMCS\Search\IntelligentSearchAutoSearch::setStatus($status === "true");
        return new \WHMCS\Http\JsonResponse(["success" => true]);
    }
}
