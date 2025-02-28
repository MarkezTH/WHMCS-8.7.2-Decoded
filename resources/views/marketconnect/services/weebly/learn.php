<?php

$this->layout("layouts/learn", $serviceOffering);
$this->start("nav-tabs");
echo "<li class=\"active\" role=\"presentation\">\n    <a aria-controls=\"home\" data-toggle=\"tab\" href=\"#about\" role=\"tab\">\n        ";
echo AdminLang::trans("marketConnect.weebly.learn.tab.about");
echo "    </a>\n</li>\n<li role=\"presentation\">\n    <a aria-controls=\"features\" data-toggle=\"tab\" href=\"#features\" role=\"tab\">\n        ";
echo AdminLang::trans("marketConnect.weebly.learn.tab.features");
echo "    </a>\n</li>\n<li role=\"presentation\">\n    <a aria-controls=\"freeplan\" data-toggle=\"tab\" href=\"#freeplan\" role=\"tab\">\n        ";
echo AdminLang::trans("marketConnect.weebly.learn.tab.freeplan");
echo "    </a>\n</li>\n<li role=\"presentation\">\n    <a aria-controls=\"pricing\" data-toggle=\"tab\" href=\"#pricing\" role=\"tab\">\n        ";
echo AdminLang::trans("marketConnect.weebly.learn.tab.pricing");
echo "    </a>\n</li>\n<li role=\"presentation\">\n    <a aria-controls=\"faq\" data-toggle=\"tab\" href=\"#faq\" role=\"tab\">\n        ";
echo AdminLang::trans("marketConnect.weebly.learn.tab.faq");
echo "    </a>\n</li>\n";
$this->end();
$this->start("content-tabs");
echo "<div class=\"tab-pane active\" id=\"about\" role=\"tabpanel\">\n    <div class=\"content-padded\">\n        <h3>";
echo AdminLang::trans("marketConnect.weebly.learn.headline");
echo "</h3>\n        <h4>";
echo AdminLang::trans("marketConnect.weebly.learn.tagline");
echo "</h4>\n\n        <img class=\"pull-left\" src=\"../assets/img/marketconnect/weebly/website-builder-screen.png\" style=\"padding-right:30px;padding-top:20px;\" width=\"320\">\n\n        <br>\n\n        <div style=\"font-size:1.1em;font-weight:300;\">\n        <p>";
echo AdminLang::trans("marketConnect.weebly.learn.description1");
echo "</p>\n        <p>";
echo AdminLang::trans("marketConnect.weebly.learn.description2");
echo "</p>\n        <p>";
echo AdminLang::trans("marketConnect.weebly.learn.description3");
echo "</p>\n        </div>\n\n        <p>\n            <br>\n            <small>\n                <strong>";
echo AdminLang::trans("marketConnect.weebly.learn.aboutWeebly");
echo "</strong>\n                <br>\n                ";
echo AdminLang::trans("marketConnect.weebly.learn.aboutWeeblyDescription");
echo "            </small>\n        </p>\n    </div>\n</div>\n<div class=\"tab-pane\" id=\"features\" role=\"tabpanel\">\n    <div class=\"content-padded weebly-features\">\n\n        <div class=\"row\">\n            ";
foreach ($serviceOffering["features"] as $feature) {
    echo "                <div class=\"col-lg-3 col-md-4 col-sm-6\">\n                    <div class=\"feature\">\n                        <div class=\"icon\">\n                            <img src=\"../assets/img/marketconnect/weebly/icons/";
    echo $feature;
    echo ".png\">\n                        </div>\n                        <h4>\n                            ";
    echo AdminLang::trans("marketConnect.weebly.learn.features." . $feature);
    echo "                        </h4>\n                        <p>\n                            ";
    echo AdminLang::trans("marketConnect.weebly.learn.features." . $feature . "Description");
    echo "                        </p>\n                    </div>\n                </div>\n            ";
}
echo "        </div>\n\n        <p class=\"text-center\">\n            <a href=\"https://www.weebly.com/features\" target=\"_blank\" class=\"btn btn-default btn-sm\">\n                ";
echo AdminLang::trans("marketConnect.weebly.learn.weeblyCom");
echo "            </a>\n        </p>\n\n    </div>\n</div>\n<div class=\"tab-pane\" id=\"freeplan\" role=\"tabpanel\">\n    <div class=\"content-padded weebly faq\">\n        <h3>";
echo AdminLang::trans("marketConnect.weebly.learn.free.headline");
echo "</h3>\n        <h4>";
echo AdminLang::trans("marketConnect.weebly.learn.free.subheadline");
echo "</h4>\n        <p>";
echo AdminLang::trans("marketConnect.weebly.learn.free.intro");
echo "</p>\n        <br>\n        <div class=\"row weebly-free\">\n            <div class=\"col-sm-3\">\n                <i class=\"fas fa-file-alt\"></i>\n                <strong>";
echo AdminLang::trans("marketConnect.weebly.learn.free.unlimitedPages");
echo "</strong><br>\n                ";
echo AdminLang::trans("marketConnect.weebly.learn.free.unlimitedPagesInfo");
echo "            </div>\n            <div class=\"col-sm-3\">\n                <i class=\"fas fa-paint-brush\"></i>\n                <strong>";
echo AdminLang::trans("marketConnect.weebly.learn.free.themes");
echo "</strong><br>\n                ";
echo AdminLang::trans("marketConnect.weebly.learn.free.themesInfo");
echo "            </div>\n            <div class=\"col-sm-3\">\n                <i class=\"fas fa-database\"></i>\n                <strong>";
echo AdminLang::trans("marketConnect.weebly.learn.free.storage");
echo "</strong><br>\n                ";
echo AdminLang::trans("marketConnect.weebly.learn.free.storageInfo");
echo "            </div>\n            <div class=\"col-sm-3\">\n                <i class=\"fas fa-wrench\"></i>\n                <strong>";
echo AdminLang::trans("marketConnect.weebly.learn.free.tools");
echo "</strong><br>\n                ";
echo AdminLang::trans("marketConnect.weebly.learn.free.toolsInfo");
echo "            </div>\n        </div>\n        <br>\n        <p>";
echo AdminLang::trans("marketConnect.weebly.learn.free.outro1");
echo "</p>\n        <p>* ";
echo AdminLang::trans("marketConnect.weebly.learn.free.outro2");
echo "</p>\n        <p><small><p>";
echo AdminLang::trans("marketConnect.weebly.learn.free.availableIn");
echo "</p></small></p>\n    </div>\n</div>\n<div class=\"tab-pane\" id=\"pricing\" role=\"tabpanel\">\n    <div class=\"content-padded weebly\">\n        ";
if ($feed->isNotAvailable()) {
    echo "            <div class=\"pricing-login-overlay\">\n                <p>";
    echo AdminLang::trans("marketConnect.loginForPricing");
    echo "</p>\n                <button type=\"button\" class=\"btn btn-default btn-sm btn-login\">\n                    ";
    echo AdminLang::trans("marketConnect.login");
    echo "                </button>\n                <button type=\"button\" class=\"btn btn-default btn-sm btn-register\">\n                    ";
    echo AdminLang::trans("marketConnect.createAccount");
    echo "                </button>\n            </div>\n        ";
}
echo "        <table class=\"table table-pricing\">\n            <tr>\n                <th>\n                    ";
echo AdminLang::trans("marketConnect.weebly.pricing.feature");
echo "                </th>\n                <th>\n                    ";
echo AdminLang::trans("marketConnect.weebly.pricing.free");
echo "<br />\n                    <span>\n                        ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getCostPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_FREE)]);
echo "                    </span>\n                </th>\n                <th>\n                    ";
echo AdminLang::trans("marketConnect.weebly.pricing.starter");
echo "<br />\n                    <span>\n                        ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getCostPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_STARTER)]);
echo "                    </span>\n                </th>\n                <th>\n                    ";
echo AdminLang::trans("marketConnect.weebly.pricing.pro");
echo "<br>\n                    <span>\n                        ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getCostPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_PRO)]);
echo "                    </span>\n                </th>\n                <th>\n                    ";
echo AdminLang::trans("marketConnect.weebly.pricing.business");
echo "<br>\n                    <span>\n                        ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getCostPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_BUSINESS)]);
echo "                    </span>\n                </th>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.ddBuilder");
echo "</td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.pages");
echo "</td>\n                <td>";
echo AdminLang::trans("global.unlimited");
echo "</td>\n                <td>";
echo AdminLang::trans("global.unlimited");
echo "</td>\n                <td>";
echo AdminLang::trans("global.unlimited");
echo "</td>\n                <td>";
echo AdminLang::trans("global.unlimited");
echo "</td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.noAds");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.hdVideo");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.passwords");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.members");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.coupons");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.tax");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.shipping");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.abandonedCart");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.giftCards");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.eCommerce");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.upTo", [":num" => 10]);
echo "</td>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.upTo", [":num" => 25]);
echo "</td>\n                <td>";
echo AdminLang::trans("global.unlimited");
echo "</td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.eCommerceFees");
echo "</td>\n                <td>-</td>\n                <td>3%</td>\n                <td>3%</td>\n                <td>0%</td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.seo");
echo "</td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n                <td><i class=\"icon-yes fas fa-check\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.emailCampaigns");
echo "</td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n                <td><i class=\"icon-no fas fa-times\"></i></td>\n            </tr>\n            <tr>\n                <td>";
echo AdminLang::trans("marketConnect.weebly.pricing.rrp");
echo "</td>\n                <td>\n                    ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getRecommendedRetailPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_FREE)]);
echo "                </td>\n                <td>\n                    ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getRecommendedRetailPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_STARTER)]);
echo "                </td>\n                <td>\n                    ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getRecommendedRetailPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_PRO)]);
echo "                </td>\n                <td>\n                    ";
echo AdminLang::trans("marketConnect.perMo", [":num" => $feed->getRecommendedRetailPrice(WHMCS\MarketConnect\Promotion\Service\Weebly::WEEBLY_BUSINESS)]);
echo "                </td>\n            </tr>\n        </table>\n    </div>\n</div>\n<div class=\"tab-pane\" id=\"faq\" role=\"tabpanel\">\n    <div class=\"content-padded weebly faq\">\n        <h4>";
echo AdminLang::trans("marketConnect.weebly.faq.q1");
echo "</h4>\n        <p>";
echo AdminLang::trans("marketConnect.weebly.faq.a1");
echo "</p>\n        <h4>";
echo AdminLang::trans("marketConnect.weebly.faq.q2");
echo "</h4>\n        <p>";
echo AdminLang::trans("marketConnect.weebly.faq.a2");
echo "</p>\n        <h4>";
echo AdminLang::trans("marketConnect.weebly.faq.q3");
echo "</h4>\n        <p>";
echo AdminLang::trans("marketConnect.weebly.faq.a3");
echo "</p>\n        <h4>";
echo AdminLang::trans("marketConnect.weebly.faq.q4");
echo "</h4>\n        <p>\n            ";
$routedPath = routePath("store-product-group", $feed->getGroupSlug(WHMCS\MarketConnect\MarketConnect::SERVICE_WEEBLY));
echo AdminLang::trans("marketConnect.weebly.faq.a4", [":href" => $routedPath . (strpos($routedPath, "?") ? "&" : "?") . "preview=1"]);
echo "        </p>\n        <p><br><small>* ";
echo AdminLang::trans("marketConnect.weebly.faq.ftp");
echo "</small></p>\n    </div>\n</div>\n<div class=\"tab-pane\" id=\"activate\" role=\"tabpanel\">\n    ";
$this->insert("shared/configuration-activate", ["currency" => $currency, "service" => $service, "firstBulletPoint" => "Offer all 4 Weebly Plans", "availableForAllHosting" => true, "landingPageRoutePath" => routePath("store-product-group", $feed->getGroupSlug(WHMCS\MarketConnect\MarketConnect::SERVICE_WEEBLY)), "serviceOffering" => $serviceOffering, "billingCycles" => $billingCycles, "products" => $products, "serviceTerms" => $serviceTerms]);
echo "</div>\n";
$this->end();
