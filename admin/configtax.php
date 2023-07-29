<?php

define("ADMINAREA", true);
require_once dirname(__DIR__) . "/init.php";
App::redirectToRoutePath("admin-setup-payments-tax-index");
