<?php

namespace WHMCS\Admin\Search\Controller;

abstract class AbstractSearchController implements \WHMCS\Search\ApplicationSupport\Controller\SearchInterface, \WHMCS\Search\SearchInterface
{
    public abstract function getSearchTerm(\WHMCS\Http\Message\ServerRequest $request);
    public abstract function getSearchable();

    public function searchRequest(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $data = $this->getSearchable()->search($this->getSearchTerm($request));
        } catch (\WHMCS\Exception\Information $e) {
            $data = ["warning" => $e->getMessage()];
        } catch (\Exception $e) {
            $data = ["error" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($data);
    }
}
