<?php

namespace WHMCS\View\Client\Menu;

class PrimaryNavbarFactory extends \WHMCS\View\Menu\MenuFactory
{
    protected $rootItemName = "Primary Navbar";

    public function navbar($firstName = "", $conditionalLinks = [])
    {
        $menuStructure = \Auth::user() ? $this->getLoggedInNavBarStructure($firstName, $conditionalLinks) : $this->getLoggedOutNavBarStructure($conditionalLinks);
        return $this->loader->load($this->buildMenuStructure($menuStructure));
    }

    protected function getLoggedOutNavBarStructure($conditionalLinks = [])
    {
        $menuItems = [["name" => "Home", "label" => \Lang::trans("clientareanavhome"), "uri" => "index.php", "order" => 10], ["name" => "Announcements", "label" => \Lang::trans("announcementstitle"), "uri" => routePath("announcement-index"), "order" => 20], ["name" => "Knowledgebase", "label" => \Lang::trans("knowledgebasetitle"), "uri" => routePath("knowledgebase-index"), "order" => 30], ["name" => "Network Status", "label" => \Lang::trans("networkstatustitle"), "uri" => "serverstatus.php", "order" => 40]];
        if (!empty($conditionalLinks["affiliates"])) {
            $menuItems[] = ["name" => "Affiliates", "label" => \Lang::trans("affiliatestitle"), "uri" => "affiliates.php", "order" => 50];
        }
        $menuItems[] = ["name" => "Contact Us", "label" => \Lang::trans("contactus"), "uri" => "contact.php", "order" => 60];
        $menuItems[] = ["name" => "Store", "label" => \Lang::trans("navStore"), "uri" => "cart.php", "order" => 15, "children" => $this->buildStoreChildren($conditionalLinks)];
        return $menuItems;
    }

    protected function getLoggedInNavBarStructure($firstName = "", $conditionalLinks = [])
    {
        $menuItems = [["name" => "Home", "label" => \Lang::trans("clientareanavhome"), "uri" => "clientarea.php", "order" => 10], ["name" => "Services", "label" => \Lang::trans("navservices"), "uri" => "services.php", "order" => 20, "children" => [["name" => "My Services", "label" => \Lang::trans("clientareanavservices"), "uri" => "clientarea.php?action=services", "order" => 10], ["name" => "Services Divider", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 20], ["name" => "Order New Services", "label" => \Lang::trans("navservicesorder"), "uri" => "cart.php", "order" => 30], ["name" => "View Available Addons", "label" => \Lang::trans("clientareaviewaddons"), "uri" => "cart.php?gid=addons", "order" => 40]]], ["name" => "Billing", "label" => \Lang::trans("navbilling"), "uri" => "billing.php", "order" => 40, "children" => $this->buildBillingChildren($conditionalLinks)], ["name" => "Support", "label" => \Lang::trans("navsupport"), "uri" => "support.php", "order" => 50, "children" => [["name" => "Tickets", "label" => \Lang::trans("navtickets"), "uri" => "supporttickets.php", "order" => 10], ["name" => "Announcements", "label" => \Lang::trans("announcementstitle"), "uri" => routePath("announcement-index"), "order" => 20], ["name" => "Knowledgebase", "label" => \Lang::trans("knowledgebasetitle"), "uri" => routePath("knowledgebase-index"), "order" => 30], ["name" => "Downloads", "label" => \Lang::trans("downloadstitle"), "uri" => routePath("download-index"), "order" => 40], ["name" => "Network Status", "label" => \Lang::trans("networkstatustitle"), "uri" => "serverstatus.php", "order" => 50]]], ["name" => "Open Ticket", "label" => \Lang::trans("navopenticket"), "uri" => "submitticket.php", "order" => 60]];
        if (!empty($conditionalLinks["affiliates"])) {
            $menuItems[] = ["name" => "Affiliates", "label" => \Lang::trans("affiliatestitle"), "uri" => "affiliates.php", "order" => 70];
        }
        $client = \Menu::context("client");
        $domains = !$client ? 0 : $client->domains()->count();
        if (\WHMCS\Config\Setting::getValue("AllowRegister") || \WHMCS\Config\Setting::getValue("AllowTransfer") || $domains) {
            $menuItems[] = ["name" => "Domains", "label" => \Lang::trans("navdomains"), "uri" => "domains.php", "order" => 30, "children" => $this->buildDomainsChildren($conditionalLinks)];
        }
        $lastOrder = 1234;
        $wsItems = [];
        if (\WHMCS\MarketConnect\MarketConnect::hasActiveServices()) {
            $marketConnectItems = \WHMCS\MarketConnect\MarketConnect::getMenuItems(true);
            if (!empty($marketConnectItems)) {
                $wsItems = $marketConnectItems;
                $lastOrder = $wsItems[count($wsItems) - 1]["order"];
            }
        }
        $addonChildren = $this->getStoreAddonChildren($lastOrder + 20);
        if (!empty($addonChildren) || !empty($wsItems)) {
            if (!empty($addonChildren) && !empty($wsItems)) {
                $wsItems[] = ["name" => "Shop Divider Addons", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => $lastOrder + 10];
            }
            $wsItems = array_merge($wsItems, $addonChildren);
            $menuItems[] = ["name" => "Website Security", "label" => \Lang::trans("navWebsiteSecurity"), "uri" => "#", "order" => 35, "children" => $wsItems];
        }
        return $menuItems;
    }

