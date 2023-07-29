<?php

class LegacyGoogleAuthenticator
{
    private $tokendata = "";

    public function setTokenData($token)
    {
        $this->tokendata = $token;
    }

    public function getData($username)
    {
        return $this->tokendata;
    }

    public function putData($username, $data)
    {
        return false;
    }

    public function getUsers()
    {
        return false;
    }
}

function totp_config()
{
    return ["FriendlyName" => ["Type" => "System", "Value" => "Time Based Tokens"], "ShortDescription" => ["Type" => "System", "Value" => "Get codes from an app like Google Authenticator or Duo."], "Description" => ["Type" => "System", "Value" => "TOTP requires that a user enter a 6 digit code that changes every 30 seconds to complete login. This works with mobile apps such as OATH Token and Google Authenticator."]];
}

function totp_activate($params)
{
    $username = $params["user_info"]["username"];
    $urlParts = parse_url(App::getSystemUrl());
    $domain = $urlParts["host"];
    $ga = new Sonata\GoogleAuthenticator\GoogleAuthenticator();
    $secret = NULL;
    $sessionKey = WHMCS\Session::get("totpKey");
    if ($sessionKey) {
        $secret = decrypt($sessionKey);
    }
    if (!$secret) {
        $secret = $ga->generateSecret();
        WHMCS\Session::set("totpKey", encrypt($secret));
    }
    $qrUrl = Sonata\GoogleAuthenticator\GoogleQrUrl::generate($username, $secret, $domain);
    $twoIpInstruct = sprintf(totp_getLangString("twoipinstruct", "twofa.twoipinstruct"), "<a href=\"https://itunes.apple.com/gb/app/google-authenticator/id388497605\" target=\"_blank\">" . totp_getLangString("twoipgoogleauth", "twofa.twoipgoogleauth") . "</a>", "<a href=\"https://itunes.apple.com/gb/app/duo-mobile/id422663827\" target=\"_blank\">" . totp_getLangString("twoipduo", "twofa.twoipduo") . "</a>");
    $twoIpConnect = totp_getLangString("twoipconnect", "twofa.twoipconnect");
    $twoIpVerificationStepMsg = totp_getLangString("twoipverificationstepmsg", "twofa.twoipverificationstepmsg");
    $twoIpEnterAuth = totp_getLangString("twoipenterauth", "twofa.twoipenterauth");
    $twoIpSubmit = totp_getLangString("submit", "global.submit");
    $twoIpMissing = totp_getLangString("twoipgdmissing", "twofa.twoipgdmissing");
    return "<h3 style=\"margin-top:0;\">" . $twoIpConnect . "</h3>\n<p>" . $twoIpInstruct . " <strong>" . $secret . "</strong></p>\n<div align=\"center\"><img src=\"" . $qrUrl . "\" style=\"border: 1px solid #ccc;border-radius: 4px;margin:15px 0;\"></div>\n<p>" . $twoIpVerificationStepMsg . "</p>\n" . ($params["verifyError"] ? "<div class=\"alert alert-danger\">" . $params["verifyError"] . "</div>" : "") . "\n<div class=\"row\">\n    <div class=\"col-sm-8\">\n        <input type=\"text\" name=\"verifykey\" maxlength=\"6\" style=\"font-size:18px;\" class=\"form-control input-lg form-control-lg\" placeholder=\"" . $twoIpEnterAuth . "\" autofocus>\n    </div>\n    <div class=\"col-sm-4\">\n        <input type=\"button\" value=\"" . $twoIpSubmit . "\" class=\"btn btn-primary btn-block btn-lg\" onclick=\"dialogSubmit()\" />\n    </div>\n</div>\n<br>";
}

function totp_activateverify($params)
{
    $email = $params["user_info"]["email"];
    $code = $params["post_vars"]["verifykey"];
    $sessionKey = WHMCS\Session::get("totpKey");
    $secret = decrypt($sessionKey);
    $ga = new Sonata\GoogleAuthenticator\GoogleAuthenticator();
    if (!$ga->checkCode($secret, $code)) {
        throw new WHMCS\Exception(totp_getLangString("twoipcodemissmatch", "twofa.twoipcodemissmatch"));
    }
    totp_add_to_used_codes($email, $code);
    WHMCS\Session::delete("totpKey");
    return ["settings" => ["secret" => $secret]];
}

function totp_get_fields($params)
{
    return [["name" => "key", "description" => "Enter a time-based token value.", "type" => "number"]];
}

function totp_challenge($params)
{
    return "<div align=\"center\">\n            <input type=\"text\" name=\"key\" maxlength=\"6\" class=\"form-control input-lg form-control-lg\" autofocus>\n        <br/>\n            <input id=\"btnLogin\" type=\"submit\" class=\"btn btn-primary btn-block btn-lg\" value=\"" . totp_getLangString("loginbutton", "twofa.loginbutton") . "\">\n            </div>";
}

function totp_get_used_otps()
{
    $usedotps = WHMCS\Config\Setting::getValue("TOTPUsedOTPs");
    $usedotps = $usedotps ? safe_unserialize($usedotps) : [];
    if (!is_array($usedotps)) {
        $usedotps = [];
    }
    return $usedotps;
}

function totp_add_to_used_codes($email, $code)
{
    $usedotps = totp_get_used_otps();
    $hash = md5($email . $code);
    $usedotps[$hash] = time();
    $expiretime = time() - 300;
    foreach ($usedotps as $k => $time) {
        if ($time < $expiretime) {
            unset($usedotps[$k]);
        } else {
            WHMCS\Config\Setting::setValue("TOTPUsedOTPs", safe_serialize($usedotps));
        }
    }
}

function totp_verify($params)
{
    $email = $params["user_info"]["email"];
    $code = $params["post_vars"]["key"];
    $hash = md5($email . $code);
    if (array_key_exists($hash, totp_get_used_otps())) {
        return false;
    }
    $userSettings = $params["user_settings"];
    if (array_key_exists("tokendata", $userSettings)) {
        $tokenData = $userSettings["tokendata"];
        totp_loadgaclass();
        $legacyGa = new LegacyGoogleAuthenticator();
        $legacyGa->setTokenData($tokenData);
        if ($legacyGa->authenticateUser("", $code)) {
            totp_add_to_used_codes($email, $code);
            return true;
        }
    } else {
        $secret = $userSettings["secret"];
        $ga = new Sonata\GoogleAuthenticator\GoogleAuthenticator();
        if ($ga->checkCode($secret, $code)) {
            totp_add_to_used_codes($email, $code);
            return true;
        }
    }
    return false;
}

function totp_loadgaclass()
{
    if (!class_exists("GoogleAuthenticator")) {
        include ROOTDIR . "/modules/security/totp/ga4php.php";
        class LegacyGoogleAuthenticator extends GoogleAuthenticator
        {
            private $tokendata = "";
            public function setTokenData($token)
            {
                $this->tokendata = $token;
            }
            public function getData($username)
            {
                return $this->tokendata;
            }
            public function putData($username, $data)
            {
                return false;
            }
            public function getUsers()
            {
                return false;
            }
        }
    }
}

function totp_getLangString($clientString, $adminString)
{
    if (defined("ADMINAREA")) {
        return AdminLang::trans($adminString);
    }
    return Lang::trans($clientString);
}
