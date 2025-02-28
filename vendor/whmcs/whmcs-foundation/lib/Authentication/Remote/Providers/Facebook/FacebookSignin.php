<?php

namespace WHMCS\Authentication\Remote\Providers\Facebook;

class FacebookSignin extends \WHMCS\Authentication\Remote\Providers\AbstractRemoteAuthProvider
{
    protected $description = "Allow customers to register and sign in using their Facebook accounts.";
    protected $configurationDescription = "Facebook requires you to create an application and retrieve the app ID and secret.";
    const NAME = "facebook_signin";
    const FRIENDLY_NAME = "Facebook";

    public function getConfigurationFields()
    {
        return ["Enabled" => "Enabled", "appId" => "App Id", "appSecret" => "App Secret"];
    }

    public function getEnabled()
    {
        return !empty($this->config["Enabled"]);
    }

    public function setEnabled($value)
    {
        $this->config["Enabled"] = (bool) $value;
    }

    private function getAppId()
    {
        $this->checkIsEnabled();
        return $this->config["appId"];
    }

    private function getAppSecret()
    {
        $this->checkIsEnabled();
        return $this->config["appSecret"];
    }

    public function parseMetadata($metadata)
    {
        return new \WHMCS\Authentication\Remote\AuthUserMetadata($metadata["first_name"] . " " . $metadata["last_name"], $metadata["email"], $metadata["email"], $this::FRIENDLY_NAME);
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
        $redirectUrl = urlencode($redirectUrl);
        $appId = $this->getAppId();
        $routePath = routePath("auth-provider-facebook_signin-finalize");
        $targetRegister = static::HTML_TARGET_REGISTER;
        $targetConnect = static::HTML_TARGET_CONNECT;
        $displayName = static::FRIENDLY_NAME;
        $targetLogin = static::HTML_TARGET_LOGIN;
        $csrfToken = generate_token("plain");
        $notAuthorizedText = \WHMCS\Input\Sanitize::escapeSingleQuotedString(\Lang::trans("facebook.notAuthorized"));
        $cartCheckout = (int) $this->isOnCheckout();
        $htmlCode = "<script>\n    window.onerror = function(e){\n        WHMCS.authn.provider.displayError();\n    };\n\n    window.fbAsyncInit = function() {\n        FB.init({\n            appId      : \"" . $appId . "\",\n            cookie     : true,  // enable cookies to allow the server to access the session\n            xfbml      : true,  // parse social plugins on this page\n            version    : \"v2.8\" // use graph api version 2.8\n        });\n    };\n\n    // Load the SDK asynchronously\n    (function(d, s, id) {\n        var js, fjs = d.getElementsByTagName(s)[0];\n        if (d.getElementById(id)) return;\n        js = d.createElement(s); js.id = id;\n        js.src = \"//connect.facebook.net/en_US/sdk.js\";\n        fjs.parentNode.insertBefore(js, fjs);\n    }(document, \"script\", \"facebook-jssdk\"));\n\n    function onLoginClick() {\n        WHMCS.authn.provider.preLinkInit();\n\n        FB.login(\n            function(response) {\n                var feedbackContainer = jQuery(\".providerLinkingFeedback\");\n                var btnContainer = jQuery(\".providerPreLinking\");\n\n                var failIfExists = 0;\n                if (\"" . $htmlTarget . "\" === \"" . $targetRegister . "\"\n                   || \"" . $htmlTarget . "\" === \"" . $targetConnect . "\"\n                ) {\n                    failIfExists = 1;\n                }\n                \n                var context = {\n                    htmlTarget: \"" . $htmlTarget . "\",\n                    targetLogin: \"" . $targetLogin . "\",\n                    targetRegister: \"" . $targetRegister . "\",\n                    redirectUrl: \"" . $redirectUrl . "\"\n                };\n\n                if (response.status === 'connected') {\n                    var config = {\n                        url: \"" . $routePath . "\",\n                        method: \"POST\",\n                        dataType: \"json\",\n                        data: {\n                            accessToken: response.authResponse.accessToken,\n                            fail_if_exists: failIfExists,\n                            token: \"" . $csrfToken . "\",\n                            cartCheckout: " . $cartCheckout . "\n                        }\n                    };\n                    var provider = {\n                        name: \"" . $displayName . "\",\n                        icon:  \"<i class=\\\"fab fa-facebook\\\"></i> \"\n                    };\n\n                    var providerDone = function () { FB = null; };\n                    var providerError = function () {};\n                } else if (!response.status) {\n                    response.status = \"unknown\";\n                }\n\n                switch (response.status) {\n                    case \"connected\":\n                        WHMCS.authn.provider.signIn(config, context, provider, providerDone, providerError);\n                        break;\n                    case \"not_authorized\":\n                        feedbackContainer.text('" . $notAuthorizedText . "').slideDown();\n                        break;\n                    case \"unknown\":\n                        feedbackContainer.slideUp();\n                }\n            });\n    }\n</script>";
        return $htmlCode;
    }