    protected function buildDomainsChildren($conditionalLinks = [])
    {
        $domainsChildren = [["name" => "My Domains", "label" => \Lang::trans("clientareanavdomains"), "uri" => "clientarea.php?action=domains", "order" => 10], ["name" => "Domains Divider", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 20]];
        if ((bool) \WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders")) {
            $domainsChildren[] = ["name" => "Renew Domains", "label" => \Lang::trans("navrenewdomains"), "uri" => routePath("cart-domain-renewals"), "order" => 30];
        }
        if (!empty($conditionalLinks["domainreg"])) {
            $domainsChildren[] = ["name" => "Register a New Domain", "label" => \Lang::trans("navregisterdomain"), "uri" => "cart.php?a=add&domain=register", "order" => 40];
        }
        if (!empty($conditionalLinks["domaintrans"])) {
            $domainsChildren[] = ["name" => "Transfer a Domain to Us", "label" => \Lang::trans("navtransferdomain"), "uri" => "cart.php?a=add&domain=transfer", "order" => 50];
        }
        if (!empty($conditionalLinks["domainreg"])) {
            $domainsChildren[] = ["name" => "Domains Divider 2", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 60];
            $domainsChildren[] = ["name" => "Domain Search", "label" => \Lang::trans("navdomainsearch"), "uri" => "domainchecker.php", "order" => 70];
        }
        return $domainsChildren;
    }

    protected function buildBillingChildren($conditionalLinks)
    {
        $billingChildren = [["name" => "My Invoices", "label" => \Lang::trans("invoices"), "uri" => "clientarea.php?action=invoices", "order" => 10], ["name" => "My Quotes", "label" => \Lang::trans("quotestitle"), "uri" => "clientarea.php?action=quotes", "order" => 20]];
        if (!empty($conditionalLinks["addfunds"]) || !empty($conditionalLinks["masspay"]) || !empty($conditionalLinks["updatecc"])) {
            $billingChildren[] = ["name" => "Billing Divider", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 30];
        }
        if (!empty($conditionalLinks["masspay"])) {
            $billingChildren[] = ["name" => "Mass Payment", "label" => \Lang::trans("masspaytitle"), "uri" => "clientarea.php?action=masspay&all=true", "order" => 40];
        }
        if (!empty($conditionalLinks["updatecc"])) {
            $billingChildren[] = ["name" => "Payment Methods", "label" => \Lang::trans("paymentMethods.title"), "uri" => routePath("account-paymentmethods"), "order" => 50];
        }
        if (!empty($conditionalLinks["addfunds"])) {
            $billingChildren[] = ["name" => "Add Funds", "label" => \Lang::trans("addfunds"), "uri" => "clientarea.php?action=addfunds", "order" => 60];
        }
        return $billingChildren;
    }

    protected function getStoreAddonChildren($startIndex)
    {
        $matchingAddonConfigs = \WHMCS\Config\Module\ModuleConfiguration::with("productAddon")->where("entity_type", "addon")->where("setting_name", "configoption1")->whereIn("value", array_column(\WHMCS\Cart\Controller\ProductController::ADDON_SLUGS, "featureName"))->get()->sort(function ($a, $b) {
            if ($a->value == $b->value) {
                return 0;
            }
            return $a->value == "wp-toolkit-deluxe" ? -1 : 1;
        })->take(1);
        $menuItemIndex = $startIndex;
        $addonChildren = [];
        foreach ($matchingAddonConfigs as $addonConfig) {
            $addonSlug = \Illuminate\Support\Str::slug($addonConfig->value);
            $addonFriendlyName = \WHMCS\Cart\Controller\ProductController::ADDON_SLUGS[$addonSlug]["friendlyName"] ?? NULL;
            if ($addonFriendlyName) {
                $addonChildren[] = ["name" => $addonFriendlyName, "label" => \Lang::trans("store.addon." . \WHMCS\Cart\Controller\ProductController::ADDON_SLUGS[$addonSlug]["languageKey"] . ".title"), "uri" => routePath("store-addon", $addonSlug), "order" => $menuItemIndex++];
            }
        }
        return $addonChildren;
    }

    protected function buildStoreChildren($conditionalLinks = [])
    {
        $children = [["name" => "Browse Products Services", "label" => \Lang::trans("navBrowseProductsServices"), "uri" => routePath("store"), "order" => 10]];
        $children[] = ["name" => "Shop Divider 1", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 20];
        $i = 0;
        foreach (\WHMCS\Product\Group::notHidden()->sorted()->get() as $group) {
            $children[] = ["name" => $group->name, "label" => $group->name, "uri" => $group->getRoutePath(), "order" => 30 + $i * 10];
            $i++;
        }
        if (\WHMCS\MarketConnect\MarketConnect::hasActiveServices()) {
            $children = array_merge($children, \WHMCS\MarketConnect\MarketConnect::getMenuItems(false));
            if (!empty($conditionalLinks["domainreg"]) || !empty($conditionalLinks["domaintrans"])) {
                $children[] = ["name" => "Shop Divider 2", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 2000];
            }
        }
        if (!empty($conditionalLinks["domainreg"])) {
            $children[] = ["name" => "Register a New Domain", "label" => \Lang::trans("navregisterdomain"), "uri" => "cart.php?a=add&domain=register", "order" => 2500];
        }
        if (!empty($conditionalLinks["domaintrans"])) {
            $children[] = ["name" => "Transfer a Domain to Us", "label" => \Lang::trans("navtransferdomain"), "uri" => "cart.php?a=add&domain=transfer", "order" => 2510];
        }
        $addonChildren = $this->getStoreAddonChildren(2530);
        if (!empty($addonChildren)) {
            $children[] = ["name" => "Shop Divider 3", "label" => "-----", "attributes" => ["class" => "nav-divider"], "order" => 2520];
            $children = array_merge($children, $addonChildren);
        }
        if (count($children) == 2) {
            return [];
        }
        return $children;
    }
}
