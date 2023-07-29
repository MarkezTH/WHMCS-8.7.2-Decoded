<?php

$this->layout("layouts/learn", $serviceOffering);
$comparePricesText = AdminLang::trans("marketConnect.threesixtymonitoring.pricing.comparePrices");
$comparePlansText = AdminLang::trans("marketConnect.threesixtymonitoring.pricing.comparePlans");
$plansMatrix = function ($planNames, $planFeatures) {
    $matrix = "<table class=\"table table-striped table-pricing\">";
    foreach (current($planFeatures) as $key => $value) {
        $label = $key;
        $matrix .= "<tr><td>" . $label . "</td>";
        foreach ($planFeatures as $plan) {
            $matrix .= "<td>" . $plan[$key] . "</td>";
        }
        $matrix .= "</tr>";
    }
    $matrix .= "</table>";
    return $matrix;
};
$pricingMatrix = function ($planNames, $billingTerms, $plansPricing) use($currency) {
    $matrix = "<table class=\"table table-striped table-pricing\">";
    foreach ($billingTerms as $currentTerm) {
        $costLabel = AdminLang::trans("billingcycles." . (new WHMCS\Billing\Cycles())->getNormalisedByMonths($currentTerm));
        $matrix .= "<tr><td>" . $costLabel . "</td>";
        foreach ($plansPricing as $plan) {
            foreach ($plan as $term) {
                if (!($term["term"] !== 100 && $term["term"] !== $currentTerm)) {
                    if ($term["term"] === 100) {
                        $term["price"] = 0;
                        $term["recommendedRrp"] = 0;
                    }
                    $price = (new WHMCS\View\Formatter\Price($term["price"], $currency))->toPrefixed();
                    $rrpStr = AdminLang::trans("marketConnect.threesixtymonitoring.pricing.RRP", [":price" => (new WHMCS\View\Formatter\Price($term["recommendedRrp"], $currency))->toPrefixed()]);
                    $matrix .= "<td>" . $price . "<br><span>" . $rrpStr . "</span></td>";
                }
            }
        }
        $matrix .= "</tr>";
    }
    $matrix .= "</table>";
    return $matrix;
};
$this->start("nav-tabs");
echo "    <li class=\"active\" role=\"presentation\">\n        <a aria-controls=\"about\" data-toggle=\"tab\" href=\"#about\" role=\"tab\">";
echo AdminLang::trans("marketConnect.threesixtymonitoring.learn.tab.about");
echo "</a>\n    </li>\n    <li role=\"presentation\">\n        <a aria-controls=\"features\" data-toggle=\"tab\" href=\"#features\" role=\"tab\">";
echo AdminLang::trans("marketConnect.threesixtymonitoring.learn.tab.features");
echo "</a>\n    </li>\n    <li role=\"presentation\">\n        <a aria-controls=\"pricing\" data-toggle=\"tab\" href=\"#lite\" role=\"tab\">";
echo AdminLang::trans("marketConnect.threesixtymonitoring.learn.tab.lite");
echo "</a>\n    </li>\n    <li role=\"presentation\">\n        <a aria-controls=\"pricing\" data-toggle=\"tab\" href=\"#pricing\" role=\"tab\">";
echo AdminLang::trans("marketConnect.threesixtymonitoring.learn.tab.pricing");
echo "</a>\n    </li>\n    <li role=\"presentation\">\n        <a aria-controls=\"faq\" data-toggle=\"tab\" href=\"#faq\" role=\"tab\">";
echo AdminLang::trans("marketConnect.threesixtymonitoring.learn.tab.faq");
echo "</a>\n    </li>\n";
$this->end();
$this->start("content-tabs");
echo "    <div class=\"tab-pane active\" id=\"about\" role=\"tabpanel\">\n        <div class=\"content-padded threesixtymonitoring about\">\n            <h3>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.about.title");
echo "</h3>\n            <h4>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.about.subtitle");
echo "</h4>\n            <br>\n            <div class=\"col-sm-4\">\n                <img src=\"";
echo $WEB_ROOT;
echo "/assets/img/marketconnect/threesixtymonitoring/header-screens.png\">\n            </div>\n            <div class=\"col-sm-8\">\n                <p>\n                    ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.about.p1");
echo "                    <br><br>\n                    ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.about.p2");
echo "                    <br><br>\n                    ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.about.p3");
echo "                    <br>\n                    <small>* ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.about.asterisk");
echo "</small>\n                </p>\n            </div>\n        </div>\n    </div>\n    <div class=\"tab-pane\" id=\"features\" role=\"tabpanel\">\n        <div class=\"content-padded threesixtymonitoring features\">\n            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.p1");
echo "            <h4>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.h1");
echo "</h4>\n            <div class=\"row\">\n                <div class=\"col-md-6\">\n                    <div class=\"feature-wrapper\">\n                        <i><img src=\"";
echo $WEB_ROOT;
echo "/assets/img/marketconnect/threesixtymonitoring/website_0004_icon.png\"></i>\n                        <div class=\"content\">\n                            <h4>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f1.title");
echo "</h4>\n                            <p>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f1.p1");
echo "</p>\n                        </div>\n                    </div>\n                </div>\n                <div class=\"col-md-6\">\n                    <div class=\"feature-wrapper\">\n                        <i><img src=\"";
echo $WEB_ROOT;
echo "/assets/img/marketconnect/threesixtymonitoring/website_0012_icon.png\"></i>\n                        <div class=\"content\">\n                            <h4>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f2.title");
echo "</h4>\n                            <p>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f2.p1");
echo "</p>\n                        </div>\n                    </div>\n                </div>\n            </div>\n            <div class=\"row\">\n                <div class=\"col-md-6\">\n                    <div class=\"feature-wrapper\">\n                        <i><img src=\"";
echo $WEB_ROOT;
echo "/assets/img/marketconnect/threesixtymonitoring/server_0002_icon.png\"></i>\n                        <div class=\"content\">\n                            <h4>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f3.title");
echo "</h4>\n                            <p>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f3.p1");
echo "</p>\n                        </div>\n                    </div>\n                </div>\n                <div class=\"col-md-6\">\n                    <div class=\"feature-wrapper\">\n                        <i><img src=\"";
echo $WEB_ROOT;
echo "/assets/img/marketconnect/threesixtymonitoring/website_0007_icon.png\"></i>\n                        <div class=\"content\">\n                            <h4>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f4.title");
echo "</h4>\n                            <p>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.features.f4.p1");
echo "</p>\n                        </div>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n\n    <div class=\"tab-pane\" id=\"lite\" role=\"tabpanel\">\n        <div class=\"content-padded threesixtymonitoring lite\">\n            <h3>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.title");
echo "</h3>\n            <h4>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p1");
echo "</h4>\n            <p>\n                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p2.p");
echo "                <ul>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p2.i1");
echo "</li>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p2.i2");
echo "</li>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p2.i3");
echo "</li>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p2.i4");
echo "</li>\n                </ul>\n                <br>\n                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p4.p");
echo "                <ul>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p4.i1");
echo "</li>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p4.i2");
echo "</li>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p4.i3");
echo "</li>\n                    <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.p4.i4");
echo "</li>\n                </ul>\n            </p>\n            <p>* ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.lite.asterisk");
echo "</p>\n        </div>\n    </div>\n\n    <div class=\"tab-pane\" id=\"pricing\" role=\"tabpanel\">\n        <div class=\"content-padded threesixtymonitoring pricing\">\n            ";
if ($feed->isNotAvailable()) {
    echo "                <div class=\"pricing-login-overlay\">\n                    <p>";
    echo AdminLang::trans("marketConnect.loginForPricing");
    echo "</p>\n                    <button type=\"button\" class=\"btn btn-default btn-sm btn-login\">";
    echo AdminLang::trans("marketConnect.login");
    echo "</button> <button type=\"button\" class=\"btn btn-default btn-sm btn-register\">";
    echo AdminLang::trans("marketConnect.createAccount");
    echo "</button>\n                </div>\n            ";
} else {
    $productInfo = $feed->getServicesByGroupId(WHMCS\MarketConnect\MarketConnect::SERVICE_THREESIXTYMONITORING);
    $planNames = ["websites" => [], "servers" => []];
    $planFeatures = ["websites" => [], "servers" => []];
    $billingTerms = [];
    $plansPricing = ["websites" => [], "servers" => []];
    if ($productInfo->isNotEmpty()) {
        foreach ($productInfo[1]["terms"] ?? [] as $billingTerm) {
            $billingTerms[] = $billingTerm["term"];
        }
        foreach ($productInfo as $plan) {
            $name = $plan["display_name"];
            $terms = $plan["terms"];
            $translatedFeature = $promotionHelper->getTranslatedFeatures($plan["id"]);
            if ($promotionHelper->getPlanFeature("servers", $plan["id"]) == 0) {
                $planNames["websites"][] = $name;
                $planFeatures["websites"][$plan["id"]] = $translatedFeature;
                $plansPricing["websites"][$plan["id"]] = $terms;
            } else {
                $planNames["servers"][] = $name;
                $planFeatures["servers"][$plan["id"]] = $translatedFeature;
                $plansPricing["servers"][$plan["id"]] = $terms;
            }
        }
    }
    unset($productInfo);
    echo "            <div class=\"tabbable\">\n                <ul class=\"nav nav-tabs\">\n                    <li role=\"presentation\" class=\"active\">\n                        <a href=\"#pricingSites\" aria-controls=\"pricingSites\" role=\"tab\" data-toggle=\"tab\">\n                            ";
    echo AdminLang::trans("marketConnect.threesixtymonitoring.pricing.siteMonitoring");
    echo "                        </a>\n                    </li>\n                    <li role=\"presentation\">\n                        <a href=\"#pricingServers\" aria-controls=\"pricingServers\" role=\"tab\" data-toggle=\"tab\">\n                            ";
    echo AdminLang::trans("marketConnect.threesixtymonitoring.pricing.serverMonitoring");
    echo "                        </a>\n                    </li>\n                </ul>\n                <div class=\"tab-content\">\n                    <div role=\"tabpanel\" class=\"tab-pane active fade in\" id=\"pricingSites\">\n                        ";
    echo "<table class=\"table table-pricing table-pricing-header\"><tr><th></th><th>" . implode("</th><th>", $planNames["websites"]) . "</th></tr></table>";
    echo "                        <div class=\"div-plans-wrapper\"  style=\"display:none;\">\n                            ";
    echo $plansMatrix($planNames["websites"], $planFeatures["websites"]);
    echo "                        </div>\n                        <div class=\"div-pricing-wrapper\">\n                            ";
    echo $pricingMatrix($planNames["websites"], $billingTerms, $plansPricing["websites"]);
    echo "                        </div>\n                    </div>\n                    <div role=\"tabpanel\" class=\"tab-pane fade\" id=\"pricingServers\">\n                        ";
    echo "<table class=\"table table-pricing table-pricing-header\"><tr><th></th><th>" . implode("</th><th>", $planNames["servers"]) . "</th></tr></table>";
    echo "                        <div class=\"div-plans-wrapper\"  style=\"display:none;\">\n                            ";
    echo $plansMatrix($planNames["servers"], $planFeatures["servers"]);
    echo "                        </div>\n                        <div class=\"div-pricing-wrapper\">\n                            ";
    echo $pricingMatrix($planNames["servers"], $billingTerms, $plansPricing["servers"]);
    echo "                        </div>\n                    </div>\n                </div>\n                <div>\n                    <button class=\"btn btn-default btn-toggle-comparison\">";
    echo $comparePlansText;
    echo "</button>\n                </div>\n            </div>\n            ";
    unset($planNames);
    unset($planFeatures);
    unset($billingTerms);
    unset($plansPricing);
}
echo "        </div>\n    </div>\n\n    <div class=\"tab-pane\" id=\"faq\" role=\"tabpanel\">\n        <div class=\"content-padded threesixtymonitoring faq\">\n            <h3>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.h1");
echo "</h3>\n            <div class=\"panel-group faq\" id=\"accordion\" role=\"tablist\" aria-multiselectable=\"true\">\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingOne\">\n                        <h4 class=\"panel-title\">\n                            <a role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseOne\" aria-expanded=\"true\" aria-controls=\"collapseOne\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q1");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseOne\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingOne\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1a");
echo "                            <br><br>\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1b.p1");
echo "                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1b.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1b.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1b.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1b.i4");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1b.i5");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a1b.i6");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingTwo\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseTwo\" aria-expanded=\"false\" aria-controls=\"collapseTwo\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q2");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseTwo\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingTwo\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a2a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingThree\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseThree\" aria-expanded=\"false\" aria-controls=\"collapseThree\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q3");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseThree\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingThree\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a3a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingFour\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseFour\" aria-expanded=\"false\" aria-controls=\"collapseFour\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q4");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseFour\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingFour\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a4a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingFive\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseFive\" aria-expanded=\"false\" aria-controls=\"collapseFive\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q5");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseFive\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingFive\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a5a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingSix\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseSix\" aria-expanded=\"false\" aria-controls=\"collapseSix\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q6");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseSix\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingSix\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a6a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingSeven\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseSeven\" aria-expanded=\"false\" aria-controls=\"collapseSeven\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q7");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseSeven\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingSeven\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a7a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingEight\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseEight\" aria-expanded=\"false\" aria-controls=\"collapseEight\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q8");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseEight\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingEight\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a8a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingNine\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseNine\" aria-expanded=\"false\" aria-controls=\"collapseNine\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q9");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseNine\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingNine\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a9a");
echo "                            <br><br>\n                            * ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a9asterisk");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingTen\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseTen\" aria-expanded=\"false\" aria-controls=\"collapseTen\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q10");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseTen\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingTen\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a10a.p1");
echo "                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a10a.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a10a.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a10a.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a10a.i4");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a10a.i5");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingEleven\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseEleven\" aria-expanded=\"false\" aria-controls=\"collapseEleven\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q11");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseEleven\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingEleven\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a11a");
echo "                            <br><br>\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a11b.p1");
echo "                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a11b.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a11b.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a11b.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a11b.i4");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingTwelve\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseTwelve\" aria-expanded=\"false\" aria-controls=\"collapseTwelve\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q12");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseTwelve\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingTwelve\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a12a");
echo "                        </div>\n                    </div>\n                </div>\n                <div class=\"panel panel-default\">\n                    <div class=\"panel-heading\" role=\"tab\" id=\"headingThirteen\">\n                        <h4 class=\"panel-title\">\n                            <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseThirteen\" aria-expanded=\"false\" aria-controls=\"collapseThirteen\">\n                                ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.q13");
echo "                            </a>\n                        </h4>\n                    </div>\n                    <div id=\"collapseThirteen\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingThirteen\">\n                        <div class=\"panel-body\">\n                            ";
echo AdminLang::trans("marketConnect.threesixtymonitoring.faq.a13a");
echo "                        </div>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n\n    <div class=\"tab-pane\" id=\"activate\" role=\"tabpanel\">\n        ";
$this->insert("shared/configuration-activate", ["currency" => $currency, "service" => $service, "mcServiceSlug" => $mcServiceSlug, "firstBulletPoint" => "Offer all 360 Monitoring Services", "availableForAllHosting" => true, "landingPageRoutePath" => routePath("store-product-group", $feed->getGroupSlug(WHMCS\MarketConnect\MarketConnect::SERVICE_THREESIXTYMONITORING)), "serviceOffering" => $serviceOffering, "billingCycles" => $billingCycles, "products" => $products, "serviceTerms" => $serviceTerms]);
echo "    </div>\n\n<style>\n.threesixtymonitoring.about img {\n    width: 100%;\n}\n.threesixtymonitoring ul {\n    list-style: none;\n    margin: 0;\n    padding: 0;\n}\n.threesixtymonitoring.lite li,\n.threesixtymonitoring.faq li,\n.threesixtymonitoring.about li {\n    margin-left: 32px;\n    padding-bottom: 7px;\n}\n.threesixtymonitoring.lite li::before,\n.threesixtymonitoring.faq li::before,\n.threesixtymonitoring.about li::before {\n    content: \"\\2022\";\n    color: #ccc;\n    font-weight: bold;\n    padding-right: 12px;\n    padding-bottom: 10px;\n    margin-left: -20px;\n    font-size: 22px;\n    line-height: 10px;\n}\n.threesixtymonitoring.features img {\n    display: block;\n    max-width: 70px;\n}\n.threesixtymonitoring .icons img {\n    margin: 0 0 5px 0;\n    max-height: 50px;\n}\n.threesixtymonitoring .feature-wrapper {\n    margin: 0 0 15px 0;\n}\n.threesixtymonitoring .feature-wrapper i {\n    float: left;\n    font-size: 4em;\n}\n.threesixtymonitoring .feature-wrapper .content {\n    margin-left: 100px;\n}\n.threesixtymonitoring .feature-wrapper p {\n    font-size: 0.9em;\n}\n.threesixtymonitoring.features .features-item span {\n    font-size: 2.0em;\n}\n.threesixtymonitoring .nav-tabs li {\n    width: 50%;\n    text-align: center;\n}\n.threesixtymonitoring.pricing table {\n    table-layout: fixed;\n    width: 100%;\n}\n.threesixtymonitoring .table-pricing {\n    font-size: 0.9em;\n    line-height: initial;\n}\n.threesixtymonitoring .table-pricing-header {\n    margin-bottom: initial;\n}\n.threesixtymonitoring.pricing td:first-child {\n    text-align: left;\n    white-space: nowrap;\n}\n.threesixtymonitoring .btn-toggle-comparison {\n    color: #ce3b56;\n    width: 100%;\n    text-align: center;\n    border-color: #ce3b56;\n}\n.threesixtymonitoring .btn-toggle-comparison:focus {\n    outline: none;\n    box-shadow: none;\n}\n.threesixtymonitoring.pricing span {\n    font-size: 0.875em;\n    font-weight: bold;\n    white-space: nowrap;\n}\n.threesixtymonitoring.reviews .testimonial .user img {\n    float: left;\n    padding-right: 15px;\n    max-width: 50px;\n}\n.threesixtymonitoring.lite h4 {\n    font-size: 16px;\n    color: #444;\n    font-weight: 600;\n}\n.threesixtymonitoring.lite h4,\n.threesixtymonitoring.lite p {\n    margin-bottom: 10px;\n}\n.threesixtymonitoring .faq .panel {\n    border: 0;\n}\n.threesixtymonitoring .faq .panel-heading {\n    padding: 10px 14px;\n    background: transparent;\n}\n.threesixtymonitoring .faq h4 {\n    margin: 0;\n    padding: 0;\n    font-size: 15px;\n}\n.threesixtymonitoring .faq h4 a {\n    color: #222;\n    font-weight: 500;\n}\n.threesixtymonitoring .faq .panel-body {\n    padding: 3px 14px 10px;\n    color: #555;\n    border: 0;\n    font-weight: 300;\n}\n.threesixtymonitoring.faq {\n    max-height: 400px;\n    overflow: auto;\n}\n</style>\n\n<script>\n    jQuery('.btn-toggle-comparison').on('click', function() {\n        var comparePricesText = '";
echo $comparePricesText;
echo "';\n        var comparePlansText = '";
echo $comparePlansText;
echo "';\n        var plansWrapper = jQuery('.div-plans-wrapper');\n        var pricingWrapper = jQuery('.div-pricing-wrapper');\n        var clickTarget = jQuery(this);\n        plansWrapper.slideToggle();\n        pricingWrapper.slideToggle();\n        if (clickTarget.text() === comparePricesText) {\n            clickTarget.text(comparePlansText);\n        } else {\n            clickTarget.text(comparePricesText);\n        }\n    });\n</script>\n";
$this->end();
