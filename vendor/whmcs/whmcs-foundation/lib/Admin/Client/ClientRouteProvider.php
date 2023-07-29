<?php

namespace WHMCS\Admin\Client;

class ClientRouteProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\AdminProviderTrait;

    public function getRoutes()
    {
        $routes = ["/admin/client" => ["attributes" => ["authentication" => "admin"], ["method" => ["POST"], "name" => "admin-client-login", "path" => "/{client_id:\\d+}/login", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "login"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Allow Login as Owner"]);
        }], ["method" => ["POST"], "name" => "admin-client-export", "path" => "/{client_id:\\d+}/export", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "export"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Client Data Export"]);
        }], ["method" => ["GET", "POST"], "name" => "admin-client-consent-history", "path" => "/{client_id:\\d+}/consent/history", "handle" => ["WHMCS\\Admin\\Client\\ProfileController", "consentHistory"], "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAnyPermission(["Edit Clients Details"]);
        }], ["method" => ["GET"], "name" => "admin-client-tickets", "path" => "/{userId:\\d+}/tickets", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["List Support Tickets"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\TicketsController", "tickets"]], ["method" => ["POST"], "name" => "admin-client-tickets-close", "path" => "/{userId:\\d+}/tickets/close", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["List Support Tickets"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\TicketsController", "close"]], ["method" => ["POST"], "name" => "admin-client-tickets-delete", "path" => "/{userId:\\d+}/tickets/delete", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Delete Ticket"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\TicketsController", "delete"]], ["method" => ["POST"], "name" => "admin-client-tickets-merge", "path" => "/{userId:\\d+}/tickets/merge", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["List Support Tickets"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\TicketsController", "merge"]], ["method" => ["GET", "POST"], "name" => "admin-client-paymethods-view", "path" => "/{userId:\\d+}/paymethods/{payMethodId:\\d+}", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Pay Methods"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "viewPayMethod"]], ["method" => ["GET", "POST"], "name" => "admin-client-paymethods-new", "path" => "/{userId:\\d+}/paymethods/new/{payMethodType:\\w+}[/{desiredStorage:\\w+}]", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Pay Methods"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "newPayMethodForm"]], ["method" => ["POST"], "name" => "admin-client-paymethods-save", "path" => "/{userId:\\d+}/paymethods/save", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Pay Methods"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "saveNew"]], ["method" => ["POST"], "name" => "admin-client-paymethods-update", "path" => "/{userId:\\d+}/paymethods/update/{payMethodId:\\d+}", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Pay Methods"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "updateExisting"]], ["method" => ["POST"], "name" => "admin-client-paymethods-delete", "path" => "/{userId:\\d+}/paymethods/delete/{payMethodId:\\d+}", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Pay Methods"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "deleteExisting"]], ["method" => ["POST"], "name" => "admin-client-paymethods-delete-confirm", "path" => "/{userId:\\d+}/paymethods/delete/{payMethodId:\\d+}/confirm", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Pay Methods"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "doDeleteExisting"]], ["method" => ["POST"], "name" => "admin-client-paymethods-html-rows", "path" => "/{userId:\\d+}/paymethods/html/rows", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Pay Methods"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "payMethodsHtmlRows"]], ["method" => ["POST"], "name" => "admin-client-paymethods-decrypt-cc-data", "path" => "/{userId:\\d+}/paymethods/decrypt/{payMethodId:\\d+}", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Decrypt Full Credit Card Number"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "decryptCcData"]], ["method" => ["GET, POST"], "name" => "admin-client-profile-contacts", "path" => "/{userId:\\d+}/profile/contacts", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Edit Clients Details"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ProfileController", "profileContacts"]], ["method" => ["POST"], "name" => "admin-client-invoice-capture", "path" => "/{userId:\\d+}/invoice/{invoiceId:\\d+}/capture", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Invoice"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\Invoice\\InvoiceController", "capture"]], ["method" => ["POST"], "name" => "admin-client-invoice-capture-confirm", "path" => "/{userId:\\d+}/invoice/{invoiceId:\\d+}/capture/confirm", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Invoice"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\Invoice\\InvoiceController", "doCapture"]], ["method" => ["POST"], "name" => "admin-client-payment-remote-confirm", "path" => "/payment/remote/confirm", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Update/Delete Stored Credit Card"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "remoteConfirm"]], ["method" => ["POST"], "name" => "admin-client-payment-remote-confirm-update", "path" => "/payment/remote/confirm/update", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Update/Delete Stored Credit Card"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\PayMethod\\PayMethodController", "remoteUpdate"]], ["method" => ["GET"], "name" => "admin-client-users", "path" => "/{clientId:\\d+}/users", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["View Account Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "usersList"]], ["method" => ["POST"], "name" => "admin-client-users-associate-start", "path" => "/{clientId:\\d+}/users", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "associateUserModal"]], ["method" => ["POST"], "name" => "admin-client-user-associate-search", "path" => "/{clientId:\\d+}/user/search", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Search\\Controller\\UserController", "searchRequest"]], ["method" => ["POST"], "name" => "admin-client-user-associate", "path" => "/{clientId:\\d+}/user/associate", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "associateUser"]], ["method" => ["POST"], "name" => "admin-client-user-remove-association", "path" => "/{clientId:\\d+}/user/remove", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "removeUser"]], ["method" => ["POST"], "name" => "admin-client-user-invite-cancel", "path" => "/{clientId:\\d+}/user/invite/cancel", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "cancelInvite"]], ["method" => ["POST"], "name" => "admin-client-user-invite-resend", "path" => "/{clientId:\\d+}/user/invite/resend", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "resendInvite"]], ["method" => ["POST"], "name" => "admin-client-user-manage", "path" => "/{clientId:\\d+}/user/{userId:\\d+}/manage", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "manageUser"]], ["method" => ["POST"], "name" => "admin-client-user-manage-save", "path" => "/{clientId:\\d+}/user/{userId:\\d+}/manage/save", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Manage Users"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "saveUser"]], ["method" => ["POST"], "name" => "admin-client-search-submit-location", "path" => "/search/submit[/{location:\\w+}]", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken();
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "submitRedirect"]], ["method" => ["POST"], "name" => "admin-client-delete", "path" => "/{userId:\\d+}/delete", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["Delete Client"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "doDelete"]], ["method" => ["POST"], "name" => "admin-client-summary-filter", "path" => "/summary/filter", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["View Clients Summary"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Client\\ClientController", "summaryFilter"]], ["method" => ["POST"], "name" => "admin-client-service-search", "path" => "/{clientId:\\d+}/service/search", "authorization" => function () {
            return (new \WHMCS\Admin\ApplicationSupport\Route\Middleware\Authorization())->requireCsrfToken()->setRequireAllPermission(["View Clients Products/Services"]);
        }, "authentication" => "admin", "handle" => ["WHMCS\\Admin\\Search\\Controller\\ServiceController", "searchRequest"]]]];
        return $routes;
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "admin-client-";
    }
}
