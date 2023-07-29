<?php

define("ADMINAREA", true);
require "../init.php";
$aInt = new WHMCS\Admin("View PHP Info");
$aInt->title = $aInt->lang("system", "phpinfo");
$aInt->sidebar = "utilities";
$aInt->icon = "phpinfo";
$aInt->content = "<p>The phpinfo() function is unavailable.</p>";
$phpInfo = WHMCS\Environment\Php::info();
if (!is_null($phpInfo)) {
    $phpInfo = preg_replace("%^.*<body>(.*)</body>.*\$%ms", "\$1", $phpInfo);
    $aInt->content = "<div class=\"whmcs-phpinfo\">" . $phpInfo . "</div>";
}
$aInt->display();
