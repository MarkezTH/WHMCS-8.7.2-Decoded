<?php

namespace WHMCS\User\Client;

class AlertFactory
{
    protected $client = NULL;
    protected $alerts = [];

    public function __construct(\WHMCS\User\Client $client)
    {
        $this->client = $client;
    }

    public function build()
    {
        $this->checkForExpiringCreditCard()->checkForDomainsExpiringSoon()->checkForUnpaidInvoices()->checkForCreditBalance();
        $alerts = run_hook("ClientAlert", $this->client);
        foreach ($alerts as $response) {
            if ($response instanceof \WHMCS\User\Alert) {
                $this->addAlert($response);
            }
        }
        return new \Illuminate\Support\Collection($this->alerts);
    }

    protected function addAlert(\WHMCS\User\Alert $alert)
    {
        $this->alerts[] = $alert;
        return $this;
    }

    protected function checkForExpiringCreditCard()
    {
        $expiringCard = $this->client->isCreditCardExpiring();
        if ($expiringCard) {
            $this->addAlert(new \WHMCS\User\Alert(\Lang::trans("clientAlerts.creditCardExpiring", [":creditCardType" => $expiringCard["cardtype"], ":creditCardLastFourDigits" => $expiringCard["cardlastfour"], ":days" => 60]), "warning", \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . DIRECTORY_SEPARATOR . "clientarea.php?action=creditcard", \Lang::trans("clientareaupdatebutton")));
        }
        return $this;
    }

    protected function checkForDomainsExpiringSoon()
    {
        if (!\WHMCS\Config\Setting::getValue("EnableDomainRenewalOrders")) {
            return $this;
        }
        $domainsDueWithin7Days = $this->client->domains()->nextDueBefore(\WHMCS\Carbon::now()->addDays(7))->count();
        if (0 < $domainsDueWithin7Days) {
            $this->addAlert(new \WHMCS\User\Alert(\Lang::trans("clientAlerts.domainsExpiringSoon", [":days" => 7, ":numberOfDomains" => $domainsDueWithin7Days]), "danger", routePath("cart-domain-renewals"), \Lang::trans("domainsrenewnow")));
        }
        $domainsDueWithin30Days = $this->client->domains()->nextDueBefore(\WHMCS\Carbon::now()->addDays(30))->count();
        $domainsDueWithin30Days -= $domainsDueWithin7Days;
        if (0 < $domainsDueWithin30Days) {
            $this->addAlert(new \WHMCS\User\Alert(\Lang::trans("clientAlerts.domainsExpiringSoon", [":days" => 30, ":numberOfDomains" => $domainsDueWithin30Days]), "info", routePath("cart-domain-renewals"), \Lang::trans("domainsrenewnow")));
        }
        return $this;
    }

    protected function checkForUnpaidInvoices()
    {
        if (!function_exists("getClientsStats")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        $clientId = $this->client->id;
        $currency = \WHMCS\Billing\Currency::factoryForClientArea();
        $clientStats = getClientsStats($clientId, $this->client);
        if (0 < $clientStats["numdueinvoices"]) {
            $this->addAlert(new \WHMCS\User\Alert(\Lang::trans("clientAlerts.invoicesUnpaid", [":numberOfInvoices" => $clientStats["numdueinvoices"], ":balanceDue" => $clientStats["dueinvoicesbalance"]]), "info", \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . DIRECTORY_SEPARATOR . "clientarea.php?action=masspay&all=true", \Lang::trans("invoicespaynow")));
        }
        if (0 < $clientStats["numoverdueinvoices"]) {
            $this->addAlert(new \WHMCS\User\Alert(\Lang::trans("clientAlerts.invoicesOverdue", [":numberOfInvoices" => $clientStats["numoverdueinvoices"], ":balanceDue" => $clientStats["overdueinvoicesbalance"]]), "warning", \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . DIRECTORY_SEPARATOR . "clientarea.php?action=masspay&all=true", \Lang::trans("invoicespaynow")));
        }
        return $this;
    }

    protected function checkForCreditBalance()
    {
        $creditBalance = $this->client->credit;
        if (0 < $creditBalance) {
            $currency = \WHMCS\Billing\Currency::factoryForClientArea();
            $this->addAlert(new \WHMCS\User\Alert(\Lang::trans("clientAlerts.creditBalance", [":creditBalance" => formatCurrency($creditBalance)]), "success", "", ""));
        }
        return $this;
    }
}
