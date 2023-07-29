<?php

namespace WHMCS\Cron\Console\Input;

trait LegacyOptionsTrait
{
    protected $map = ["invoices" => "CreateInvoices", "affcommissions" => "AffiliateCommissions", "affreports" => "AffiliateReports", "backups" => "DatabaseBackup", "cancelrequests" => "CancellationRequests", "ccexpirynotices" => "CreditCardExpiryNotices", "ccprocessing" => "ProcessCreditCardPayments", "clientstatussync" => "AutoClientStatusSync", "closetickets" => "CloseInactiveTickets", "domainrenewalnotices" => "DomainRenewalNotices", "emailmarketing" => "EmailMarketer", "escalations" => "TicketEscalations", "fixedtermterminations" => "FixedTermTerminations", "invoicereminders" => "InvoiceReminders", "latefees" => "AddLateFees", "overagesbilling" => "OverageBilling", "suspensions" => "AutoSuspensions", "terminations" => "AutoTerminations", "updatepricing" => "CurrencyUpdateProductPricing", "updaterates" => "CurrencyUpdateExchangeRate", "usagestats" => "UpdateServerUsage"];
    protected $renameMap = ["DomainExpirySync" => "DomainStatusSync"];

    public function getMap()
    {
        return $this->map;
    }

    public function setMap($map)
    {
        $this->map = $map;
        return $this;
    }

    public function convertLegacyOptions($options)
    {
        $map = $this->getMap();
        foreach ($options as $key => $value) {
            $value = ltrim($value, "--");
            if (array_key_exists($value, $map)) {
                $options[$key] = "--" . $map[$value];
            }
        }
        return $options;
    }

    public function convertRenamedOptions($options = [])
    {
        $renameMap = $this->renameMap;
        foreach ($options as $key => $value) {
            $value = ltrim($value, "--");
            if (array_key_exists($value, $renameMap)) {
                $options[$key] = "--" . $renameMap[$value];
            }
        }
        return $options;
    }
}