    public function getHtmlButton($htmlTarget)
    {
        if ($htmlTarget === self::HTML_TARGET_LOGIN) {
            $caption = \Lang::trans("remoteAuthn.signInWith", [":provider" => "Facebook"]);
        } else {
            if ($htmlTarget === self::HTML_TARGET_CONNECT) {
                $caption = \Lang::trans("remoteAuthn.connectWith", [":provider" => "Facebook"]);
            } else {
                $caption = \Lang::trans("remoteAuthn.signUpWith", [":provider" => "Facebook"]);
            }
        }
        return "\n        <button class=\"btn btn-social btn-facebook fb-login-button\" data-max-rows=\"1\" data-size=\"medium\" data-button-type=\"login_with\" data-show-faces=\"false\" data-auto-logout-link=\"false\" data-use-continue-as=\"false\" data-scope=\"public_profile,email\" onclick=\"onLoginClick()\" type=\"button\">\n            <i class=\"fab fa-facebook\"></i>\n            " . $caption . "\n        </button>";
    }

    private function checkIsEnabled()
    {
        if (!$this->getEnabled()) {
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Remote authentication not available via \"" . self::FRIENDLY_NAME . "\"");
        }
    }

    public function linkAccount($context)
    {
        $client = $context;
        if (!is_array($client)) {
            return false;
        }
        $remoteUserId = $client["id"];
        if (empty($remoteUserId)) {
            return false;
        }
        return $this->linkLoggedInUser($remoteUserId, $context);
    }

    private function createFacebookClient()
    {
        $fbOptions = ["app_id" => $this->getAppId(), "app_secret" => $this->getAppSecret(), "default_graph_version" => "v5.0"];
        if (!extension_loaded("curl")) {
            $fbOptions["http_client_handler"] = new FacebookGuzzle6HttpClient();
        }
        return new \Facebook\Facebook($fbOptions);
    }

    public function finalizeSignin(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token();
        try {
            $this->checkIsEnabled();
            if (!$request->has("accessToken")) {
                return new \WHMCS\Http\Message\JsonResponse("Invalid accessToken", \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
            }
            $fb = $this->createFacebookClient();
            try {
                $response = $fb->get("/me?fields=id,email,first_name,last_name,verified", $request->get("accessToken"));
            } catch (\Facebook\Exceptions\FacebookResponseException $e) {
                return new \WHMCS\Http\Message\JsonResponse("Facebook Graph API Error: " . $e->getMessage(), \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
            } catch (\Facebook\Exceptions\FacebookSDKException $e) {
                return new \WHMCS\Http\Message\JsonResponse("Facebook SDK returned an error: " . $e->getMessage(), \Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            $client = $response->getGraphUser();
            $loginResult = $this->processRemoteUserId($client->getId(), $client->asArray(), $request->get("fail_if_exists"));
            if ($request->get("cartCheckout")) {
                \WHMCS\Session::set("2fafromcart", true);
            }
            $response = ["result" => $loginResult, "remote_account" => $this->getRegistrationFormData($client->asArray())];
            if ($loginResult === static::LOGIN_RESULT_2FA_NEEDED) {
                $response["redirect_url"] = routePath("login-two-factor-challenge");
            }
            return new \WHMCS\Http\Message\JsonResponse($response);
        } catch (\Exception $e) {
            logActivity(sprintf("Remote account linking via %s has failed. Error: %s", static::FRIENDLY_NAME, $e->getMessage()));
            return new \WHMCS\Http\Message\JsonResponse("Could not finalize signin", \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }
    }

    public function getRegistrationFormData($context)
    {
        $fieldMap = ["email" => "email", "first_name" => "firstname", "last_name" => "lastname"];
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
        if (!$this->config["appId"] || !$this->config["appSecret"]) {
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Settings cannot be empty");
        }
        $guzzle = new \WHMCS\Http\Client\HttpClient();
        $params = ["client_id" => $this->getAppId(), "client_secret" => $this->getAppSecret(), "grant_type" => "client_credentials"];
        $url = "https://graph.facebook.com/v2.10/oauth/access_token?" . http_build_query($params);
        try {
            $result = $guzzle->get($url, [\GuzzleHttp\RequestOptions::HTTP_ERRORS => !defined("ADMINAREA")]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new \RuntimeException("Connection to provider failed");
        }
        if ($result->getStatusCode() != 200) {
            $body = $result->getBody()->getContents();
            $body = json_decode($body, true);
            if (defined("ADMINAREA") && json_last_error() === JSON_ERROR_NONE && !empty($body["error"]) && !empty($body["error"]["message"])) {
                throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException($body["error"]["message"]);
            }
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Settings resulted in invalid response code");
        }
        $response = json_decode($result->getBody(), true);
        if (!is_array($response) || !isset($response["access_token"])) {
            throw new \WHMCS\Exception\Authentication\Remote\RemoteAuthConfigException("Verification for current settings failed validation");
        }
    }

    public function getRemoteAccountName($context)
    {
        return !empty($context["email"]) ? $context["email"] : $context["first_name"] . " " . $context["last_name"];
    }
}
