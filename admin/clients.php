<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("List Clients");
$aInt->title = $aInt->lang("clients", "viewsearch");
$aInt->sidebar = "clients";
$aInt->icon = "clients";
$aInt->setHelpLink("Client Management");
$limitClientId = 0;
$licensing = DI::make("license");
if ($licensing->isClientLimitsEnabled()) {
    $limitClientId = $licensing->getClientBoundaryId();
}
$name = "clients";
$orderby = "id";
$sort = "DESC";
$pageObj = new WHMCS\Pagination($name, $orderby, $sort);
$pageObj->digestCookieData();
$tbl = new WHMCS\ListTable($pageObj, 0, $aInt);
$tbl->setColumns(["checkall", ["id", $aInt->lang("fields", "id")], ["firstname", $aInt->lang("fields", "firstname")], ["lastname", $aInt->lang("fields", "lastname")], ["companyname", $aInt->lang("fields", "companyname")], ["email", $aInt->lang("fields", "email")], $aInt->lang("fields", "services"), ["datecreated", $aInt->lang("fields", "created")], ["status", $aInt->lang("fields", "status")]]);
$clientsModel = new WHMCS\Clients($pageObj);
$filter = (new WHMCS\Filter())->setAllowedVars(["userid", "name", "email", "country-calling-code-phone", "phone", "group", "status", "address1", "address2", "city", "state", "postcode", "country", "paymentmethod", "cctype", "cclastfour", "autoccbilling", "credit", "currency", "signupdaterange", "language", "marketingoptin", "autostatus", "taxexempt", "latefees", "overduenotices", "separateinvoices", "customfields", "email2", "country-calling-code-phone2", "phone2", "group2"]);
$searchCriteria = $filter->store()->getFilterCriteria();
foreach ($searchCriteria as $criteria => $value) {
    if (!empty($value)) {
        if (is_array($value)) {
            foreach ($value as $field => $fieldValue) {
                $searchCriteria[$criteria][$field] = trim($fieldValue);
            }
        } else {
            $searchCriteria[$criteria] = trim($value);
        }
    }
}
$clientsModel->execute($searchCriteria);
$tableOutput = "";
$numresults = $pageObj->getNumResults();
if ($filter->isActive() && $numresults == 1) {
    $client = $pageObj->getOne();
    redir("userid=" . $client["id"], "clientssummary.php");
} else {
    $clientlist = $pageObj->getData();
    foreach ($clientlist as $client) {
        $clientId = $client["id"];
        $linkopen = "<a href=\"clientssummary.php?userid=" . $client["id"] . "\"" . ($client["groupcolor"] ? " style=\"background-color:" . $client["groupcolor"] . "\"" : "") . ">";
        $linkclose = "</a>";
        $checkbox = "<input type=\"checkbox\" name=\"selectedclients[]\" value=\"" . $client["id"] . "\" class=\"checkall\" />";
        if (0 < $limitClientId && $limitClientId <= $clientId) {
            $checkbox = ["trAttributes" => ["class" => "grey-out"], "output" => $checkbox];
        }
        $tbl->addRow([$checkbox, $linkopen . $client["id"] . $linkclose, $linkopen . $client["firstname"] . $linkclose, $linkopen . $client["lastname"] . $linkclose, $client["companyname"], "<a href=\"mailto:" . $client["email"] . "\">" . $client["email"] . "</a>", $client["services"] . " (" . $client["totalservices"] . ")", $client["datecreated"], "<span class=\"label " . strtolower($client["status"]) . "\">" . $client["status"] . "</span>"]);
    }
    $tbl->setMassActionURL("sendmessage.php?type=general&multiple=true");
    $tbl->setMassActionBtns("<input type=\"submit\" value=\"" . $aInt->lang("global", "sendmessage") . "\" class=\"btn btn-default\" />");
    $tableOutput = $tbl->output();
    $clientsModel = NULL;
    unset($clientlist);
}
$displaySearchCriteria = $searchCriteria;
$displaySearchCriteria["phone"] = str_replace(".", "", App::formatPostedPhoneNumber("phone"));
$aInt->content = view("admin.client.index", ["searchActive" => $filter->isActive(), "searchCriteria" => $displaySearchCriteria, "clientsModel" => $clientsModel, "tableOutput" => $tableOutput, "searchEnabledOptions" => ["" => AdminLang::trans("global.any"), "true" => AdminLang::trans("searchOptions.enabled"), "false" => AdminLang::trans("searchOptions.disabled")], "searchEnabledOptionsInverse" => ["" => AdminLang::trans("global.any"), "false" => AdminLang::trans("searchOptions.enabled"), "true" => AdminLang::trans("searchOptions.disabled")], "countries" => (new WHMCS\Utility\Country())->getCountryNameArray(), "clientLanguages" => WHMCS\Language\ClientLanguage::getLanguages(), "clientGroups" => WHMCS\User\Client::getGroups(), "clientStatuses" => WHMCS\User\Client::getStatuses(), "paymentMethods" => WHMCS\Module\GatewaySetting::getActiveGatewayFriendlyNames(), "currencies" => WHMCS\Billing\Currency::defaultSorting()->pluck("code", "id"), "cardTypes" => WHMCS\User\Client::getUsedCardTypes(), "customFields" => WHMCS\CustomField::where("type", "client")->get()]);
$aInt->display();
