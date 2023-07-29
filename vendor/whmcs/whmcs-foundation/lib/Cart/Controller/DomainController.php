<?php

namespace WHMCS\Cart\Controller;

class DomainController
{
    private $orderForm = NULL;
    private $orderFormTemplate = NULL;
    private $templateVariables = [];

    protected function startOrderForm()
    {
        $templateVariables = $this->templateVariables;
        $orderForm = new \WHMCS\OrderForm();
        $orderFormTemplate = \WHMCS\View\Template\OrderForm::factory();
        $orderFormTemplateName = $orderFormTemplate->getName();
        try {
            \WHMCS\View\Template\OrderForm::factory("domain-renewals.tpl", $orderFormTemplateName);
        } catch (\WHMCS\Exception\View\TemplateNotFound $e) {
            \App::redirect(\App::getSystemURL() . "cart.php", ["gid" => "renewals"]);
        }
        $currency = \Currency::factoryForClientArea();
        $templateVariables["currency"] = $currency;
        $templateVariables["ipaddress"] = \App::getRemoteIp();
        $templateVariables["inShoppingCart"] = true;
        $templateVariables["action"] = "add";
        $templateVariables["numitemsincart"] = $orderForm->getNumItemsInCart();
        $templateVariables["gid"] = "renewals";
        $templateVariables["domain"] = "renew";
        $templateVariables["carttpl"] = $orderFormTemplateName;
        $templateVariables["showSidebarToggle"] = (bool) \WHMCS\Config\Setting::getValue("OrderFormSidebarToggle");
        $this->orderForm = $orderForm;
        $this->orderFormTemplate = $orderFormTemplate;
        $this->templateVariables = $templateVariables;
    }

