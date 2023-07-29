<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$installedVersion = App::getVersion();
$versionOutput = ["version" => $installedVersion->getCasual(), "canonicalversion" => $installedVersion->getCanonical()];
$apiresults = ["result" => "success", "whmcs" => $versionOutput];
