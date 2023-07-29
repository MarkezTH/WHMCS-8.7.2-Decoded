<?php

echo "<div class=\"row home-status-badge-row\">\n    <div class=\"col-sm-4\">\n\n        <div class=\"health-status-block status-badge-";
if ($cronStatus->hasError()) {
    echo "error";
} else {
    if ($cronStatus->hasWarning()) {
        echo "warning";
    } else {
        echo "green";
    }
}
echo " hover-pointer open-modal clearfix\" href=\"";
echo routePath("admin-setup-automation-cron-status");
echo "\" data-modal-title=\"Cron Status\" data-modal-size=\"modal-lg\">\n            <div class=\"icon\">\n                ";
if ($cronStatus->hasError()) {
    echo "                    <i class=\"fas fa-times\"></i>\n                ";
} else {
    if ($cronStatus->hasWarning()) {
        echo "                    <i class=\"fas fa-exclamation-triangle\"></i>\n                ";
    } else {
        echo "                    <i class=\"fas fa-check\"></i>\n                ";
    }
}
echo "            </div>\n            <div class=\"detail\">\n                <span class=\"count\">\n                    ";
if ($cronStatus->hasError()) {
    echo "                        Error\n                    ";
} else {
    if ($cronStatus->hasWarning()) {
        echo "                        Warning\n                    ";
    } else {
        echo "                        Ok\n                    ";
    }
}
echo "                </span>\n                <span class=\"desc\">\n                    ";
if ($cronStatus->hasError()) {
    echo "                        Click here to resolve\n                    ";
} else {
    if ($cronStatus->hasWarning()) {
        echo "                        Click here to resolve\n                    ";
    } else {
        echo "                        View cron status\n                    ";
    }
}
echo "                </span>\n            </div>\n        </div>\n\n    </div>\n    <div class=\"col-sm-4\">\n\n        <div class=\"health-status-block status-badge-orange clearfix\">\n            <div class=\"icon\">\n                <i class=\"fas fa-calendar-alt\"></i>\n            </div>\n            <div class=\"detail\">\n                <span class=\"count\">";
if ($lastInvocationTime = $cronStatus->getLastCronInvocationTime()) {
    echo $lastInvocationTime->diffForHumans();
} else {
    echo "Never";
}
echo "</span>\n                <span class=\"desc\">Last Cron Invocation</span>\n            </div>\n        </div>\n\n    </div>\n    <div class=\"col-sm-4\">\n\n        <div class=\"health-status-block status-badge-grey clearfix\">\n            <div class=\"icon\">\n                <i class=\"far fa-calendar-check\"></i>\n            </div>\n            <div class=\"detail\">\n                <span class=\"count\">";
$lastDailyCronInvocationTime = $cronStatus->getLastDailyCronInvocationTime();
echo $lastDailyCronInvocationTime instanceof WHMCS\Carbon ? $lastDailyCronInvocationTime->addDay()->diffForHumans(NULL, true) : "N/A";
echo "</span>\n                <span class=\"desc\">Next Daily Task Run</span>\n            </div>\n        </div>\n\n    </div>\n</div>\n";
