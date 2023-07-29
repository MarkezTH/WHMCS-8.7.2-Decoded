<?php

$this->layout("layouts/learn", $serviceOffering);
$this->start("nav-tabs");
echo "    <li class=\"active\" role=\"presentation\">\n        <a aria-controls=\"about\" data-toggle=\"tab\" href=\"#about\" role=\"tab\">About</a>\n    </li>\n    <li role=\"presentation\">\n        <a aria-controls=\"features\" data-toggle=\"tab\" href=\"#features\" role=\"tab\">Features</a>\n    </li>\n    <li role=\"presentation\">\n        <a aria-controls=\"pricing\" data-toggle=\"tab\" href=\"#pricing\" role=\"tab\">Pricing</a>\n    </li>\n    <li role=\"presentation\">\n        <a aria-controls=\"faq\" data-toggle=\"tab\" href=\"#faq\" role=\"tab\">FAQ</a>\n    </li>\n";
$this->end();
$this->start("content-tabs");
echo "    <div class=\"tab-pane active\" id=\"about\" role=\"tabpanel\">\n        <div class=\"content-padded ox about\">\n\n            <h3>OX App Suite: Hosted Email for SMBs</h3>\n            <h4>Offer professional, reliable email with all the bells and whistles at an affordable price!</h4>\n\n            <br>\n\n            <div style=\"float:left;width:420px;margin-bottom:10px;\">\n                <iframe src=\"https://player.vimeo.com/video/391308260?title=0&byline=0&portrait=0\" width=\"390\" height=\"220\" frameborder=\"0\" allow=\"autoplay; fullscreen\" allowfullscreen></iframe>\n            </div>\n\n            <p>Email is a critical component of any hosting product portfolio. As SMB needs grow, choices have been limited to Office 365 and G Suite. Now, OX App Suite presents another powerful option.</p>\n            <p>Your customers will enjoy all the email features they need on a rock solid platform. OX App Suite brings users 99.9% uptime, premium anti-virus and anti-spam protection, and advanced productivity tools.</p>\n            <p>With OX App Suite, you'll be offering a competetive, high-quality email platform at a fraction of the cost.</p>\n\n            <div class=\"clearfix\"></div>\n\n            <p><strong>About Open-Xchange</strong><br>\n            Founded in 2005, Open-Xchange provides white-labeled email software services to Global 500 hosts, ISPs, and other service providers. With over 160 million mailboxes in customer portfolios and millions more under the Dovecot brand, OX App Suite is a leader in email and productivity.</p>\n\n        </div>\n    </div>\n    <div class=\"tab-pane\" id=\"features\" role=\"tabpanel\">\n        <div class=\"content-padded ox features\">\n            <div class=\"row ox-features\">\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-email.png\">\n                    <span>Email</span>\n                    Powerful Webmail, or access IMAP on any device.\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-collab.png\">\n                    <span>Collaboration</span>\n                    Shared Calendaring, Contacts and Tasks\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-avas.png\">\n                    <span>Anti-Virus / Spam</span>\n                    Keep inboxes safe with premium AV/AS\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-alias.png\">\n                    <span>Email Aliases</span>\n                    Create many addresses for one mailbox.\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-uptime.png\">\n                    <span>Uptime</span>\n                    Rest assured with 99.9% uptime SLA\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-cloud.png\">\n                    <span>Cloud File Storage</span>\n                    Store and access files securely in the cloud\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-apps.png\">\n                    <span>Productivity Apps</span>\n                    Create & Edit MS Office files with online apps\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-migration.png\">\n                    <span>Self-Migration Tool</span>\n                    Easy self-service migration tool.\n                </div>\n                <div>\n                    <img src=\"../assets/img/marketconnect/ox/icon-margins.png\">\n                    <span>Great Margins</span>\n                    Low license and high RRP means increased margins\n                </div>\n            </div>\n        </div>\n    </div>\n    <div class=\"tab-pane\" id=\"pricing\" role=\"tabpanel\">\n        <div class=\"content-padded ox pricing\">\n            ";
if ($feed->isNotAvailable()) {
    echo "                <div class=\"pricing-login-overlay\">\n                    <p>To view pricing, you must first register or log in to your MarketConnect account.</p>\n                    <button type=\"button\" class=\"btn btn-default btn-sm btn-login\">Login</button>\n                    <button type=\"button\" class=\"btn btn-default btn-sm btn-register\">Create Account</button>\n                </div>\n            ";
}
echo "\n        <table class=\"table table-pricing\">\n            ";
$productInfo = $feed->getServicesByGroupId(WHMCS\MarketConnect\MarketConnect::SERVICE_OX);
$planNames = [];
$planFeatures = [];
if ($productInfo->isNotEmpty()) {
    foreach ($productInfo as $plan) {
        $planNames[] = $plan["display_name"];
        $planFeatures[$plan["id"]] = $promotionHelper->getPlanFeatures($plan["id"]);
    }
    echo "<tr><td class=\"no-border\"></td><th>" . implode("</th><th>", $planNames) . "</th></tr>";
    foreach (current($planFeatures) as $key => $value) {
        echo "<tr><td>" . $key . "</td>";
        foreach ($productInfo as $plan) {
            $feature = $planFeatures[$plan["id"]][$key];
            if (is_bool($feature)) {
                $feature = "<i class=\"fa fa-check\"></i>";
            }
            echo "<td> " . $feature . "</td>";
        }
        echo "</tr>";
    }
    foreach ($productInfo[0]["terms"] as $term) {
        $currentTerm = $term["term"];
        echo "<tr><td>Your Price</td>";
        foreach ($productInfo as $plan) {
            foreach ($plan["terms"] as $term) {
                if ($term["term"] == $currentTerm) {
                    echo "<td class=\"yourcost\"><strong>\$" . $term["price"] . "</strong></td>";
                }
            }
        }
        foreach ($productInfo[0]["terms"] as $term) {
            $currentTerm = $term["term"];
            echo "<tr><td>Recommended Retail Price</td>";
            foreach ($productInfo as $plan) {
                foreach ($plan["terms"] as $term) {
                    if ($term["term"] == $currentTerm) {
                        echo "<td>\$" . $term["recommendedRrp"] . " RRP</td>";
                    }
                }
            }
        }
    }
}
echo "        </table>\n\n        </div>\n    </div>\n    <div class=\"tab-pane\" id=\"faq\" role=\"tabpanel\">\n        <div class=\"content-padded ox faq\">\n\n            <h4>Why should I sell Email?</h4>\n            <p>Email is the most used business application on the planet; virtually every SMB needs it and utilizes it daily. As email needs grow, your customers are facing a decision: Remain on a dated (often free) email platform that no longer meets their needs OR move to a paid provider (Gmail/O365). OX App Suite helps fill the gap between these 2 choices.</p>\n\n            <h4>Will SMBs pay for Email?</h4>\n            <p>Simply put, \"YES!\" Email has become a crucial tool for practically every SMB. As such, they are expecting more than tiny mailboxes and poor spam/virus protection. Most SMBs are more than willing to pay for a quality, secure and reliable email service. Gmail and Office 365 are growing exponentially based on this principle - but now so can you!</p>\n\n            <h4>What messaging would be most effective for selling email to SMBs?</h4>\n            <p>Most SMBs are not super-technical businesses. They want a simple email address (@their-domain.com) they can rely on. Before you sell all the bells and whistles (of which App Suite has a ton!) be sure to cover their basic needs first: Uptime and reliability, followed closely by Premium Anti-Spam/Virus. Mailbox size, collaboration and Cloud File Storage are also increasing in importance these days.</p>\n\n            <h4>How much revenue/margin can I make on email?</h4>\n            <p>Of course that is completely up to you. Remember to add your company's special sauce to any App Suite Mailboxes you sell. Whether that be white glove migrations, 24x7 support and/or basic CRUD operations.</p>\n\n        </div>\n    </div>\n\n    <div class=\"tab-pane\" id=\"activate\" role=\"tabpanel\">\n        ";
$this->insert("shared/configuration-activate", ["currency" => $currency, "service" => $service, "firstBulletPoint" => "Offer all Open-Xchange Services", "availableForAllHosting" => true, "landingPageRoutePath" => routePath("store-product-group", $feed->getGroupSlug(WHMCS\MarketConnect\MarketConnect::SERVICE_OX)), "serviceOffering" => $serviceOffering, "billingCycles" => $billingCycles, "products" => $products, "serviceTerms" => $serviceTerms]);
echo "    </div>\n\n    <style>\n        .ox.features {\n            text-align: center;\n            display: flex;\n            flex-direction: column;\n        }\n        .ox.features img {\n            display: block;\n            margin: 0 auto;\n        }\n        .ox.features span {\n            display: block;\n            font-weight: bold;\n        }\n        .ox.pricing table td {\n            padding: 0 !important;\n            color: #043855;\n        }\n        .ox.pricing table td.no-border {\n            border: 0;\n        }\n        .ox.pricing table td:not(.no-border),\n        .ox.pricing table th {\n            width: 33.33%;\n            text-align: center;\n            border: 1px solid #e1eaf3;\n        }\n        .ox.pricing table th {\n            padding: 5px;\n            background-color: #3d82bb;\n            color: #fff;\n        }\n        .ox.pricing table tr td:first-child {\n            padding: 0 10px !important;\n            text-align: left;\n            font-size: 0.9em;\n            line-height: 21px;\n        }\n        .ox.pricing i {\n            color: #666;\n        }\n        .ox.pricing td.yourcost {\n            background-color: #beeaa0;\n        }\n        .ox.pricing table tr:nth-child(even) td:first-child {\n            background-color: #f3f7fa;\n        }\n        .ox.faq h4 {\n            color: #666;\n            font-size: 1.3em;\n            margin-bottom: 7px;\n        }\n        .ox.faq p {\n            font-size: 0.95em;\n        }\n        @media only screen and (min-width : 768px) {\n            .ox-features {\n                display: flex;\n                flex-direction: row;\n                flex-wrap: wrap;\n            }\n            .ox-features > div {\n                flex-basis: 20%;\n            }\n            .ox-features > div:nth-last-child(-n+4) {\n                flex-basis: 25%;\n            }\n        }\n    </style>\n";
$this->end();
