<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function offlinecc_MetaData()
{
    return ["gatewayType" => WHMCS\Module\Gateway::GATEWAY_CREDIT_CARD, "processingType" => WHMCS\Module\Gateway::PROCESSING_OFFLINE];
}

function offlinecc_config()
{
    return ["FriendlyName" => ["Type" => "System", "Value" => "Offline Credit Card"], "RemoteStorage" => ["Type" => "System", "Value" => true]];
}
