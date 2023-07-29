<?php

namespace WHMCS\Authentication\Remote\Providers\Google;

class GoogleSignin extends \WHMCS\Authentication\Remote\Providers\AbstractRemoteAuthProvider
{
    protected $description = "Allow customers to register and sign in using their Google accounts.";
    protected $configurationDescription = "Google requires you to create an application and retrieve a client ID and secret.";
    const NAME = "google_signin";
    const FRIENDLY_NAME = "Google";

    public function getConfigurationFields()
    {
        return ["Enabled" => "Enabled", "ClientId" => "Client Id", "ClientSecret" => "Client Secret"];
    }

    public function getEnabled()
    {
        return !empty($this->config["Enabled"]);
    }

    public function setEnabled($value)
    {
        $this->config["Enabled"] = (bool) $value;
    }

    private function getClientId()
    {
        $this->checkIsEnabled();
        return $this->config["ClientId"];
    }

    public function parseMetadata($metadata)
    {
        return new \WHMCS\Authentication\Remote\AuthUserMetadata($metadata["name"], $metadata["email"], $metadata["email"], $this::FRIENDLY_NAME);
    }

    public function getHtmlScriptCode($htmlTarget)
    {
        if (in_array($htmlTarget, [static::HTML_TARGET_LOGIN, static::HTML_TARGET_REGISTER])) {
            $redirectUrl = \WHMCS\Session::get("loginurlredirect") ?: \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php";
        } else {
            if ($htmlTarget === static::HTML_TARGET_CONNECT) {
                $redirectUrl = routePath("user-security");
            } else {
                if ($htmlTarget === static::HTML_TARGET_CHECKOUT) {
                    $redirectUrl = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/cart.php?a=checkout";
                } else {
                    throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Invalid auth provider HTML target: " . $htmlTarget);
                }
            }
        }
        $assetHelper = \DI::make("asset");
        $redirectUrl = urlencode($redirectUrl);
        $appId = $this->getClientId();
        $originUrl = \App::getSystemURL(false);
        $routePath = routePath("auth-provider-google_signin-finalize");
        $targetRegister = static::HTML_TARGET_REGISTER;
        $targetConnect = static::HTML_TARGET_CONNECT;
        $displayName = static::FRIENDLY_NAME;
        $targetLogin = static::HTML_TARGET_LOGIN;
        $csrfToken = generate_token("plain");
        $cartCheckout = (int) $this->isOnCheckout();
        $html = "<script>\n    window.onerror = function(e){\n        WHMCS.authn.provider.displayError();\n    };\n\n    function onSignIn(credentialResponse) {\n        WHMCS.authn.provider.preLinkInit();\n\n        var failIfExists = 0;\n        if (\"" . $htmlTarget . "\" === \"" . $targetRegister . "\"\n           || \"" . $htmlTarget . "\" === \"" . $targetConnect . "\"\n        ) {\n            failIfExists = 1;\n        }\n        \n        var context = {\n            htmlTarget: \"" . $htmlTarget . "\",\n            targetLogin: \"" . $targetLogin . "\",\n            targetRegister: \"" . $targetRegister . "\",\n            redirectUrl: \"" . $redirectUrl . "\"\n        };\n        var config = {\n            url: \"" . $routePath . "\",\n            method: \"POST\",\n            dataType: \"json\",\n            data: {\n                id_token: credentialResponse.credential,\n                fail_if_exists: failIfExists,\n                token: \"" . $csrfToken . "\",\n                cartCheckout: " . $cartCheckout . "\n            }\n        };\n        var provider = {\n            \"name\": \"" . $displayName . "\",\n            \"icon\":  \"<img src=\\\"" . $assetHelper->getWebRoot() . "/assets/img/auth/google_icon.png\\\" width=\\\"17px\\\" height=\\\"17px\\\" alt=\\\"googleIcon\\\" /> \"\n        };\n\n        var providerDone = function () { };\n        var providerError = function () { };\n\n        WHMCS.authn.provider.signIn(config, context, provider, providerDone, providerError);\n    }\n</script>\n<script src=\"https://accounts.google.com/gsi/client\" async defer></script>";
        return $html;
    }

    public function getHtmlButton($htmlTarget)
    {
        $i++;
        $languageCode = \Lang::getLocaleMetadata()["languageCode"];
        $appId = $this->getClientId();
        return "            <div id=\"g_id_onload\"\n                data-client_id=\"" . $appId . "\"\n                data-callback=\"onSignIn\"\n                data-ux_mode=\"popup\"\n                data-auto_prompt=\"false\"\n                style=\"display: none\"\n            ></div>\n            <div id=\"btnGoogleSignin" . $i . "\"\n                class=\"g_id_signin btn btn-social btn-google\"\n                data-locale=\"" . $languageCode . "\"\n                data-type=\"standard\"\n                data-logo_alignment=\"center\"\n            ></div>";
    }

