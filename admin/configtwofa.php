<?php

define("ADMINAREA", true);
require "../init.php";
header("Location: " . routePath("admin-setup-auth-two-factor-index"));
exit;
