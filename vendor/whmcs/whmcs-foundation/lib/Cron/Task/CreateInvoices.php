<?php

namespace WHMCS\Cron\Task;

class CreateInvoices extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1520;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "Generate Invoices";
    protected $defaultName = "Invoices";
    protected $systemName = "CreateInvoices";
    protected $outputs = ["invoice.created" => ["defaultValue" => 0, "identifier" => "invoice.created", "name" => "Total Invoices"], "action.detail" => ["defaultValue" => "", "identifier" => "action.detail", "name" => "Action Detail"]];
    protected $icon = "far fa-file-alt";
    protected $successCountIdentifier = "invoice.created";
    protected $failedCountIdentifier = "";
    protected $successKeyword = "Generated";
    protected $hasDetail = true;

    public function __invoke()
    {
        $this->setDetails(["success" => []]);
        if (!function_exists("createInvoices")) {
            include_once ROOTDIR . "/includes/processinvoices.php";
        }
        createInvoices("", "", "", "", $this);
        return $this;
    }
}
