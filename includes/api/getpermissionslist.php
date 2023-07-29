<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$permissions = ["permission" => WHMCS\User\Permissions::getAllPermissions()];
$apiresults = ["status" => "success", "permissions" => $permissions];
