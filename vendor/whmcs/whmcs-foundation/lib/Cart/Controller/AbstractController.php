<?php

namespace WHMCS\Cart\Controller;

class AbstractController
{
    public function render($template, $templateVars)
    {
        $orderfrm = new \WHMCS\OrderForm();
        $view = new \WHMCS\ClientArea();
        $view->setPageTitle(\Lang::trans("carttitle"));
        $view->addOutputHookFunction("ClientAreaPageCart");
        $view->addToBreadCrumb("cart.php", \Lang::trans("carttitle"));
        $view->isInOrderForm();
        \Menu::addContext("productGroups", $orderfrm->getProductGroups(true));
        \Menu::addContext("productGroupId", $templateVars["gid"] ?? NULL);
        \Menu::addContext("domainRegistrationEnabled", (bool) \WHMCS\Config\Setting::getValue("AllowRegister"));
        \Menu::addContext("domainTransferEnabled", (bool) \WHMCS\Config\Setting::getValue("AllowTransfer"));
        \Menu::addContext("domainRenewalEnabled", (bool) \WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders"));
        global $currency;
        \Menu::addContext("currency", $currency);
        \Menu::addContext("action", "");
        \Menu::addContext("client", \Auth::client());
        \Menu::primarySidebar("orderFormView");
        \Menu::secondarySidebar("orderFormView");
        $orderFormTemplate = \WHMCS\View\Template\OrderForm::factory();
        $orderFormTemplateName = $orderFormTemplate->getName();
        if (!empty($templateVars["ajax"])) {
            $view->disableHeaderFooterOutput();
            unset($templateVars["ajax"]);
        }
        $view->setTemplate($template)->setTemplateVariables(array_merge(["inShoppingCart" => true, "carttpl" => $orderFormTemplateName, "showSidebarToggle" => (bool) \WHMCS\Config\Setting::getValue("OrderFormSidebarToggle")], $templateVars));
        return $view;
    }
}
