<?php

$this->layout("layouts/learn", $serviceOffering);
$this->start("nav-tabs");
echo "<li class=\"active\" role=\"presentation\">\n    <a aria-controls=\"about\" data-toggle=\"tab\" href=\"#about\" role=\"tab\">";
echo AdminLang::trans("marketConnect.xoviNow.learn.tab.about");
echo "</a>\n</li>\n<li role=\"presentation\">\n    <a aria-controls=\"features\" data-toggle=\"tab\" href=\"#features\" role=\"tab\">";
echo AdminLang::trans("marketConnect.xoviNow.learn.tab.features");
echo "</a>\n</li>\n<li role=\"presentation\">\n    <a aria-controls=\"pricing\" data-toggle=\"tab\" href=\"#pricing\" role=\"tab\">";
echo AdminLang::trans("marketConnect.xoviNow.learn.tab.pricing");
echo "</a>\n</li>\n<li role=\"presentation\">\n    <a aria-controls=\"faq\" data-toggle=\"tab\" href=\"#faq\" role=\"tab\">";
echo AdminLang::trans("marketConnect.xoviNow.learn.tab.faq");
echo "</a>\n</li>\n\n";
$this->end();
$this->start("content-tabs");
echo "<div class=\"tab-pane active\" id=\"about\" role=\"tabpanel\">\n    <div class=\"content-padded xovinow about\">\n        <h4>";
echo AdminLang::trans("marketConnect.xoviNow.about.h1");
echo "</h4>\n        <p>\n            ";
echo AdminLang::trans("marketConnect.xoviNow.about.p1");
echo "            <br><br>\n            ";
echo AdminLang::trans("marketConnect.xoviNow.about.p2");
echo "            <br><br>\n            ";
echo AdminLang::trans("marketConnect.xoviNow.about.p3");
echo "            <br><br>\n            ";
echo AdminLang::trans("marketConnect.xoviNow.about.p4");
echo "            <br><br>\n            ";
echo AdminLang::trans("marketConnect.xoviNow.about.p5");
echo "        </p>\n        <h4>";
echo AdminLang::trans("marketConnect.xoviNow.about.h2");
echo "</h4>\n        <p>\n            ";
echo AdminLang::trans("marketConnect.xoviNow.about.p6");
echo "            <br><br>\n            ";
echo AdminLang::trans("marketConnect.xoviNow.about.p7");
echo "        </p>\n    </div>\n</div>\n    <div class=\"tab-pane\" id=\"features\" role=\"tabpanel\">\n        <div class=\"content-padded xovinow features\">\n            <div class=\"row\">\n                <div class=\"col-sm-3 feature-menu\">\n                    <a class=\"feature-menu-item shown\" href=\"#\" data-name=\"keywords\">\n                        <strong>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.title");
echo "</strong>\n                    </a>\n                    <a class=\"feature-menu-item\" href=\"#\" data-name=\"advisor\">\n                        <strong>";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.title");
echo "</strong>\n                    </a>\n                    <a class=\"feature-menu-item\" href=\"#\" data-name=\"rank-tracker\">\n                        <strong>";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.title");
echo "</strong>\n                    </a>\n                    <a class=\"feature-menu-item\" href=\"#\" data-name=\"site-audit\">\n                        <strong>";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.title");
echo "</strong>\n                    </a>\n                    <a class=\"feature-menu-item\" href=\"#\" data-name=\"text-optimizer\">\n                        <strong>";
echo AdminLang::trans("marketConnect.xoviNow.features.f5.title");
echo "</strong>\n                    </a>\n                    <a class=\"feature-menu-item\" href=\"#\" data-name=\"benchmarking\">\n                        <strong>";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.title");
echo "</strong>\n                    </a>\n                </div>\n                <div class=\"col-sm-9 feature-info\">\n                    <div class=\"row shown feature-info-item\" data-name=\"keywords\">\n                        <div class=\"col-sm-12\">\n                            <h3>\n                                ";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.title");
echo "<br>\n                                <small>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.tag");
echo "</small>\n                            </h3>\n                        </div>\n                        <div class=\"col-sm-5\">\n                            <img src=\"../assets/img/marketconnect/xovinow/keywords.svg\">\n                        </div>\n                        <div class=\"col-sm-7 margin-top-bottom-25\">\n                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.i4");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.i5");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f1.i6");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                    <div class=\"row feature-info-item\" data-name=\"advisor\">\n                        <div class=\"col-sm-12\">\n                            <h3>\n                                ";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.title");
echo "<br>\n                                <small>";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.tag");
echo "</small>\n                            </h3>\n                        </div>\n                        <div class=\"col-sm-5\">\n                            <img src=\"../assets/img/marketconnect/xovinow/advisor.svg\">\n                        </div>\n                        <div class=\"col-sm-7 margin-top-bottom-25\">\n                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.i4");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f2.i5");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                    <div class=\"row feature-info-item\" data-name=\"rank-tracker\">\n                        <div class=\"col-sm-12\">\n                            <h3>\n                                ";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.title");
echo "<br>\n                                <small>";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.tag");
echo "</small>\n                            </h3>\n                        </div>\n                        <div class=\"col-sm-5\">\n                            <img src=\"../assets/img/marketconnect/xovinow/rank-tracker.svg\">\n                        </div>\n                        <div class=\"col-sm-7 margin-top-bottom-25\">\n                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.i4");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f3.i5");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                    <div class=\"row feature-info-item\" data-name=\"site-audit\">\n                        <div class=\"col-sm-12\">\n                            <h3>\n                                ";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.title");
echo "<br>\n                                <small>";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.tag");
echo "</small>\n                            </h3>\n                        </div>\n                        <div class=\"col-sm-5\">\n                            <img src=\"../assets/img/marketconnect/xovinow/site-audit.svg\">\n                        </div>\n                        <div class=\"col-sm-7 margin-top-bottom-25\">\n                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.i4");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f4.i5");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                    <div class=\"row feature-info-item\" data-name=\"text-optimizer\">\n                        <div class=\"col-sm-12\">\n                            <h3>\n                                ";
echo AdminLang::trans("marketConnect.xoviNow.features.f5.title");
echo "<br>\n                                <small>";
echo AdminLang::trans("marketConnect.xoviNow.features.f5.tag");
echo "</small>\n                            </h3>\n                        </div>\n                        <div class=\"col-sm-5\">\n                            <img src=\"../assets/img/marketconnect/xovinow/text-optimizer.svg\">\n                        </div>\n                        <div class=\"col-sm-7 margin-top-bottom-25\">\n                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f5.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f5.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f5.i3");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                    <div class=\"row feature-info-item\" data-name=\"benchmarking\">\n                        <div class=\"col-sm-12\">\n                            <h3>\n                                ";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.title");
echo "<br>\n                                <small>";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.tag");
echo "</small>\n                            </h3>\n                        </div>\n                        <div class=\"col-sm-5\">\n                            <img src=\"../assets/img/marketconnect/xovinow/benchmarking.svg\">\n                        </div>\n                        <div class=\"col-sm-7 margin-top-bottom-25\">\n                            <ul>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.i1");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.i2");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.i3");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.i4");
echo "</li>\n                                <li>";
echo AdminLang::trans("marketConnect.xoviNow.features.f6.i5");
echo "</li>\n                            </ul>\n                        </div>\n                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n<div class=\"tab-pane\" id=\"pricing\" role=\"tabpanel\">\n    <div class=\"content-padded xovinow pricing\">\n        ";
if ($feed->isNotAvailable()) {
    echo "            <div class=\"pricing-login-overlay\">\n                <p>";
    echo AdminLang::trans("marketConnect.loginForPricing");
    echo "</p>\n                <button type=\"button\" class=\"btn btn-default btn-sm btn-login\">";
    echo AdminLang::trans("marketConnect.login");
    echo "</button> <button type=\"button\" class=\"btn btn-default btn-sm btn-register\">";
    echo AdminLang::trans("marketConnect.createAccount");
    echo "</button>\n            </div>\n        ";
}
echo "        <table class=\"table table-pricing\">\n            ";
$featureLabelMap = ["Projects" => "f1", "Full-Access Accounts" => "f2", "Read-Only Accounts" => "f3", "Competitor Benchmarking" => "f4", "Competitors per project" => "f5", "Keyword Research" => "f6", "Rank Tracker" => "f7", "Keyword crawls" => "f8", "Keyword check" => "f9", "Site Audit" => "f10", "Pages to crawl" => "f11", "SEO Advisor" => "f12", "SEO Text Optimizer" => "f13"];
$featureValueMap = ["xovinow_starter" => ["Keyword check" => "checkStarter", "Pages to crawl" => "pagesStarter"], "xovinow_pro" => ["Keyword check" => "checkPro", "Pages to crawl" => "pagesPro"]];
$productInfo = $feed->getServicesByGroupId(WHMCS\MarketConnect\MarketConnect::SERVICE_XOVINOW);
$planPricing = $feed->getPricingMatrix([WHMCS\MarketConnect\Promotion\Service\XoviNow::XOVINOW_STARTER, WHMCS\MarketConnect\Promotion\Service\XoviNow::XOVINOW_PROFESSIONAL]);
$planFeatures = [];
if ($productInfo->isNotEmpty()) {
    foreach ($productInfo as $plan) {
        $planFeatures[$plan["id"]] = $promotionHelper->getPlanFeatures($plan["id"]);
    }
    echo "<tr><th>" . AdminLang::trans("marketConnect.xoviNow.pricing.feature") . "</th>";
    $pricing = [];
    foreach ($productInfo[0]["terms"] as $term) {
        $currentTerm = $term["term"];
        foreach ($productInfo as $plan) {
            echo "<th>" . AdminLang::trans("marketConnect.xoviNow.pricing." . strtolower($plan["display_name"])) . "<br></th>";
            foreach ($plan["terms"] as $term) {
                $rrp = new WHMCS\View\Formatter\Price($term["recommendedRrp"], $currency);
                $price = new WHMCS\View\Formatter\Price($term["price"], $currency);
                $pricing[$term["term"]] .= "<td>" . AdminLang::trans("marketConnect.xoviNow.pricing.yourCost", [":price" => $price->toPrefixed()]) . "<br><small>" . AdminLang::trans("marketConnect.xoviNow.pricing.RRP", [":price" => $rrp->toPrefixed()]) . "</small></td>";
            }
        }
        echo "</tr>";
        foreach (array_keys(current($planFeatures)) as $featureKey) {
            echo "<tr><td>" . AdminLang::trans("marketConnect.xoviNow.pricing." . $featureLabelMap[$featureKey]) . "</td>";
            foreach ($productInfo as $plan) {
                $feature = $planFeatures[$plan["id"]][$featureKey];
                if (is_bool($feature)) {
                    $feature = "<i class=\"icon-yes fas fa-check\"></i>";
                }
                if (!empty($featureValueMap[$plan["id"]][$featureKey])) {
                    $feature = AdminLang::trans("marketConnect.xoviNow.pricing." . $featureValueMap[$plan["id"]][$featureKey]);
                }
                echo "<td> " . $feature . "</td>";
            }
            echo "</tr>";
        }
        $cycles = new WHMCS\Billing\Cycles();
        foreach ($pricing as $term => $price) {
            echo "<tr><td>" . AdminLang::trans("billingcycles." . $cycles->getNormalisedByMonths($term)) . "</td>" . $price . "</tr>";
        }
    }
}
echo "        </table>\n    </div>\n</div>\n<div class=\"tab-pane\" id=\"faq\" role=\"tabpanel\">\n    <div class=\"content-padded xovinow faq\">\n        <div class=\"panel-group faq\" id=\"accordion\" role=\"tablist\" aria-multiselectable=\"true\">\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingOne\">\n                    <h4 class=\"panel-title\">\n                        <a role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseOne\" aria-expanded=\"true\" aria-controls=\"collapseOne\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q1");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseOne\" class=\"panel-collapse collapse in\" role=\"tabpanel\" aria-labelledby=\"headingOne\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a1a");
echo "                        <br>\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a1b");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingTwo\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseTwo\" aria-expanded=\"false\" aria-controls=\"collapseTwo\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q2");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseTwo\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingTwo\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a2a");
echo "                        <br>\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a2b");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingThree\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseThree\" aria-expanded=\"false\" aria-controls=\"collapseThree\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q3");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseThree\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingThree\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a3a");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingFour\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseFour\" aria-expanded=\"false\" aria-controls=\"collapseFour\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q4");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseFour\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingFour\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a4a");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingFive\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseFive\" aria-expanded=\"false\" aria-controls=\"collapseFive\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q5");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseFive\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingFive\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a5a");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingSix\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseSix\" aria-expanded=\"false\" aria-controls=\"collapseSix\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q6");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseSix\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingSix\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a6a");
echo "                        <br>\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a6b");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingSeven\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseSeven\" aria-expanded=\"false\" aria-controls=\"collapseSeven\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q7");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseSeven\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingSeven\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a7a");
echo "                        <br>\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a7b");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingEight\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseEight\" aria-expanded=\"false\" aria-controls=\"collapseEight\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q8");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseEight\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingEight\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a8a");
echo "                        <br>\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a8b");
echo "                        <br>\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a8c");
echo "                    </div>\n                </div>\n            </div>\n            <div class=\"panel panel-default\">\n                <div class=\"panel-heading\" role=\"tab\" id=\"headingNine\">\n                    <h4 class=\"panel-title\">\n                        <a class=\"collapsed\" role=\"button\" data-toggle=\"collapse\" data-parent=\"#accordion\" href=\"#collapseNine\" aria-expanded=\"false\" aria-controls=\"collapseNine\">\n                            ";
echo AdminLang::trans("marketConnect.xoviNow.faq.q9");
echo "                        </a>\n                    </h4>\n                </div>\n                <div id=\"collapseNine\" class=\"panel-collapse collapse\" role=\"tabpanel\" aria-labelledby=\"headingNine\">\n                    <div class=\"panel-body\">\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a9a");
echo "                        <br>\n                        ";
echo AdminLang::trans("marketConnect.xoviNow.faq.a9b");
echo "                    </div>\n                </div>\n            </div>\n        </div>\n    </div>\n</div>\n<div class=\"tab-pane\" id=\"activate\" role=\"tabpanel\">\n    ";
$this->insert("shared/configuration-activate", ["currency" => $currency, "service" => $service, "firstBulletPoint" => AdminLang::trans("marketConnect.xoviNow.offer"), "availableForAllHosting" => true, "landingPageRoutePath" => routePath("store-product-group", $feed->getGroupSlug(WHMCS\MarketConnect\MarketConnect::SERVICE_XOVINOW)), "serviceOffering" => $serviceOffering, "billingCycles" => $billingCycles, "products" => $products, "serviceTerms" => $serviceTerms]);
echo "</div>\n";
$this->end();
