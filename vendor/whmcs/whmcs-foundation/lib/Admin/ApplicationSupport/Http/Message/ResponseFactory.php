<?php

namespace WHMCS\Admin\ApplicationSupport\Http\Message;

class ResponseFactory
{
    public function genericError(\WHMCS\Http\Message\ServerRequest $request, $statusCode = 500)
    {
        if ($request->expectsJsonResponse()) {
            $msg = sprintf("%s. Error URL: %s.", \AdminLang::trans("errorPage." . $statusCode . ".title"), (string) $request->getUri());
            $response = new \WHMCS\Http\Message\JsonResponse(["status" => "error", "errorMessage" => $msg], $statusCode);
        } else {
            $body = view("error.oops", ["statusCode" => $statusCode]);
            $response = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\ErrorPage($body, $statusCode))->setTitle(\AdminLang::trans("errorPage." . $statusCode . ".title"));
        }
        return $response;
    }

    public function missingPermission(\WHMCS\Http\Message\ServerRequest $request, $permissionNames = [], $allRequired = true)
    {
        $statusCode = 403;
        if ($request->expectsJsonResponse()) {
            return $this->genericError($request, $statusCode);
        }
        $translatedPermissionNames = [];
        if (empty($permissionNames)) {
            $translatedPermissionNames[] = "Unknown";
            logActivity("Access Denied to Unspecified");
        } else {
            foreach ($permissionNames as $name) {
                $id = \WHMCS\User\Admin\Permission::findId($name);
                if ($id) {
                    $translatedPermissionNames[] = \AdminLang::trans("permissions." . $id);
                }
            }
            logActivity("Access Denied to " . implode(",", $permissionNames));
        }
        if ($allRequired) {
            $requireText = \AdminLang::trans("permissions.requiresAll");
        } else {
            $requireText = \AdminLang::trans("permissions.requiresOne");
        }
        $description = "<strong>" . $requireText . "</strong><br />" . implode(", ", $translatedPermissionNames);
        $body = view("error.oops", ["statusCode" => $statusCode, "description" => $description]);
        $response = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\ErrorPage($body, $statusCode))->setTitle(\AdminLang::trans("errorPage." . $statusCode . ".title"));
        return $response;
    }

    public function invalidCsrfToken(\WHMCS\Http\Message\ServerRequest $request)
    {
        $statusCode = 401;
        $msg = \AdminLang::trans("errorPage.general.invalidCsrfToken");
        if ($request->expectsJsonResponse()) {
            $response = new \WHMCS\Http\Message\JsonResponse(["status" => "error", "errorMessage" => $msg], $statusCode);
        } else {
            $body = view("error.oops", ["statusCode" => $statusCode, "subtitle" => $msg]);
            $response = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\ErrorPage($body, $statusCode))->setTitle(\AdminLang::trans("errorPage." . $statusCode . ".title"));
        }
        return $response;
    }
}
