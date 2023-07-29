<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$password = encrypt($_POST["password2"]);
$apiresults = ["result" => "success", "password" => $password];
