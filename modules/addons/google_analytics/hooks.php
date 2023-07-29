<?php

use WHMCS\Application\Support\Facades\Hook;

if (!defined("WHMCS")) {
    exit("This file cannot be accessed directly");
}
Hook::add("ShoppingCartCheckoutCompletePage", 1, "google_analytics_hook_checkout_tracker");
Hook::add("ClientAreaHeadOutput", 1, "google_analytics_hook_page_tracking");
function google_analytics_hook_checkout_tracker($vars)
{
    $modulevars = WHMCS\Module\Addon\Setting::module("google_analytics")->pluck("value", "setting")->all();
    if (!$modulevars["code"]) {
        return false;
    }
    $modulevars["code"] = preg_replace("/[^a-z\\d\\-]+/i", "", $modulevars["code"]);
    $universalAnalytics = $googleAnalytics = $globalSiteTag = false;
    if ($modulevars["analytics_version"] == "Global Site Tag") {
        $globalSiteTag = true;
    } else {
        if ($modulevars["analytics_version"] == "Universal Analytics") {
            $universalAnalytics = true;
        } else {
            $googleAnalytics = true;
        }
    }
    $orderid = $vars["orderid"];
    $ordernumber = $vars["ordernumber"];
    $invoiceid = $vars["invoiceid"];
    $ispaid = $vars["ispaid"];
    $amount = $subtotal = $vars["amount"];
    $paymentmethod = $vars["paymentmethod"];
    $clientdetails = $vars["clientdetails"];
    $order = WHMCS\Order\Order::with(["addons", "domains", "services", "invoice"])->find($orderid);
    if (!$order) {
        return false;
    }
    $renewals = $order->renewals;
    if ($invoiceid == $order->invoiceId) {
        $subtotal = $order->invoice->subtotal;
        $tax = $order->invoice->tax1 + $order->invoice->tax2;
        $total = $order->invoice->total;
    }
    if (isset($_SESSION["gatracking"][$orderid])) {
        return false;
    }
    $_SESSION["gatracking"][$orderid] = 1;
    if ($universalAnalytics) {
        if (!empty($modulevars["domain"])) {
            $moduleDomain = "{ cookieDomain: '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["domain"]) . "' }";
        } else {
            $moduleDomain = "'auto'";
        }
        $code = "\n<!-- Google Analytics -->\n<script>\n(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){\n(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),\n    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)\n    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');\n\nga('create', '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["code"]) . "', " . $moduleDomain . ");\nga('send', 'pageview');\n\n// ecommerce functions.\nga('require', 'ecommerce', 'ecommerce.js');\nga('ecommerce:addTransaction', {\n    id: '" . $orderid . "',\n    affiliation: 'WHMCS Cart',\n    revenue: '" . $subtotal . "',\n    tax: '" . $tax . "'\n});\n";
    } else {
        if ($googleAnalytics) {
            $code = "\n<script type=\"text/javascript\">\nvar _gaq = _gaq || [];\n_gaq.push(['_setAccount', '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["code"]) . "']);";
            if ($modulevars["domain"]) {
                $code .= "\n_gaq.push(['_setDomainName', '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["domain"]) . "']);";
            }
            $code .= "\n_gaq.push(['_trackPageview']);\n_gaq.push(['_addTrans',\n'" . $orderid . "',\n'WHMCS Cart',\n'" . $subtotal . "',\n'" . $tax . "',\n'0',\n'" . $clientdetails["city"] . "',\n'" . $clientdetails["state"] . "',\n'" . $clientdetails["country"] . "'\n]);\n";
        } else {
            $code = "<script>\ngtag('event', 'purchase', {\n  \"transaction_id\": \"" . $orderid . "\",\n  \"affiliation\": \"WHMCS Cart\",\n  \"value\": \"" . $subtotal . "\",\n  \"shipping\": 0,\n  \"tax\": \"" . $tax . "\",\n  \"items\": [";
        }
    }
    $services = $order->services;
    foreach ($services as $service) {
        $itempid = WHMCS\Input\Sanitize::escapeSingleQuotedString($service->packageId);
        $name = WHMCS\Input\Sanitize::escapeSingleQuotedString($service->product->name);
        $groupname = WHMCS\Input\Sanitize::escapeSingleQuotedString($service->product->productGroup->name);
        $itemamount = WHMCS\Input\Sanitize::escapeSingleQuotedString($service->firstPaymentAmount);
        if ($universalAnalytics) {
            $code .= "\nga('ecommerce:addItem', {\n    id: '" . $orderid . "',\n    sku: 'PID" . $itempid . "',\n    name: '" . $name . "',\n    category: '" . $groupname . "',\n    price: '" . $itemamount . "',\n    quantity: '1'\n});\n";
        } else {
            if ($googleAnalytics) {
                $code .= "\n_gaq.push(['_addItem',\n'" . $orderid . "',\n'PID" . $itempid . "',\n'" . $name . "',\n'" . $groupname . "',\n'" . $itemamount . "',\n'1'\n]);\n";
            } else {
                $code .= "{\n  \"id\": 'PID" . $itempid . "',\n  \"name\": '" . $name . "',\n  \"category\": '" . $groupname . "',\n  \"price\": '" . $itemamount . "'\n},";
            }
        }
    }
    $addons = $order->addons;
    foreach ($addons as $addon) {
        $addonid = $addon->addonId;
        $name = $addon->name;
        $itemamount = $addon->setupFee + $addon->recurringFee;
        if ($universalAnalytics) {
            $code .= "\nga('ecommerce:addItem', {\n    id: '" . $orderid . "',\n    sku: 'AID" . $addonid . "',\n    name: '" . $name . "',\n    category: 'Addons',\n    price: '" . $itemamount . "',\n    quantity: '1'\n});\n";
        } else {
            if ($googleAnalytics) {
                $code .= "\n_gaq.push(['_addItem',\n'" . $orderid . "',\n'AID" . $addonid . "',\n'" . $name . "',\n'Addons',\n'" . $itemamount . "',\n'1'\n]);\n";
            } else {
                $code .= "{\n  \"id\": 'AID" . $addonid . "',\n  \"name\": '" . $name . "',\n  \"category\": 'Addons',\n  \"price\": '" . $itemamount . "'\n},";
            }
        }
    }
    $domains = $order->domains;
    foreach ($domains as $domain) {
        $regtype = $domain->type;
        $domainName = $domain->domain;
        $tld = strtoupper($domain->tld);
        $itemamount = $domain->firstPaymentAmount;
        if ($universalAnalytics) {
            $code .= "\nga('ecommerce:addItem', {\n    id: '" . $orderid . "',\n    sku: 'TLD" . $tld . "',\n    name: '" . $regtype . "',\n    category: 'Domain',\n    price: '" . $itemamount . "',\n    quantity: '1'\n});\n";
        } else {
            if ($googleAnalytics) {
                $code .= "\n_gaq.push(['_addItem',\n'" . $orderid . "',\n'TLD" . $tld . "',\n'" . $regtype . "',\n'Domain',\n'" . $itemamount . "',\n'1'\n]);\n";
            } else {
                $code .= "{\n  \"id\": 'TLD" . $tld . "',\n  \"name\": '" . $regtype . "',\n  \"category\": 'Domain',\n  \"price\": '" . $itemamount . "'\n},";
            }
        }
    }
    if ($renewals) {
        $renewals = explode(",", $renewals);
        foreach ($renewals as $renewal) {
            $renewal = explode("=", $renewal);
            list($domainid, $registrationperiod) = $renewal;
            $renewalDomain = WHMCS\Domain\Domain::find($domainid);
            if ($renewalDomain) {
                $domain = $renewalDomain->domain;
                $itemamount = $renewalDomain->recurringAmount;
                $tld = strtoupper($renewalDomain->tld);
                if ($universalAnalytics) {
                    $code .= "\nga('ecommerce:addItem', {\n    id: '" . $orderid . "',\n    sku: 'TLD" . $tld . "',\n    name: 'Renewal',\n    category: 'Domain',\n    price: '" . $itemamount . "',\n    quantity: '1'\n});\n";
                } else {
                    if ($googleAnalytics) {
                        $code .= "\n_gaq.push(['_addItem',\n'" . $orderid . "',\n'TLD" . $tld . "',\n'Renewal',\n'Domain',\n'" . $itemamount . "',\n'1'\n]);\n";
                    } else {
                        $code .= "{\n  \"id\": 'TLD" . $tld . "',\n  \"name\": 'Renewal',\n  \"category\": 'Domain',\n  \"price\": '" . $itemamount . "'\n},";
                    }
                }
            }
        }
    }
    if ($universalAnalytics) {
        $code .= "\nga('ecommerce:send');\n\n</script>\n<!-- End Google Analytics -->\n";
    } else {
        if ($googleAnalytics) {
            $code .= "\n_gaq.push(['_trackTrans']);\n\n(function() {\n    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;\n    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';\n    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);\n})();\n\n</script>";
        } else {
            $code .= "]\n});\n</script>";
        }
    }
    return $code;
}

