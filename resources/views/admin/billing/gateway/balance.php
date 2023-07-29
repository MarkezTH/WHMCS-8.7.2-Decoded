<?php

if (!$balances && !$gatewaysLoading) {
    return NULL;
}
foreach ($balances as &$gatewayBalanceCollection) {
    $gatewayBalanceCollection = $gatewayBalanceCollection->filter(function (WHMCS\Module\Gateway\BalanceInterface $balance) {
        return $balance->getCurrencyObject() instanceof Currency;
    });
}
unset($gatewayBalanceCollection);
echo "<div id=\"gatewayBalanceTotals\" class=\"balance-container\">\n    <div class=\"balance-hr\"></div>\n    <div>\n        <h2 class=\"balance-title\">";
echo AdminLang::trans("billing.gatewayBalances");
echo "</h2>\n        <a href=\"#\" id=\"balanceRefreshBtn\" class=\"btn btn-default btn-xs\"><i class=\"fas fa-sync-alt\"></i>&nbsp;";
echo AdminLang::trans("global.refresh");
echo "</a>\n        <span class=\"balance-updated\">";
echo AdminLang::trans("global.lastUpdated");
echo ": ";
echo $lastUpdated->diffForHumans();
echo "</span>\n    </div>\n    <div id=\"divGatewayBalances\" class=\"balance-wrapper\">\n        ";
foreach ($balances as $gateway => $balanceItems) {
    foreach ($balanceItems as $balanceItem) {
        echo "                <div class=\"balance-column\">\n                    <div class=\"balance-panel\">\n                        <div class=\"balance-panel-body\">\n                            <span class=\"balance-name\">";
        echo $gatewayInterfaces[$gateway]->getDisplayName();
        echo "</span>\n                            ";
        if ($gatewaysLoading[$gateway]) {
            echo "                                <span class=\"balance-loading\">\n                                    <i class=\"fas fa-spinner fa-spin\"></i> ";
            echo AdminLang::trans("global.loading");
            echo "                                </span>\n                                <span class=\"balance-label\">&nbsp;</span>\n                            ";
        } else {
            echo "                                <span class=\"balance-amount\" style=\"color:";
            echo $balanceItem->getColor();
            echo "\">";
            echo $balanceItem->getAmount();
            echo "</span>\n                                <span class=\"balance-label\">";
            echo $balanceItem->getLabel();
            echo "</span>\n                            ";
        }
        echo "                        </div>\n                    </div>\n                </div>\n                ";
    }
}
echo "    </div>\n    <div>\n        <a href=\"#\" id=\"balanceShowAllBtn\" class=\"btn btn-default btn-xs\"><i class=\"fas fa-plus\"></i>&nbsp;";
echo AdminLang::trans("global.expandAll");
echo "</a>\n    </div>\n</div>\n<style>\n    .balance-container {\n        margin-top: 15px;\n    }\n    .balance-hr {\n        border-top: 1px solid #ddd;\n        margin: auto;\n        width: 90%;\n    }\n    .balance-title {\n        margin:15px 0 10px;\n    }\n    .balance-updated {\n        font-size:0.9em;\n    }\n    .balance-wrapper {\n        display: flex;\n        flex-wrap: wrap;\n        margin: 0 -5px;\n        max-height: 190px;\n        overflow-y: scroll;\n        transition: all .5s ease;\n    }\n    .balance-column {\n        display: inline-block;\n        width: 100%;\n        padding: 5px;\n    }\n    .balance-panel {\n        border: 1px solid #ddd;\n        border-radius: 4px;\n        background: #fff;\n    }\n    .balance-panel-body {\n        padding: 10px;\n    }\n    .balance-panel-body span {\n        display: block;\n    }\n    .balance-name {\n        font-weight:bold;\n    }\n    .balance-amount {\n        font-size:1.25em;\n    }\n    .balance-loading {\n        color: #87939f;\n        font-size:1.25em;\n    }\n    .balance-label {\n        font-size:0.95em;\n    }\n    #balanceShowAllBtn {\n        display: none;\n    }\n    @media (min-width:492px) {\n        .balance-column {\n            width: 50%;\n        }\n    }\n    @media (min-width:576px) {\n        .balance-wrapper {\n            overflow-y: hidden;\n        }\n        #balanceShowAllBtn {\n            display: initial;\n        }\n    }\n    @media (min-width:720px) {\n        .balance-column {\n            width: 33.3%;\n        }\n    }\n    @media (min-width:950px) {\n        .balance-column {\n            width: 220px;\n        }\n    }\n</style>\n<script>\n    (function() {\n        if (jQuery('#divGatewayBalances').prop(\"scrollHeight\") <= 190) {\n            jQuery('#balanceShowAllBtn').hide();\n        }\n    })();\n    jQuery(document).off('click.balanceControls');\n    jQuery(document).on('click.balanceControls', '#balanceRefreshBtn', function() {\n        var body = jQuery('#divGatewayBalances');\n        body.css('height', body.outerHeight()).css('max-height', 190);\n        jQuery(this).prop('disabled', true).addClass('disabled').find('i').addClass('fa-spin');\n        gatewayBalanceForceLoad = 1;\n        loadGatewayBalances();\n        return false;\n    }).on('click.balanceControls', '#balanceShowAllBtn', function() {\n        var body = jQuery('#gatewayBalanceTotals').find('.balance-wrapper'),\n            button = jQuery('#balanceShowAllBtn');\n        body.css('max-height', body.prop(\"scrollHeight\"));\n        button.hide();\n        return false;\n    });\n    ";
if ($refreshOnLoad) {
    echo "    jQuery(document).ready(function()\n    {\n        jQuery('#balanceRefreshBtn').click();\n    });\n    ";
}
echo "</script>\n";
