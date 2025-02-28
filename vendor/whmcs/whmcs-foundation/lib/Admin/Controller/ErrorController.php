<?php

namespace WHMCS\Admin\Controller;

class ErrorController
{
    use \WHMCS\Application\Support\Controller\DelegationTrait;

    public function loginRequired(\WHMCS\Http\Message\ServerRequest $request)
    {
        $msg = "Admin Login Required";
        if ($request->expectsJsonResponse()) {
            $response = new \WHMCS\Http\Message\JsonResponse(["status" => "error", "errorMessage" => $msg], 403);
        } else {
            $response = $this->redirectTo("admin-login", $request);
        }
        return $response;
    }
}
