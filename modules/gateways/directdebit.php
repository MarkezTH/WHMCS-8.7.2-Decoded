<?php

function directdebit_MetaData()
{
    return ["gatewayType" => WHMCS\Module\Gateway::GATEWAY_BANK, "failedEmail" => "Direct Debit Payment Failed", "successEmail" => "Direct Debit Payment Confirmation", "pendingEmail" => "Direct Debit Payment Pending", "processingType" => WHMCS\Module\Gateway::PROCESSING_OFFLINE];
}

function directdebit_config()
{
    $configarray = ["FriendlyName" => ["Type" => "System", "Value" => "Direct Debit"]];
    return $configarray;
}

function directdebit_localbankdetails()
{
}
