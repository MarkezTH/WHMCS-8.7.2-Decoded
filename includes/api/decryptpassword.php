<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
$password = decrypt($_POST["password2"]);
$apiresults = ["result" => "success", "password" => $password];
