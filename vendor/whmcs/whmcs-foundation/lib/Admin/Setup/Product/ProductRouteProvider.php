<?php

namespace WHMCS\Admin\Setup\Product;

class ProductRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        return ["/admin/setup/product" => ["attributes" => ["authentication" => "adminConfirmation"], ["method" => ["POST"], "name" => "admin-setup-product-validate-slug", "path" => "/validate/slug", "handle" => ["WHMCS\\Admin\\Setup\\Product\\ProductController", "validateSlug"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["Create New Products/Services", "Edit Products/Services"]);
        }], ["method" => ["POST"], "name" => "admin-setup-product-slug-remove", "path" => "/slug/remove", "handle" => ["WHMCS\\Admin\\Setup\\Product\\ProductController", "removeSlug"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["Create New Products/Services", "Edit Products/Services"]);
        }], ["method" => ["POST"], "name" => "admin-setup-product-group-slug", "path" => "/group/slug", "handle" => ["WHMCS\\Admin\\Setup\\Product\\ProductController", "groupSlug"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["Manage Product Groups"]);
        }], ["method" => ["POST"], "name" => "admin-setup-product-group-validate-slug", "path" => "/group/validate/slug", "handle" => ["WHMCS\\Admin\\Setup\\Product\\ProductController", "validateGroupSlug"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAnyPermission(["Manage Product Groups"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-setup-product-addon-new", "path" => "/addon/new", "handle" => ["WHMCS\\Admin\\Setup\\Product\\ProductController", "newAddon"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Configure Product Addons"]);
        }], ["method" => ["POST"], "name" => "admin-setup-product-addon-create", "path" => "/addon/create", "handle" => ["WHMCS\\Admin\\Setup\\Product\\ProductController", "createAddon"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Configure Product Addons"]);
        }], ["method" => ["POST"], "name" => "admin-setup-product-recommendation-search", "path" => "/{productId:\\d+}/product/recommendation/search", "handle" => ["WHMCS\\Admin\\Search\\Controller\\ProductController", "searchRequest"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Edit Products/Services"]);
        }]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-setup-product-";
    }
}
