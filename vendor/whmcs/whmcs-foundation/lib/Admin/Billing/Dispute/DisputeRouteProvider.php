<?php

namespace WHMCS\Admin\Billing\Dispute;

class DisputeRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        return ["/admin/billing/disputes" => ["attributes" => ["authentication" => "admin", "authorization" => function () {
            return new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization();
        }], ["method" => ["GET"], "name" => "admin-billing-disputes-index", "path" => "", "handle" => ["WHMCS\\Admin\\Billing\\Dispute\\DisputeController", "index"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["List Disputes"]);
        }], ["method" => ["GET"], "name" => "admin-billing-disputes-view", "path" => "/view/{gateway:\\w+}/{disputeId}", "handle" => ["WHMCS\\Admin\\Billing\\Dispute\\DisputeController", "view"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->setRequireAllPermission(["Manage Disputes"]);
        }], ["method" => ["POST"], "name" => "admin-billing-disputes-evidence-submit", "path" => "/evidence/submit/{gateway:\\w+}/{disputeId}", "handle" => ["WHMCS\\Admin\\Billing\\Dispute\\DisputeController", "submitEvidence"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Manage Disputes"]);
        }], ["method" => ["POST"], "name" => "admin-billing-disputes-submit", "path" => "/submit/{gateway:\\w+}/{disputeId}", "handle" => ["WHMCS\\Admin\\Billing\\Dispute\\DisputeController", "submit"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Manage Disputes"]);
        }], ["method" => ["POST"], "name" => "admin-billing-disputes-close", "path" => "/close/{gateway:\\w+}/{disputeId}", "handle" => ["WHMCS\\Admin\\Billing\\Dispute\\DisputeController", "close"], "authorization" => function (\WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization $authz) {
            return $authz->requireCsrfToken()->setRequireAllPermission(["Close Disputes"]);
        }]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-billing-disputes-";
    }
}
