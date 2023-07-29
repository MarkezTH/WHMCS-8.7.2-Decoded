<?php

function duosecurity_config()
{
    $twofa = new WHMCS\TwoFactorAuthentication();
    $integrationKey = decrypt($twofa->getModuleSetting("duosecurity", "integrationKey"));
    $secretKey = decrypt($twofa->getModuleSetting("duosecurity", "secretKey"));
    $apiHostname = $twofa->getModuleSetting("duosecurity", "apiHostname");
    $extraDescription = "";
    if (!$integrationKey && !$secretKey && !$apiHostname) {
        $extraDescription .= "<div class=\"alert alert-success\" style=\"margin:10px 0;padding:8px 15px;\">New to Duo Security? <a href=\"http://go.whmcs.com/918/duo-security-signup\" target=\"_blank\" class=\"alert-link\">Click here to create an account</a></div>";
    }
    return ["FriendlyName" => ["Type" => "System", "Value" => "Duo Security"], "ShortDescription" => ["Type" => "System", "Value" => "Get codes via Duo Push, SMS or Phone Callback."], "Description" => ["Type" => "System", "Value" => "Duo Security enables your users to secure their logins using their smartphones. Authentication options include push notifications, passcodes, text messages and/or phone calls." . $extraDescription], "integrationKey" => ["FriendlyName" => "Integration Key", "Type" => "password", "Size" => "25"], "secretKey" => ["FriendlyName" => "Secret Key", "Type" => "password", "Size" => "45"], "apiHostname" => ["FriendlyName" => "API Hostname", "Type" => "text", "Size" => "45"]];
}

function duosecurity_activate($params)
{
}

function duosecurity_activateverify($params)
{
    return ["msg" => "You will be asked to configure your Duo Security Two-Factor Authentication the next time you login."];
}

function duosecurity_challenge($params)
{
    $whmcs = App::self();
    $appsecretkey = sha1("Duo" . $whmcs->get_hash());
    $username = $params["user_info"]["username"];
    $email = $params["user_info"]["email"];
    $inAdmin = defined("ADMINAREA");
    $integrationkey = !empty($params["settings"]["integrationKey"]) ? decrypt($params["settings"]["integrationKey"]) : "";
    $secretkey = !empty($params["settings"]["secretKey"]) ? decrypt($params["settings"]["secretKey"]) : "";
    $apihostname = WHMCS\Input\Sanitize::escapeSingleQuotedString($params["settings"]["apiHostname"]);
    $uid = $username . ":" . $email . ":" . $whmcs->get_license_key();
    $sig_request = WHMCS\Input\Sanitize::escapeSingleQuotedString(Duo\Web::signRequest($integrationkey, $secretkey, $appsecretkey, $uid));
    $output = "There is an error with the DuoSecurity module configuration.";
    if (!$integrationkey || !$secretkey || !$apihostname) {
        logActivity(($inAdmin ? "Admin" : "Client") . " Duo Security Login Failed: " . $sig_request);
        $sig_request = NULL;
        $output .= "<br>Please login with your backup code" . ($inAdmin ? " and check the DuoSecurity configuration." : ".");
    }
    if ($sig_request != NULL) {
        $route = defined("ADMINAREA") ? "dologin.php" : routePath("login-two-factor-challenge-verify");
        $route = WHMCS\Input\Sanitize::escapeSingleQuotedString($route);
        $asset = DI::make("asset");
        $output = "<script src=\"" . $asset->getWebRoot() . "/modules/security/duosecurity/Duo-Web-v2.min.js\"></script>\n<script>\n  Duo.init({\n    \"host\": '" . $apihostname . "',\n    \"sig_request\": '" . $sig_request . "',\n    \"post_action\": '" . $route . "'\n  });\n</script>\n<iframe id=\"duo_iframe\" width=\"100%\" height=\"500\" frameborder=\"0\"></iframe>";
    }
    return $output;
}

function duosecurity_verify($params)
{
    $request = WHMCS\Http\Message\ServerRequest::fromGlobals();
    $whmcs = App::self();
    $appsecretkey = sha1("Duo" . $whmcs->get_hash());
    $integrationkey = !empty($params["settings"]["integrationKey"]) ? decrypt($params["settings"]["integrationKey"]) : "";
    $secretkey = !empty($params["settings"]["secretKey"]) ? decrypt($params["settings"]["secretKey"]) : "";
    if (Duo\Web::verifyResponse($integrationkey, $secretkey, $appsecretkey, $request->get("sig_response"))) {
        return true;
    }
    return false;
}
