<?php

namespace WHMCS\Billing\Domains;

class Invoice
{
    protected $type = "grace";
    protected $defaultPaymentMethod = "";
    protected $fee = 0;
    protected $feeType = "";
    protected $feeDescription = "";

    public function __construct()
    {
        if (!function_exists("getClientsPaymentMethod")) {
            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "clientfunctions.php";
        }
        if (!function_exists("createInvoices")) {
            require ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "processinvoices.php";
        }
    }

    public function cancelInvoiceForExpiredDomains(\Illuminate\Database\Eloquent\Collection $expiredDomains)
    {
        $this->eagerLoadWithProvidedDomains($expiredDomains);
        $invoicesToWorkOn = [];
        foreach ($expiredDomains as $expiredDomain) {
            if (0 < $expiredDomain->invoiceItems->count()) {
                foreach ($expiredDomain->invoiceItems as $invoiceItem) {
                    if ($invoiceItem->invoice && !($invoiceItem->invoice && $invoiceItem->invoice->status != "Unpaid")) {
                        $invoiceID = $invoiceItem->invoice->id;
                        if (!isset($invoicesToWorkOn[$invoiceID])) {
                            $invoicesToWorkOn[$invoiceID] = [];
                        }
                        $invoicesToWorkOn[$invoiceID][] = $expiredDomain;
                    }
                }
            }
        }
        foreach ($invoicesToWorkOn as $key => $domainsInInvoice) {
            $invoiceToCheck = $domainsInInvoice[0]->invoiceItems->filter(function ($item) use($key) {
                return $item->invoiceid == $key;
            })->first()->invoice;
            $invoiceItemCount = $invoiceToCheck->items->count();
            if ($invoiceItemCount == count($domainsInInvoice)) {
                $this->cancelInvoice($invoiceToCheck->items->first(), $domainsInInvoice[0]);
            } else {
                foreach ($domainsInInvoice as $expiredDomain) {
                    foreach ($expiredDomain->invoiceItems as $invoiceItem) {
                        if ($invoiceItem->invoice && !($invoiceItem->invoice && $invoiceItem->invoice->status != "Unpaid")) {
                            try {
                                $this->deleteAppropriateItemsFromInvoice($invoiceItem, $expiredDomain);
                                $invoiceItem->invoice->updateInvoiceTotal();
                                logActivity("Removed Domain Renewal Line Item - Invoice ID: " . " " . $invoiceItem->invoiceId . " - Domain: " . $expiredDomain->domain, $invoiceItem->userId);
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }
            }
        }
        return $this;
    }

    public function cancelOrGenerateInvoiceForDomainGraceAndRedemption(\Illuminate\Database\Eloquent\Collection $domains, $type = "grace")
    {
        if (!in_array($type, ["grace", "redemption"])) {
            $type = "grace";
        }
        $this->type = $type;
        $newOrExisting = \WHMCS\Config\Setting::getValue("DomainExpirationFeeHandling");
        $this->eagerLoadWithProvidedDomains($domains);
        foreach ($domains as $domain) {
            $this->defaultPaymentMethod = getClientsPaymentMethod($domain->clientId);
            $fee = $domain->extension->gracePeriodFee;
            $description = \Lang::trans("domainGracePeriodFeeInvoiceItem", [":domainName" => $domain->domain]);
            $feeType = "DomainGraceFee";
            if ($this->type == "redemption") {
                $fee = $domain->extension->redemptionGracePeriodFee;
                $description = \Lang::trans("domainRedemptionPeriodFeeInvoiceItem", [":domainName" => $domain->domain]);
                $feeType = "DomainRedemptionFee";
            }
            if (!(is_null($fee) || $fee <= 0)) {
                if (0 < $fee) {
                    $fee = convertCurrency($fee, 1, $domain->client->currencyId);
                }
                $this->fee = $fee;
                $this->feeType = $feeType;
                $this->feeDescription = $description;
                $done = false;
                $invoiceItems = $domain->invoiceItems->sortByDesc("invoiceid");
                $unpaidInvoice = false;
                foreach ($invoiceItems as $invoiceItem) {
                    if ($invoiceItem->invoice->status == "Unpaid") {
                        $unpaidInvoice = true;
                        if ($unpaidInvoice) {
                            if ($newOrExisting == "existing") {
                                foreach ($invoiceItems as $invoiceItem) {
                                    if ($invoiceItem->invoice->status == "Unpaid") {
                                        $existingInvoiceItem = $invoiceItem->invoice->items()->where("type", $feeType)->where("relid", $domain->id)->count();
                                        if ($existingInvoiceItem === 0) {
                                            $this->addNewLineItem($domain, $invoiceItem);
                                            $invoiceItem->invoice->updateInvoiceTotal();
                                            try {
                                                sendMessage(\WHMCS\Mail\Template::where("name", "Invoice Modified")->firstOrFail(), $invoiceItem->invoiceId);
                                            } catch (\Exception $e) {
                                            }
                                        }
                                        $done = true;
                                    }
                                }
                            }
                            if (!$done) {
                                $paymentMethod = "";
                                $dueDate = "";
                                if (0 < $domain->invoiceItems->count()) {
                                    foreach ($invoiceItems as $invoiceItem) {
                                        if ($invoiceItem->invoice->status == "Unpaid") {
                                            $existingInvoiceItem = $invoiceItem->invoice->items()->where("type", $feeType)->where("relid", $domain->id)->count();
                                            if ($existingInvoiceItem === 0) {
                                                $itemCount = $invoiceItem->invoice->items->count();
                                                $invoiceId = $invoiceItem->invoiceId;
                                                $invoice = $invoiceItem->invoice;
                                                if ($itemCount == 1) {
                                                    $this->cancelInvoice($invoiceItem, $domain);
                                                    $this->duplicateExistingInvoiceItems($invoice, $domain, $paymentMethod, $dueDate);
                                                } else {
                                                    if (1 < $itemCount) {
                                                        try {
                                                            $this->duplicateExistingInvoiceItems($invoice, $domain, $paymentMethod, $dueDate);
                                                            $this->deleteAppropriateItemsFromInvoice($invoiceItem, $domain);
                                                            $invoiceItem->invoice->updateInvoiceTotal();
                                                            logActivity("Removed Domain Renewal Line Item - " . "Invoice ID: " . $invoiceId . " - Domain: " . $domain->domain, $invoiceItem->userId);
                                                            try {
                                                                sendMessage(\WHMCS\Mail\Template::where("name", "Invoice Modified")->firstOrFail(), $invoiceId);
                                                            } catch (\Exception $e) {
                                                            }
                                                        } catch (\Exception $e) {
                                                        }
                                                    }
                                                }
                                            } else {
                                                $done = true;
                                            }
                                        }
                                    }
                                }
                                if ($paymentMethod) {
                                    $this->defaultPaymentMethod = $paymentMethod;
                                }
                                if (!$done) {
                                    if ($dueDate) {
                                        try {
                                            $dueDate = \WHMCS\Carbon::parse($dueDate);
                                        } catch (\Exception $e) {
                                            $dueDate = NULL;
                                        }
                                    }
                                    $this->addNewLineItem($domain, NULL, $dueDate);
                                    createInvoices($domain->clientId, "", false);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $this;
    }

    private function duplicateExistingInvoiceItems(\WHMCS\Billing\Invoice $invoice, \WHMCS\Domain\Domain $domain, &$paymentMethod, &$dueDate)
    {
        $invoiceItems = $invoice->items()->whereIn("type", ["Domain", "DomainGraceFee", "DomainRedemptionFee"])->where("relid", $domain->id)->get();
        foreach ($invoiceItems as $existingInvoiceItem) {
            $newItem = $existingInvoiceItem->replicate();
            $newItem->invoiceId = 0;
            $newItem->save();
            if (!$paymentMethod) {
                $paymentMethod = $existingInvoiceItem->paymentMethod;
            }
            if (!$dueDate) {
                $dueDate = $existingInvoiceItem->dueDate;
            }
        }
    }

    private function deleteAppropriateItemsFromInvoice(\WHMCS\Billing\Invoice\Item $invoiceItem, \WHMCS\Domain\Domain $expiredDomain)
    {
        \WHMCS\Billing\Invoice\Item::where(function (\Illuminate\Database\Eloquent\Builder $query) use($invoiceItem, $expiredDomain) {
            $query->where("invoiceid", $invoiceItem->invoiceId)->where("relid", $expiredDomain->id)->whereIn("type", ["Domain", "DomainGraceFee", "DomainRedemptionFee", "PromoDomain"]);
        })->orWhere(function (\Illuminate\Database\Eloquent\Builder $query) use($invoiceItem) {
            $query->where("invoiceid", $invoiceItem->invoiceId)->whereIn("type", ["GroupDiscount", "LateFee"]);
        })->delete();
    }

    private function eagerLoadWithProvidedDomains(\Illuminate\Database\Eloquent\Collection &$domains)
    {
        $domains->load(["invoiceItems" => function (\Illuminate\Database\Eloquent\Relations\HasMany $query) {
            $query->where("type", "Domain");
        }, "invoiceItems.invoice", "invoiceItems.invoice.items" => function (\Illuminate\Database\Eloquent\Relations\HasMany $query) {
            $query->whereNotIn("type", ["PromoDomain", "GroupDiscount", "LateFee", "DomainGraceFee", "DomainRedemptionFee"]);
        }]);
    }

    private function cancelInvoice(\WHMCS\Billing\Invoice\Item $item, \WHMCS\Domain\Domain $domain)
    {
        $item->invoice->status = \WHMCS\Billing\Invoice::STATUS_CANCELLED;
        $item->invoice->dateCancelled = \WHMCS\Carbon::now();
        $item->invoice->save();
        logActivity("Cancelled Domain Renewal Invoice - Invoice ID" . ": " . $item->invoiceId . " - Domain: " . $domain->domain, $item->userId);
        run_hook("InvoiceCancelled", ["invoiceid" => $item->invoice->id]);
    }

    private function addNewLineItem(\WHMCS\Domain\Domain $domain, \WHMCS\Billing\Invoice\Item $item = NULL, \WHMCS\Carbon $dueDate = NULL)
    {
        $invoiceId = 0;
        if (!$dueDate) {
            $dueDate = $domain->nextDueDate;
        }
        $paymentMethod = NULL;
        if (!is_null($item)) {
            $invoiceId = $item->invoiceId;
            $dueDate = $item->dueDate;
            $paymentMethod = $item->paymentMethod;
        }
        $taxDomains = \WHMCS\Config\Setting::getValue("TaxEnabled") && \WHMCS\Config\Setting::getValue("TaxDomains");
        if (!$paymentMethod) {
            $paymentMethod = $this->defaultPaymentMethod;
        }
        $newItem = new \WHMCS\Billing\Invoice\Item();
        $newItem->userId = $domain->clientId;
        $newItem->type = $this->feeType;
        $newItem->relatedEntityId = $domain->id;
        $newItem->description = $this->feeDescription;
        $newItem->amount = $this->fee;
        $newItem->taxed = $taxDomains;
        $newItem->dueDate = $dueDate;
        $newItem->paymentMethod = $paymentMethod;
        $newItem->invoiceId = $invoiceId;
        $newItem->save();
    }
}
