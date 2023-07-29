<?php

define("ADMINAREA", true);
require "../init.php";
if (!$whmcs instanceof WHMCS\Init) {
    exit("Failed to initialize application.");
}
$licenseerror = strtolower($whmcs->get_req_var("status"));
if (empty($licenseerror)) {
    $licenseerror = strtolower($whmcs->get_req_var("licenseerror"));
}
$validLicenseErrorTypes = ["invalid", "pending", "suspended", "expired", "version", "noconnection", "error", "change"];
$licenseCheckError = WHMCS\Session::getAndDelete("licenseCheckError");
if ($licenseCheckError) {
    $licenseerror = "error";
}
if (!in_array($licenseerror, $validLicenseErrorTypes)) {
    $licenseerror = $validLicenseErrorTypes[0];
}
$match = "";
$id = "";
$roleid = "";
$remote_ip = WHMCS\Utility\Environment\CurrentRequest::getIP();
$performLicenseKeyUpdate = $whmcs->get_req_var("updatekey") === "true";
$licenseChangeResult = "";
if ($performLicenseKeyUpdate && defined("DEMO_MODE")) {
    $performLicenseKeyUpdate = false;
    $licenseChangeResult = "demoMode";
}
if ($performLicenseKeyUpdate) {
    $authAdmin = new WHMCS\Auth();
    if ($authAdmin->getInfobyUsername($username) && $authAdmin->comparePassword($password)) {
        $roleid = get_query_val("tbladmins", "roleid", ["id" => $authAdmin->getAdminID()]);
        $result = select_query("tbladminperms", "COUNT(*)", ["roleid" => $roleid, "permid" => "64"]);
        $data = mysql_fetch_array($result);
        $match = $data[0];
        $license_key = trim($license_key);
        $licenseKeyPattern = "/^[a-zA-Z0-9-]+\$/";
        if (!$license_key) {
            $licenseChangeResult = "keyempty";
        } else {
            if (preg_match($licenseKeyPattern, $license_key) !== 1) {
                $licenseChangeResult = "keyinvalid";
            } else {
                if (!$match) {
                    $licenseChangeResult = "nopermission";
                } else {
                    if (is_writable("../configuration.php")) {
                        $_REQUEST["license_key"] = $license_key;
                        $updateStatus = licenseerror_updatelicensekey();
                        if ($updateStatus["success"]) {
                            $redirLocation = empty($updateStatus["redirect"]) ? "index.php" : $updateStatus["redirect"];
                            header("Location: " . $redirLocation);
                            WHMCS\Terminus::getInstance()->doExit();
                        }
                        $licenseChangeResult = $updateStatus["errorMessage"];
                    }
                }
            }
        }
    } else {
        $authAdmin->failedLogin();
        $licenseChangeResult = "loginfailed";
    }
}
$changeError = "";
if ($licenseChangeResult) {
    switch ($licenseChangeResult) {
        case "loginfailed":
            $changeError = "Login Details Incorrect";
            break;
        case "keyinvalid":
            $changeError = "You did not enter a valid license key";
            break;
        case "keyempty":
            $changeError = "You did not enter a new license key";
            break;
        case "nopermission":
            $changeError = "You do not have permission to make this change";
            break;
        case "demoMode":
            $changeError = "Actions on this page are unavailable while in demo mode. Changes will not be saved.";
            break;
    }
}
if ($licenseerror == "change" && !is_writable("../configuration.php")) {
    $changeError = "The current permissions for configuration.php will prevent successful update of the license key. Please ensure that your configuration file is writable by the web server process.";
}
$templatevars["errorMsg"] = $changeError;
$templatevars["licenseError"] = $licenseerror;
$templatevars["licenseCheckError"] = $licenseCheckError;
$assetHelper = DI::make("asset");
$templatevars["WEB_ROOT"] = $assetHelper->getWebRoot();
$templatevars["BASE_PATH_CSS"] = $assetHelper->getCssPath();
$templatevars["BASE_PATH_JS"] = $assetHelper->getJsPath();
$templatevars["BASE_PATH_FONTS"] = $assetHelper->getFontsPath();
$templatevars["BASE_PATH_IMG"] = $assetHelper->getImgPath();
$smarty = new WHMCS\Smarty(true);
foreach ($templatevars as $key => $value) {
    $smarty->assign($key, $value);
}
echo $smarty->fetch("licenseerror.tpl");
function licenseerror_updateLicenseKey()
{
    $result = (new WHMCS\Admin\Utilities\Assent\Controller\LicenseController())->updateLicenseKey(WHMCS\Http\Message\ServerRequest::fromGlobals());
    return json_decode($result->getBody()->getContents(), true);
}