    protected function endOrderForm()
    {
        $orderForm = $this->orderForm;
        $templateVariables = $this->templateVariables;
        \Menu::addContext("productGroups", $orderForm->getProductGroups(true));
        \Menu::addContext("productGroupId", $templateVariables["gid"]);
        \Menu::addContext("domainRegistrationEnabled", (bool) \WHMCS\Config\Setting::getValue("AllowRegister"));
        \Menu::addContext("domainTransferEnabled", (bool) \WHMCS\Config\Setting::getValue("AllowTransfer"));
        \Menu::addContext("domainRenewalEnabled", (bool) \WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders"));
        \Menu::addContext("domain", $templateVariables["domain"]);
        \Menu::addContext("currency", $templateVariables["currency"]);
        \Menu::addContext("action", $templateVariables["action"]);
        \Menu::addContext("domainAction", "renew");
        \Menu::addContext("allowRemoteAuth", true);
        \Menu::primarySidebar("orderFormView");
        \Menu::secondarySidebar("orderFormView");
        $this->orderForm = $orderForm;
        $this->templateVariables = $templateVariables;
    }

    public function singleRenew(\Psr\Http\Message\ServerRequestInterface $request)
    {
        \Auth::requireLoginAndClient(true);
        $this->startOrderForm();
        $domainName = $request->getAttribute("domain");
        if (!\WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders")) {
            \App::redirect(\App::getSystemURL() . "clientarea.php", ["action" => "domains"]);
        }
        $domain = \WHMCS\Domain\Domain::where("domain", $domainName)->where("userid", \Auth::client()->id);
        if (1 < $domain->count()) {
            $domain = $domain->whereIn("status", ["Active", "Grace", "Redemption"])->orderBy("status", "ASC");
        }
        try {
            $renewableDomains = $domain->get();
            $domain = NULL;
            foreach ($renewableDomains as $renewableDomain) {
                if ($renewableDomain->domain === $domainName) {
                    $domain = $renewableDomain;
                }
            }
            if (is_null($domain)) {
                throw new \WHMCS\Exception\InvalidDomain("No domain specified for renewal.");
            }
        } catch (\Exception $e) {
            logActivity("Invalid Domain Renewal Attempt - " . $domainName . " - User ID: " . \Auth::client()->id, \Auth::client()->id);
            \App::redirect("clientarea.php");
        }
        $renewData = \WHMCS\Domains::getRenewableDomains(\Auth::client()->id, [$domain->id]);
        if (!$renewData) {
            \App::redirect("clientarea.php", ["action" => "domaindetails", "id" => $domain->id]);
        }
        $templateVariables = $this->templateVariables;
        $view = new \WHMCS\ClientArea();
        $view->initPage();
        $view->setTemplate("domain-renewals");
        $view->setPageTitle(\Lang::trans("domainsrenew"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"))->addToBreadCrumb("clientarea.php", \Lang::trans("clientareatitle"))->addToBreadCrumb("clientarea.php?action=domains", \Lang::trans("clientareanavdomains"))->addToBreadCrumb(routePath("domain-renewal", $domainName), \Lang::trans("domainsrenew"));
        $templateVariables["renewalsData"] = $renewData["renewals"];
        $templateVariables["totalResults"] = count($renewData["renewals"]);
        $templateVariables["hasExpiredDomains"] = $renewData["hasExpiredDomains"];
        $templateVariables["hasDomainsTooEarlyToRenew"] = $renewData["hasDomainsTooEarlyToRenew"];
        $templateVariables["hasDomainsInGracePeriod"] = $renewData["hasDomainsInGracePeriod"];
        $templateVariables["totalDomainCount"] = 1;
        $view->setTemplateVariables($templateVariables);
        $this->endOrderForm();
        $view->isInOrderForm();
        return $view;
    }

    public function massRenew(\Psr\Http\Message\ServerRequestInterface $request)
    {
        \Auth::requireLoginAndClient(true);
        if (!\WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders")) {
            \App::redirect(\App::getSystemURL() . "clientarea.php", ["action" => "domains"]);
        }
        $this->startOrderForm();
        $templateVariables = $this->templateVariables;
        $domainIds = \App::getFromRequest("domids");
        if (!$domainIds) {
            $domainIds = NULL;
        } else {
            check_token();
        }
        $renewData = \WHMCS\Domains::getRenewableDomains(\Auth::client()->id, $domainIds);
        $view = new \WHMCS\ClientArea();
        $view->initPage();
        $view->setTemplate("domain-renewals");
        $view->setPageTitle(\Lang::trans("domainsrenew"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"))->addToBreadCrumb("clientarea.php", \Lang::trans("clientareatitle"))->addToBreadCrumb("clientarea.php?action=domains", \Lang::trans("clientareanavdomains"))->addToBreadCrumb(routePath("cart-domain-renewals"), \Lang::trans("domainsrenew"));
        $templateVariables["renewalsData"] = array_merge($renewData["renewalsByStatus"]["domainrenewalsingraceperiod"], $renewData["renewalsByStatus"]["domainsExpiringSoon"], $renewData["renewalsByStatus"]["domainsActive"], $renewData["renewalsByStatus"]["domainrenewalsbeforerenewlimit"], $renewData["renewalsByStatus"]["domainrenewalspastgraceperiod"]);
        $templateVariables["totalResults"] = count($templateVariables["renewalsData"]);
        $totalDomainCount = $templateVariables["totalResults"];
        if ($domainIds) {
            $totalDomainCount = \WHMCS\Domain\Domain::ofClient(\Auth::client()->id)->count();
        }
        $templateVariables["totalDomainCount"] = $totalDomainCount;
        $templateVariables["totalDomainCount"] = $totalDomainCount;
        $templateVariables["hasExpiredDomains"] = $renewData["hasExpiredDomains"];
        $templateVariables["hasDomainsTooEarlyToRenew"] = $renewData["hasDomainsTooEarlyToRenew"];
        $templateVariables["hasDomainsInGracePeriod"] = $renewData["hasDomainsInGracePeriod"];
        $view->setTemplateVariables($templateVariables);
        $this->endOrderForm();
        $view->isInOrderForm();
        return $view;
    }

    public function addRenewal()
    {
        check_token();
        $domainId = (int) \App::getFromRequest("domainId");
        $renewalPeriod = (int) \App::getFromRequest("period");
        $cartSession = \WHMCS\Session::get("cart");
        if (!is_array($cartSession)) {
            $cartSession = ["renewals" => []];
        }
        if (!isset($cartSession["renewals"]) || !is_array($cartSession["renewals"])) {
            $cartSession["renewals"] = [];
        }
        $cartSession["renewals"][$domainId] = $renewalPeriod;
        \WHMCS\Session::set("cart", $cartSession);
        return new \WHMCS\Http\Message\JsonResponse(["result" => "added"]);
    }

    public function calcRenewalCartTotals()
    {
        if (!function_exists("calcCartTotals")) {
            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "orderfunctions.php";
        }
        $currency = \Currency::factoryForClientArea();
        $view = new \WHMCS\ClientArea();
        $orderFormTemplate = \WHMCS\View\Template\OrderForm::factory("ordersummary.tpl");
        $orderFormTemplateName = $orderFormTemplate->getName();
        $orderSummaryTemplate = "/templates/orderforms/" . $orderFormTemplateName . "/ordersummary.tpl";
        $cartTotals = calcCartTotals(\Auth::client(), false, true);
        $renewalsDataReplacements = ["rawtotal" => 0, "subtotal" => 0, "taxtotal" => 0, "taxtotal2" => 0, "total" => 0];
        $taxCalculator = new \WHMCS\Billing\Tax();
        $taxCalculator->setIsInclusive(\WHMCS\Config\Setting::getValue("TaxType") == "Inclusive")->setIsCompound(\WHMCS\Config\Setting::getValue("TaxL2Compound"))->setLevel1Percentage($cartTotals["taxrate"] ?: 0)->setLevel2Percentage($cartTotals["taxrate2"] ?: 0);
        $taxSubTotals = [];
        foreach ($cartTotals["renewals"] as $domainId => $renewal) {
            $renewalPriceNumeric = $renewal["price"]->toNumeric();
            if (!empty($renewal["taxes"]["tax1"]) || !empty($renewal["taxes"]["tax2"])) {
                $taxSubTotals[] = $renewalPriceNumeric;
            } else {
                $renewalsDataReplacements["subtotal"] += $renewalPriceNumeric;
                $renewalsDataReplacements["rawtotal"] += $renewalPriceNumeric;
            }
        }
        $totalTax1 = 0;
        $totalTax2 = 0;
        if (!empty($taxSubTotals)) {
            if (\WHMCS\Config\Setting::getValue("TaxPerLineItem")) {
                foreach ($taxSubTotals as $taxBase) {
                    $taxCalculator->setTaxBase($taxBase);
                    $totalTax1 += $taxCalculator->getLevel1TaxTotal();
                    $totalTax2 += $taxCalculator->getLevel2TaxTotal();
                    $renewalsDataReplacements["subtotal"] += $taxCalculator->getTotalBeforeTaxes();
                    $renewalsDataReplacements["rawtotal"] += $taxCalculator->getTotalAfterTaxes();
                }
            } else {
                $taxCalculator->setTaxBase(array_sum($taxSubTotals));
                $totalTax1 = $taxCalculator->getLevel1TaxTotal();
                $totalTax2 = $taxCalculator->getLevel2TaxTotal();
                $renewalsDataReplacements["subtotal"] += $taxCalculator->getTotalBeforeTaxes();
                $renewalsDataReplacements["rawtotal"] += $taxCalculator->getTotalAfterTaxes();
            }
        }
        if ($totalTax1) {
            $renewalsDataReplacements["taxtotal"] = new \WHMCS\View\Formatter\Price($totalTax1, $currency);
        }
        if ($totalTax2) {
            $renewalsDataReplacements["taxtotal2"] = new \WHMCS\View\Formatter\Price($totalTax2, $currency);
        }
        $renewalsDataReplacements["subtotal"] = new \WHMCS\View\Formatter\Price($renewalsDataReplacements["subtotal"], $currency);
        $renewalsDataReplacements["total"] = new \WHMCS\View\Formatter\Price($renewalsDataReplacements["rawtotal"], $currency);
        $cartTotals = array_merge($cartTotals, $renewalsDataReplacements);
        $templateVariables = ["producttotals" => [], "carttotals" => $cartTotals, "renewals" => true];
        return new \WHMCS\Http\Message\JsonResponse(["body" => $view->getSingleTPLOutput($orderSummaryTemplate, $templateVariables)]);
    }
}
