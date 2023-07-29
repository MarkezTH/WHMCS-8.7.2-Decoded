<?php

namespace WHMCS\Authentication;

class LoginHandler
{
    const SESSION_REDIRECT_NAME = "loginPostRedirectUri";
    const SESSION_REDIRECT_NO_2FA = "loginRedirectWithout2FA";
    const SESSION_IN_OAUTH_LOGIN_FLOW = "loginViaOauthFlow";

    public static function autoReturnUri()
    {
        $requestUri = html_entity_decode($_SERVER["REQUEST_URI"]);
        self::setReturnUri($requestUri);
    }

    public static function validateAndSaveGotoRequest($goto)
    {
        $cleanedGoto = (new \WHMCS\Auth())->cleanRedirectUri($goto);
        if (!$cleanedGoto) {
            return NULL;
        }
        $baseUrl = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        if (!empty($baseUrl) && strpos($cleanedGoto, $baseUrl) === false) {
            $redirectUri = $baseUrl . "/" . $cleanedGoto;
        } else {
            $redirectUri = $cleanedGoto;
        }
        self::setReturnUri($redirectUri);
    }

    public static function saveGotoRequest($uri)
    {
        self::setReturnUri((new \WHMCS\Auth())->sanitizeUri($uri));
    }

    public static function setReturnUri($uri)
    {
        \WHMCS\Session::set(self::SESSION_REDIRECT_NAME, $uri);
    }

    public static function getReturnUri()
    {
        return \WHMCS\Session::get(self::SESSION_REDIRECT_NAME);
    }

    public static function clearReturnUri()
    {
        \WHMCS\Session::delete(self::SESSION_REDIRECT_NAME);
    }

    public static function disableRedirectToTwoFactor($state)
    {
        if ($state) {
            \WHMCS\Session::set(self::SESSION_REDIRECT_NO_2FA, true);
        } else {
            \WHMCS\Session::delete(self::SESSION_REDIRECT_NO_2FA);
        }
    }

    public static function isRedirectToTwoFactorDisabled()
    {
        return \WHMCS\Session::exists(self::SESSION_REDIRECT_NO_2FA);
    }

    public static function setIsOauthLoginRequest($state)
    {
        \WHMCS\Session::set(self::SESSION_IN_OAUTH_LOGIN_FLOW, $state);
    }

    public static function isOauthLoginRequest($getAndReset)
    {
        $retrievalType = "get";
        if ($getAndReset) {
            $retrievalType = "getAndDelete";
        }
        return (bool) \WHMCS\Session::$retrievalType(self::SESSION_IN_OAUTH_LOGIN_FLOW);
    }

    public static function captureRequestParams()
    {
        if (\App::isInRequest("ssoredirect") && \App::getFromRequest("ssoredirect")) {
            \WHMCS\Session::set("login_ssoredirect", true);
        }
    }

    public static function dispatchLoginShareHooks($username, $password)
    {
        $hookResults = run_hook("ClientLoginShare", ["username" => $username, "password" => $password]);
        foreach ($hookResults as $hookData) {
            if ($hookData) {
                $hookId = $hookData["id"];
                $hookEmail = $hookData["email"];
                if (is_numeric($hookId)) {
                    $client = \WHMCS\User\Client::find($hookId);
                    if ($client) {
                        return $client;
                    }
                    return NULL;
                }
                $client = \WHMCS\User\Client::where("email", $hookEmail)->first();
                if ($client->exists) {
                    return $client;
                }
                if ($hookData["create"]) {
                    if (!function_exists("addClient")) {
                        require_once ROOTDIR . "/includes/clientfunctions.php";
                    }
                    try {
                        $user = \WHMCS\User\User::createUser($hookData["firstname"], $hookData["lastname"], $hookData["email"], $hookData["password"]);
                        $client = addClient($user, $hookData["firstname"], $hookData["lastname"], $hookData["companyname"], $hookData["email"], $hookData["address1"], $hookData["address2"], $hookData["city"], $hookData["state"], $hookData["postcode"], $hookData["country"], $hookData["phonenumber"], false, [], "", false, NULL);
                        return $client;
                    } catch (\Exception $e) {
                        return NULL;
                    }
                }
            }
        }
        return NULL;
    }
}
