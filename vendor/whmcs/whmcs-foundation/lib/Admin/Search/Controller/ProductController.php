<?php

namespace WHMCS\Admin\Search\Controller;

class ProductController extends AbstractSearchController
{
    public function getSearchTerm($request)
    {
        return ["searchTerm" => $request->get("search", NULL), "productId" => $request->get("productId", 0)];
    }

    public function getSearchable($Product)
    {
        return new \WHMCS\Search\Product();
    }

    public function search($searchTerm)
    {
        if (is_array($searchTerm)) {
            $productId = $searchTerm["productId"] ?? NULL;
            $searchTerm = $searchTerm["searchTerm"] ?? NULL;
        } else {
            $productId = NULL;
        }
        $searchFor = ["productId" => $productId, "searchTerm" => $searchTerm];
        return $this->getSearchable()->search($searchFor);
    }
}
