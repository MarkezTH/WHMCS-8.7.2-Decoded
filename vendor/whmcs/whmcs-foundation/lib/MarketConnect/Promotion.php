<?php

namespace WHMCS\MarketConnect;

class Promotion
{
    const DEFAULT_SETTINGS = [["name" => "auto-assign-addons", "label" => "Auto Assign to Addons", "description" => "Automatically assign these products as add-on options to all applicable products", "default" => true], ["name" => "activate-landing-page", "label" => "Landing Page Links", "description" => "Activate navigation link within the client area navigation bar", "default" => true]];

    public static function initHooks()
    {
        $hooks = ["ClientAreaSidebars" => "clientAreaSidebars"];
        foreach ($hooks as $hook => $function) {
            add_hook($hook, -1, function ($var = NULL) use($function) {
                $response = [];
                foreach (Service::active()->get() as $service) {
                    $response[] = $service->factoryPromoter()->{$function}(func_get_args());
                }
                return implode($response);
            });
        }
        add_hook("ClientAreaProductDetailsOutput", -1, function ($vars) {
            $serviceModel = $vars["service"];
            $logins = [];
            foreach (Service::active()->get() as $service) {
                if ($serviceModel->product->module != "marketconnect") {
                    $loginPanel = $service->factoryPromoter()->productDetailsLogin($serviceModel);
                    if ($loginPanel instanceof Promotion\LoginPanel) {
                        $logins[] = $loginPanel->toHtml();
                    }
                }
            }
            if (empty($logins)) {
                return "";
            }
            return "<div id=\"mc-promo-widgets\">" . implode("", $logins) . "</div>";
        });
        add_hook("ClientAreaProductDetailsOutput", -2, function ($vars) {
            $serviceModel = $vars["service"];
            $promotions = [];
            foreach (Service::active()->get() as $service) {
                $response = $service->factoryPromoter()->productDetailsOutput($serviceModel);
                if (!empty($response)) {
                    $promotions[] = $response;
                }
            }
            if (0 < count($promotions)) {
                return Promotion::renderPromotionsCarousel($promotions);
            }
        });
        add_hook("ClientAreaHomepagePanels", -1, function ($homePagePanels) {
            $promotions = [];
            foreach (Service::active()->get() as $service) {
                $loginPanel = NULL;
                $promoter = $service->factoryPromoter();
                if ($promoter->clientHasActiveServices() && $promoter->supportsLogin()) {
                    $loginPanel = $promoter->getLoginPanel();
                }
                if (!is_null($loginPanel)) {
                    $homePagePanels->addChild($loginPanel);
                }
            }
        });
        add_hook("ClientAreaHomepage", -1, function () {
            $promotions = [];
            foreach (Service::active()->get() as $service) {
                $response = $service->factoryPromoter()->clientAreaHomeOutput();
                if (!empty($response)) {
                    $promotions[] = $response;
                }
            }
            if (0 < count($promotions)) {
                return Promotion::renderPromotionsCarousel($promotions);
            }
        });
        add_hook("ShoppingCartViewCartOutput", -1, function () {
            $promotions = Promotion::cartViewPromotion();
            if (0 < count($promotions)) {
                return "<h3 style=\"margin:20px 0;\">" . \Lang::trans("store.recommendedForYou") . "</h3>" . "<div class=\"mc-promos viewcart\">" . implode($promotions) . "</div>";
            }
        });
        add_hook("ShoppingCartCheckoutOutput", -1, function () {
            $promotions = Promotion::cartViewPromotion(true);
            if (0 < count($promotions)) {
                return "<div class=\"sub-heading\"><span class=\"primary-bg-color\">" . \Lang::trans("store.lastChance") . "</span></div>" . "<div class=\"mc-promos checkout\">" . implode($promotions) . "</div>";
            }
        });
    }

    public static function cartViewPromotion($checkout = false)
    {
        $promotions = [];
        foreach (Service::active()->get() as $service) {
            if ($checkout) {
                $promotions[] = $service->factoryPromoter()->cartCheckoutPromotion(func_get_args());
            } else {
                $promotions[] = $service->factoryPromoter()->cartViewPromotion(func_get_args());
            }
        }
        foreach ($promotions as $key => $value) {
            if (empty($value)) {
                unset($promotions[$key]);
            }
        }
        return $promotions;
    }

    protected static function renderPromotionsCarousel($promotions)
    {
        foreach ($promotions as $key => $value) {
            $promotions[$key] = "<div class=\"carousel-item item" . ($key == 0 ? " active" : "") . "\">" . $value . "</div>";
        }
        if (count($promotions) == 1) {
            return "<h3 style=\"margin:0 0 20px 0;\">" . \Lang::trans("store.recommendedForYou") . "</h3>" . implode($promotions) . "<br>";
        }
        return "<div class=\"promo-container\">\n            <div class=\"header\">\n                <div class=\"pull-right float-right promotions-slider-control\">\n                    <a href=\"#promotions-slider\" role=\"button\" data-slide=\"prev\" style=\"text-decoration:none;\">\n                        <span class=\"glyphicon align-bottom carousel-control-prev-icon glyphicon-chevron-left\" aria-hidden=\"true\"></span>\n                        <span class=\"sr-only\">" . \Lang::trans("tablepagesprevious") . "</span>\n                    </a>\n                    <a href=\"#promotions-slider\" role=\"button\" data-slide=\"next\">\n                        <span class=\"glyphicon align-bottom carousel-control-next-icon glyphicon-chevron-right\" aria-hidden=\"true\"></span>\n                        <span class=\"sr-only\">" . \Lang::trans("tablepagesnext") . "</span>\n                    </a>\n                </div>\n                <h3>" . \Lang::trans("store.recommendedForYou") . "</h3>" . "\n            </div>\n            <div id=\"promotions-slider\" class=\"carousel slide\" data-ride=\"carousel\">\n                <div class=\"carousel-inner\" role=\"listbox\">" . implode($promotions) . "</div>\n            </div>\n        </div>";
    }
}
