<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$serviceid = App::getFromRequest("serviceid");
$clientid = App::getFromRequest("clientid");
$addonid = App::getFromRequest("addonid");
$query = WHMCS\Database\Capsule::table("tblhostingaddons")->distinct()->join("tblhosting", "tblhosting.id", "=", "tblhostingaddons.hostingid")->join("tbladdons", "tbladdons.id", "=", "tblhostingaddons.addonid", "LEFT");
if ($serviceid) {
    if (is_numeric($serviceid)) {
        $query = $query->where("tblhostingaddons.hostingid", "=", $serviceid);
    } else {
        $serviceids = array_map("trim", explode(",", $serviceid));
        $query = $query->whereIn("tblhostingaddons.hostingid", $serviceids);
    }
}
if ($clientid) {
    $query = $query->where("tblhosting.userid", "=", $clientid);
}
if ($addonid) {
    $query = $query->where("tblhostingaddons.addonid", "=", $addonid);
}
$query = $query->orderBy("tblhostingaddons.id", "ASC");
$result = $query->get(["tblhostingaddons.*", "tblhosting.userid", "tbladdons.name AS addon_name"])->all();
$apiresults = ["result" => "success", "serviceid" => $serviceid, "clientid" => $clientid, "totalresults" => count($result)];
$gatewaysObj = new WHMCS\Gateways();
foreach ($result as $data) {
    $addonarray = ["id" => $data->id, "userid" => $data->userid, "orderid" => $data->orderid, "serviceid" => $data->hostingid, "addonid" => $data->addonid, "name" => $data->name ?: $data->addon_name, "setupfee" => $data->setupfee, "recurring" => $data->recurring, "billingcycle" => $data->billingcycle, "tax" => $data->tax, "status" => $data->status, "regdate" => $data->regdate, "nextduedate" => $data->nextduedate, "nextinvoicedate" => $data->nextinvoicedate, "paymentmethod" => $data->paymentmethod, "paymentmethodname" => $gatewaysObj->getDisplayName($data->paymentmethod), "notes" => $data->notes];
    $apiresults["addons"]["addon"][] = $addonarray;
}
$responsetype = "xml";
