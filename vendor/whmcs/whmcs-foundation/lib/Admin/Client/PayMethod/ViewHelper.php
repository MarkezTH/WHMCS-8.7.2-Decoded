<?php

namespace WHMCS\Admin\Client\PayMethod;

class ViewHelper
{
    private $aInt = NULL;

    public function __construct(\WHMCS\Admin $aInt = NULL)
    {
        if (!$aInt) {
            $aInt = new \WHMCS\Admin("Manage Pay Methods", false);
        }
        $this->aInt = $aInt;
    }

    public function getIconClass(\WHMCS\Payment\Contracts\PayMethodInterface $payMethod)
    {
        $payment = $payMethod->payment;
        $gateway = $payMethod->getGateway();
        if (!$payMethod->isManageable()) {
            $class = "fal fa-credit-card-front fa-fw";
        } else {
            switch ($gateway) {
                case "stripe":
                    $class = "fab fa-cc-stripe fa-fw";
                    break;
                case "paypal":
                    $class = "fab fa-cc-paypal fa-fw";
                    break;
                default:
                    $class = "";
                    if (!$class && $payMethod->isCreditCard()) {
                        $cardType = strtolower($payment->getCardType());
                        switch ($cardType) {
                            case "visa":
                                $class = "fab fa-cc-visa fa-fw";
                                break;
                            case "mastercard":
                                $class = "fab fa-cc-mastercard fa-fw";
                                break;
                            case "american express":
                            case "amex":
                                $class = "fab fa-cc-amex fa-fw";
                                break;
                            case "discover":
                                $class = "fab fa-cc-discover fa-fw";
                                break;
                            case "diners club":
                            case "enroute":
                                $class = "fab fa-cc-diners-club fa-fw";
                                break;
                            case "jcb":
                                $class = "fab fa-cc-jcb fa-fw";
                                break;
                            default:
                                $class = "fal fa-credit-card fa-fw";
                        }
                    } else {
                        $class = "fal fa-money-check fa-fw";
                    }
            }
        }
        return $class;
    }

    public function getShortDescription(\WHMCS\Payment\Contracts\PayMethodInterface $payMethod)
    {
        $description = "";
        $payment = $payMethod->payment;
        $totalLength = 30;
        $lastFour = "";
        if ($payMethod->isCreditCard()) {
            $totalLength -= 7;
            $lastFour = $payment->getLastFour();
            if ($lastFour) {
                $lastFour = "&nbsp;(" . $lastFour . ")";
            }
            if (20 < strlen($payMethod->getDescription())) {
                $description = substr($payMethod->getDescription(), 0, $totalLength - 3);
                $description .= "...";
            } else {
                $description = substr($payMethod->getDescription(), 0, $totalLength);
            }
        }
        return $description . $lastFour;
    }

    public function clientProfileSummaryHtmlTableRows(\WHMCS\User\Client $client)
    {
        $template = "client-paymethods-rows";
        return $this->clientProfileHtml($client, $template);
    }

    public function clientProfileSummaryHtml(\WHMCS\User\Client $client)
    {
        $template = "client-paymethods";
        return $this->clientProfileHtml($client, $template, ["payMethodRows" => $this->clientProfileSummaryHtmlTableRows($client)]);
    }

    protected function clientProfileHtml(\WHMCS\User\Client $client, $template, $vars = [])
    {
        $currentAdmin = \WHMCS\User\Admin::find(\WHMCS\Session::get("adminid"));
        if (!$currentAdmin || !$currentAdmin->hasPermission("Manage Pay Methods")) {
            return "";
        }
        $resolver = new \WHMCS\Gateways();
        $cardStorageOptions = $bankStorageOptions = [];
        if ($resolver->hasGatewaysSupportingManage()) {
            $availableGatewayModules = $resolver->getAvailableGatewayInstances(true);
            foreach ($availableGatewayModules as $name => $module) {
                if (!($module->getWorkflowType() == \WHMCS\Module\Gateway::WORKFLOW_NOLOCALCARDINPUT || $module->isTokenised() && !$module->isRemote())) {
                    if ($module->getMetaDataValue("gatewayType") == \WHMCS\Module\Gateway::GATEWAY_BANK) {
                        $bankStorageOptions[$name] = $module->getDisplayName();
                    } else {
                        $cardStorageOptions[$name] = $module->getDisplayName();
                    }
                }
            }
            if ($resolver->isLocalCreditCardStorageEnabled()) {
                $cardStorageOptions["local"] = \AdminLang::trans("payments.localEncryption");
            }
            if ($resolver->isLocalBankAccountGatewayAvailable()) {
                $bankStorageOptions["local"] = routePath("admin-client-paymethods-new", $client->id, "bank_account");
            }
        }
        $addNewBankAccountUrl = $addNewCardUrl = "";
        if (count($cardStorageOptions)) {
            if (count($cardStorageOptions) == 1) {
                $addNewCardUrl = routePath("admin-client-paymethods-new", $client->id, "card", key($cardStorageOptions));
            } else {
                $addNewCardUrl = routePath("admin-client-paymethods-new", $client->id, "card");
            }
        }
        if (count($bankStorageOptions)) {
            if (count($bankStorageOptions) == 1) {
                $addNewBankAccountUrl = routePath("admin-client-paymethods-new", $client->id, "bank_account", key($bankStorageOptions));
            } else {
                $addNewBankAccountUrl = routePath("admin-client-paymethods-new", $client->id, "bank_account");
            }
        }
        $templateData = ["cardStorageOptions" => $cardStorageOptions, "payMethods" => [], "client" => $client, "addNewCardUrl" => $addNewCardUrl, "addNewBankAccountUrl" => $addNewBankAccountUrl];
        $payMethods = $client->payMethods;
        foreach ($payMethods as $payMethod) {
            $payment = $payMethod->payment;
            if (!$payment->{$payment->getSensitiveDataAttributeName()}) {
                logActivity("Automatically Removed Payment Method without Encrypted Data. PayMethod ID: " . $payMethod->id . " User ID: " . $payMethod->userid, \Auth::client()->id);
                $payMethod->delete();
            } else {
                $templateData["payMethods"][] = ["id" => $payMethod->id, "url" => routePath("admin-client-paymethods-view", $client->id, $payMethod->id), "iconClass" => $payMethod->getFontAwesomeIcon(), "description" => $payMethod->getPaymentDescription(), "isDefault" => $payMethod->isDefaultPayMethod(), "isUsingInactiveGateway" => $payMethod->isUsingInactiveGateway()];
            }
        }
        $templateData = array_merge($templateData, $vars);
        $this->aInt->templatevars = $templateData;
        return $this->aInt->getTemplate($template);
    }
}
