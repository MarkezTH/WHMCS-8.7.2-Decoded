<?php

require "init.php";
define("ROUTE_CONVERTED_LEGACY_ENDPOINT", true);
$_GET["rp"] = "/login";
$_SERVER["REQUEST_METHOD"] = "POST";
require_once __DIR__ . "/index.php";
