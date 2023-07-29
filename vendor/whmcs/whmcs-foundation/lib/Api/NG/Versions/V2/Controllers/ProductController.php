<?php

namespace WHMCS\Api\NG\Versions\V2\Controllers;

class ProductController extends \WHMCS\Api\NG\Versions\V2\AbstractApiController implements \WHMCS\Api\NG\Versions\V2\PagedResponseInterface
{
    use \WHMCS\Api\NG\Versions\V2\PagedResponseTrait;

    public function getProductAddons(\WHMCS\Http\Message\ServerRequest $request)
    {
        $productId = $request->get("product_id");
        \WHMCS\Product\Product::findOrFail($productId);
        $addons = \WHMCS\Product\Addon::isNotHidden()->isNotRetired()->get();
        $addons = $addons->filter(function (\WHMCS\Product\Addon $addon) use($productId) {
            return in_array($productId, $addon->packages);
        });
        $data = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($this->paginateData($addons, $request));
        return $this->createResponse($data);
    }
}
