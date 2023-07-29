<?php

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}

function google_analytics_config()
{
    $configarray = ["name" => "Google Analytics", "description" => "This module provides a quick and easy way to integrate full Google Analytics tracking into your WHMCS installation", "version" => "3.0", "author" => "WHMCS", "fields" => ["analytics_version" => ["FriendlyName" => "Analytics Version", "Type" => "radio", "Options" => ["Global Site Tag" => "Google Analytics 4", "Google Analytics" => "Google (Classic) Analytics <span class='label inactive'>Deprecated</span>", "Universal Analytics" => "Universal Analytics <span class='label inactive'>Deprecated</span>"], "Description" => "<a href='https://support.google.com/analytics/answer/10089681'target='_blank'>More Info</a>"], "code" => ["FriendlyName" => "Measurement ID", "Type" => "text", "Size" => "25", "Description" => "Format: G-XXXXXXXXXX OR UA-XXXXXXXX-X (Tracking ID)"], "domain" => ["FriendlyName" => "Tracking Domain", "Type" => "text", "Size" => "25", "Description" => "(Optional) Format: example.com"]]];
    return $configarray;
}

function google_analytics_output($vars)
{
    echo "<br /><br />\n<p align=\"center\"><input type=\"button\" value=\"Launch Google Analytics Website\" onclick=\"window.open('https://analytics.google.com/','ganalytics');\" class=\"btn btn-primary btn-lg\" /></p>\n<br /><br />\n<p>Configuration of the Google Analytics Addon is done via <a href=\"configaddonmods.php\"><b>Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Addon Modules</b></a>. Please also ensure your active client area footer.tpl template file includes the {\$footeroutput} template tag.</p>";
}
