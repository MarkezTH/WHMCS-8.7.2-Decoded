<?php

namespace WHMCS\Admin\Setup\Support;

class SupportDepartmentController implements \WHMCS\Admin\Setup\Oauth2MailControllerInterface
{
    use \WHMCS\Admin\Setup\Oauth2MailControllerTrait;
    protected $context = \WHMCS\Mail\MailAuthHandler::CONTEXT_SUPPORT_DEPARTMENT;

    public function testMailConnection(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $departmentId = $request->get("id");
            if ($departmentId) {
                $department = \WHMCS\Support\Department::findOrFail($departmentId);
            } else {
                $department = new \WHMCS\Support\Department();
            }
            $department->email = $request->get("email");
            $department->login = $request->get("login");
            $department->host = $request->get("host");
            $department->port = $request->get("port");
            $password = $request->get("password");
            if ($departmentId) {
                $storedPassword = $department->getPasswordAttribute();
                if (!hasMaskedPasswordChanged($password, $storedPassword)) {
                    $password = $storedPassword;
                }
            }
            $department->password = $password;
            $mailAuthConfig = $department->mailAuthConfig;
            $mailAuthConfig["service_provider"] = $request->get("service_provider");
            $mailAuthConfig["auth_type"] = $request->get("auth_type");
            $mailAuthConfig["oauth2_client_id"] = $request->get("oauth2_client_id");
            $clientSecret = $request->get("oauth2_client_secret");
            $refreshToken = $request->get("oauth2_refresh_token");
            if (hasMaskedPasswordChanged($clientSecret, $mailAuthConfig["oauth2_client_secret"])) {
                $mailAuthConfig["oauth2_client_secret"] = $clientSecret;
            }
            if (hasMaskedPasswordChanged($refreshToken, $mailAuthConfig["oauth2_refresh_token"])) {
                $mailAuthConfig["oauth2_refresh_token"] = $refreshToken;
            }
            $department->mailAuthConfig = $mailAuthConfig;
            $mailbox = \WHMCS\Mail\Incoming\MailboxFactory::createForDepartment($department, true);
            $mailbox->getMessageCount();
            $response = ["success" => "true"];
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            if ($e instanceof \League\OAuth2\Client\Provider\Exception\IdentityProviderException) {
                $responseBody = $e->getResponseBody();
                if (is_array($responseBody)) {
                    if (isset($responseBody["error_description"])) {
                        $errorMessage = $responseBody["error_description"];
                        if (isset($responseBody["error"])) {
                            $errorMessage = rtrim($errorMessage, ".") . ". Error: " . $responseBody["error"] . ".";
                        }
                    }
                } else {
                    if (is_string($responseBody)) {
                        $errorMessage = $responseBody;
                    }
                }
            }
            $response = ["error" => $errorMessage];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function getStoredClientSecret($request)
    {
        $departmentId = $request->get("supportDepartmentId");
        if ($departmentId) {
            return \WHMCS\Support\Department::findOrFail($departmentId)->mailAuthConfig["oauth2_client_secret"] ?? NULL;
        }
        return NULL;
    }
}