    private function checkIsEnabled()
    {
        if (!$this->getEnabled()) {
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Remote authentication not available via \"" . self::FRIENDLY_NAME . "\"");
        }
    }

    public function linkAccount($context)
    {
        $payload = $context;
        if (!is_array($payload)) {
            return false;
        }
        $expires = \WHMCS\Carbon::createFromTimestampUTC($payload["exp"]);
        if (!$expires->isFuture()) {
            return false;
        }
        $remoteUserId = $payload["sub"];
        if (empty($remoteUserId)) {
            return false;
        }
        return $this->linkLoggedInUser($remoteUserId, $context);
    }

    public function finalizeSignin(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token();
        try {
            $this->checkIsEnabled();
            if ($request->has("id_token")) {
                $jwt = new \Firebase\JWT\JWT();
                $jwt::$leeway = 3;
                $client = new \Google_Client(["client_id" => $this->getClientId(), "jwt" => $jwt]);
                $token = $request->get("id_token");
                $payload = $client->verifyIdToken($token);
                if (empty($payload) || empty($payload["sub"])) {
                    return new \WHMCS\Http\Message\JsonResponse("Invalid token", \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
                }
                $loginResult = $this->processRemoteUserId($payload["sub"], $payload, $request->get("fail_if_exists"));
                if ($request->get("cartCheckout")) {
                    \WHMCS\Session::set("2fafromcart", true);
                }
                $response = ["result" => $loginResult, "remote_account" => $this->getRegistrationFormData($payload)];
                if ($loginResult === static::LOGIN_RESULT_2FA_NEEDED) {
                    $response["redirect_url"] = routePath("login-two-factor-challenge");
                }
                return new \WHMCS\Http\Message\JsonResponse($response);
            }
            return new \WHMCS\Http\Message\JsonResponse("Invalid token", \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            $possibleCause = "";
            if ($e instanceof \Firebase\JWT\BeforeValidException || $e instanceof \Firebase\JWT\ExpiredException) {
                $possibleCause = " Please make sure that the system clock is set properly (current system time is " . date("c") . ").";
            }
            logActivity(sprintf("Remote account linking via %s has failed.%s Error: %s", static::FRIENDLY_NAME, $possibleCause, $e->getMessage()));
            return new \WHMCS\Http\Message\JsonResponse("Could not finalize sign-in", \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
    }

    public function getRegistrationFormData($context)
    {
        $fieldMap = ["email" => "email", "given_name" => "firstname", "family_name" => "lastname"];
        $formData = [];
        foreach ($fieldMap as $contextField => $regFormField) {
            if (isset($context[$contextField]) && $context[$contextField] !== ".") {
                $formData[$regFormField] = $context[$contextField];
            }
        }
        return $formData;
    }

    public function verifyConfiguration()
    {
        if (!$this->config["ClientId"] || !$this->config["ClientSecret"]) {
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Settings cannot be empty");
        }
        $guzzle = new \WHMCS\Http\Client\HttpClient();
        $parts = parse_url(\App::getSystemURL(false));
        $origin = $parts["scheme"] . "://" . $parts["host"];
        $params = ["action" => "checkOrigin", "origin" => $origin, "client_id" => $this->getClientId()];
        $url = "https://accounts.google.com/o/oauth2/iframerpc?" . http_build_query($params);
        try {
            $result = $guzzle->get($url, ["headers" => ["X-Requested-With" => "XmlHttpRequest"], \GuzzleHttp\RequestOptions::HTTP_ERRORS => !defined("ADMINAREA")]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new \RuntimeException("Connection to provider failed: " . $e->getMessage());
        }
        if ($result->getStatusCode() != 200) {
            $body = $result->getBody()->getContents();
            $body = json_decode($body, true);
            if (defined("ADMINAREA") && json_last_error() === JSON_ERROR_NONE && !empty($body["error_description"])) {
                throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException($body["error_description"]);
            }
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Settings resulted in invalid response code");
        }
        $response = json_decode($result->getBody(), true);
        if (!is_array($response) || empty($response["valid"])) {
            $msg = "Verification for current settings failed validation";
            if (is_array($response)) {
                $msg .= "Response: " . json_encode($response);
            }
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException($msg);
        }
    }

    public function getRemoteAccountName($context)
    {
        return !empty($context["email"]) ? $context["email"] : $context["given_name"] . " " . $context["family_name"];
    }
}
