<?php

echo "<a href=\"https://marketplace.whmcs.com/connect\" target=\"_blank\"><img src=\"../assets/img/marketconnect/logo.png\" class=\"marketconnect-logo\" id=\"marketconnectLogo\" alt=\"MarketConnect\"></a>\n<br><br>\n<p class=\"lead\">Connecting you with Value Added Services, Upsells and Additional Revenue Streams. <a href=\"https://marketplace.whmcs.com/connect\" target=\"_blank\">Learn more &raquo;</a></p>\n\n<div class=\"clearfix\"></div>\n\n";
if ($account["linked"] && $account["balance"] <= 0) {
    echo "    <div class=\"alert alert-info text-center\" style=\"border-radius:0;\">\n        Your balance is <strong>empty</strong>. Before you can provision services, you must first <a href=\"#\" class=\"alert-link deposit-funds\">Deposit Funds</a>\n    </div>\n";
} else {
    if ($account["linked"] && $account["balance"] <= 5) {
        echo "    <div class=\"alert alert-info text-center\" style=\"border-radius:0;\">\n        Your balance is low. To avoid interuption in provisioning services, <a href=\"#\" class=\"alert-link deposit-funds\">Deposit Funds</a> now\n    </div>\n";
    }
}
echo "\n";