function google_analytics_hook_page_tracking($vars)
{
    $modulevars = WHMCS\Module\Addon\Setting::module("google_analytics")->pluck("value", "setting");
    if (!$modulevars["code"]) {
        return false;
    }
    $modulevars["code"] = preg_replace("/[^a-z\\d\\-]+/i", "", $modulevars["code"]);
    if ($modulevars["analytics_version"] == "Universal Analytics") {
        if (!empty($modulevars["domain"])) {
            $domain = "{ cookieDomain: '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["domain"]) . "' }";
        } else {
            $domain = "'auto'";
        }
        $jscode = "\n<!-- Google Analytics -->\n<script>\n(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){\n(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),\n    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)\n    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');\n\nga('create', '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["code"]) . "', " . $domain . ");\nga('send', 'pageview');\n\n</script>\n<!-- End Google Analytics -->\n";
    } else {
        if ($modulevars["analytics_version"] == "Google Analytics") {
            $jscode = "<script type=\"text/javascript\">\n\nvar _gaq = _gaq || [];\n_gaq.push(['_setAccount', '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["code"]) . "']);";
            if ($modulevars["domain"]) {
                $jscode .= "\n_gaq.push(['_setDomainName', '" . WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["domain"]) . "']);";
            }
            $jscode .= "\n_gaq.push(['_trackPageview']);\n\n(function() {\nvar ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;\nga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';\nvar s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);\n})();\n\n</script>\n";
        } else {
            $siteTag = WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["code"]);
            $domainLine = "gtag('config', '" . $siteTag . "');";
            if (!empty($modulevars["domain"])) {
                $domain = WHMCS\Input\Sanitize::escapeSingleQuotedString($modulevars["domain"]);
                $domainLine = "gtag('config', '" . $siteTag . "', {\n  'cookie_domain': '" . $domain . "'\n});";
            }
            $jscode = "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . $siteTag . "\"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  " . $domainLine . "\n</script>";
        }
    }
    return $jscode;
}
