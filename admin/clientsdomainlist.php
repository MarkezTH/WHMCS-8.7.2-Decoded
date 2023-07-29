<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("List Domains");
App::redirectToRoutePath("admin-domains-index");
