<?php

namespace WHMCS\Api\NG\Versions\V2\Controllers;

class ProductGroupController extends \WHMCS\Api\NG\Versions\V2\AbstractApiController implements \WHMCS\Api\NG\Versions\V2\PagedResponseInterface
{
    use \WHMCS\Api\NG\Versions\V2\PagedResponseTrait;

    public function getProductGroupList(\WHMCS\Http\Message\ServerRequest $request)
    {
        $data = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($this->paginateData(\WHMCS\Product\Group::all(), $request));
        return $this->createResponse($data);
    }

    public function getProductGroup(\WHMCS\Http\Message\ServerRequest $request)
    {
        $groupId = $request->get("group_id");
        $data = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate(\WHMCS\Product\Group::findOrFail($groupId));
        $data["products"] = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($this->paginateData(\WHMCS\Product\Product::where("gid", $groupId)->get(), $request));
        return $this->createResponse($data);
    }
}
