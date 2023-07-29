<?php

namespace WHMCS\Api\NG\Versions\V2\EntityDecorators;

class ProductGroupDecorator extends \WHMCS\Api\NG\Versions\V2\AbstractApiEntityDecorator
{
    const DATA_KEYS = ["id", "name"];

    public static function getEntityClass()
    {
        return "WHMCS\\Product\\Group";
    }
}
