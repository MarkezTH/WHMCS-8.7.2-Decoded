<?php

namespace WHMCS\Admin\Setup;

trait Oauth2MailControllerTrait
{
    private function getClientSecret($request)
    {
        $clientSecret = $request->get("clientSecret");
        try {
            $storedClientSecret = $this->getStoredClientSecret($request) ?? "";
        } catch (\Throwable $e) {
            throw new \Exception(\AdminLang::trans("global.erroroccurred"));
        }
        if ($storedClientSecret && !hasMaskedPasswordChanged($clientSecret, $storedClientSecret)) {
            $clientSecret = $storedClientSecret;
        }
        return $clientSecret;
    }

    public function oauth2GetRedirectUrl(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $validator = new \WHMCS\Validate();
            $validator->validate("required", "clientId", ["fields", "clientid"]);
            $validator->validate("required", "clientSecret", ["fields", "clientsecret"]);
            if ($validator->hasErrors()) {
                throw new \Exception(\AdminLang::trans("global.required") . ": " . implode(", ", $validator->getErrors()));
            }
            $handler = new \WHMCS\Mail\MailAuthHandler();
            $providerName = $request->get("serviceProvider");
            $clientId = $request->get("clientId");
            $clientSecret = $this->getClientSecret($request);
            $provider = $handler->createProvider($providerName, $clientId, $clientSecret, $this->context);
            $authUrl = $provider->getAuthorizationUrl($handler->getAuthorizationUrlOptions($providerName));
            \WHMCS\Session::set("oauth2state", ["provider" => $providerName, "clientId" => $clientId, "clientSecret" => $clientSecret, "providerState" => $provider->getState(), "csrfToken" => $request->get("token")]);
            $response = ["url" => $authUrl];
        } catch (\Exception $e) {
            $response = ["error" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function oauth2Callback(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $oauth2State = \WHMCS\Session::getAndDelete("oauth2state");
            if (!is_array($oauth2State) || $oauth2State["providerState"] !== $request->get("state")) {
                throw new \WHMCS\Exception("Connection unsuccessful. Please try again.");
            }
            $handler = new \WHMCS\Mail\MailAuthHandler();
            $provider = $handler->createProvider($oauth2State["provider"], $oauth2State["clientId"], $oauth2State["clientSecret"], $this->context);
            $token = $provider->getAccessToken("authorization_code", ["code" => $request->get("code")]);
            return "<script>window.opener.whmcsSetOauthRefreshToken(\"" . $token->getRefreshToken() . "\");" . "window.close();" . "</script>";
        } catch (\Exception $e) {
            return "Connection unsuccessful. Please close this window and try again.";
        }
    }
}
