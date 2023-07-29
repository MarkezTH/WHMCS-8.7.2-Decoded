<?php

define("ADMINAREA", true);
require dirname(__DIR__) . "/init.php";
$aInt = new WHMCS\Admin("Manage MarketConnect");
$aInt->isSetupPage = true;
$aInt->title = AdminLang::trans("setup.marketconnect");
if (in_array(App::getFromRequest("action"), ["link"])) {
    $aInt->setResponseType($aInt::RESPONSE_JSON);
}
$aInt->requireAuthConfirmation();
$request = WHMCS\Http\Message\ServerRequest::fromGlobals();
$adminController = new WHMCS\MarketConnect\AdminController();
$aInt->setBodyContent($adminController->dispatch($request));
$aInt->display();
