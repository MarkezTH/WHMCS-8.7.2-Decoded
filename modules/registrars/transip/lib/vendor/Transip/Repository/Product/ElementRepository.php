<?php

namespace Transip\Api\Library\Repository\Product;

class ElementRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "elements";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\ProductRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByProductName($productName)
    {
        $productElements = [];
        $response = $this->httpClient->get($this->getResourceUrl($productName));
        $productElementsArray = $this->getParameterFromResponse($response, "productElements");
        foreach ($productElementsArray as $productElementArray) {
            $productElements[] = new \Transip\Api\Library\Entity\Product\Element($productElementArray);
        }
        return $productElements;
    }
}
