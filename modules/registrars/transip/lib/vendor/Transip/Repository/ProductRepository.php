<?php

namespace Transip\Api\Library\Repository;

class ProductRepository extends ApiRepository
{
    const RESOURCE_NAME = "products";

    public function getAll()
    {
        $products = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $categoryArray = $this->getParameterFromResponse($response, "products");
        foreach ($categoryArray as $category => $productsArray) {
            foreach ($productsArray as $productArray) {
                $productArray["category"] = $category;
                $products[] = new \Transip\Api\Library\Entity\Product($productArray);
            }
        }
        return $products;
    }
}
