<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("List Addons");
App::redirectToRoutePath("admin-addons-index");
