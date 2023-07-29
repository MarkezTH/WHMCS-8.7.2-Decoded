<?php

namespace Transip\Api\Library\Repository;

class InvoiceRepository extends ApiRepository
{
    const RESOURCE_NAME = "invoices";

    public function getAll()
    {
        $invoices = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $invoicesArray = $this->getParameterFromResponse($response, "invoices");
        foreach ($invoicesArray as $invoiceArray) {
            $invoices[] = new \Transip\Api\Library\Entity\Invoice($invoiceArray);
        }
        return $invoices;
    }

    public function getSelection($page, int $itemsPerPage)
    {
        $invoices = [];
        $query = ["pageSize" => $itemsPerPage, "page" => $page];
        $response = $this->httpClient->get($this->getResourceUrl(), $query);
        $invoicesArray = $this->getParameterFromResponse($response, "invoices");
        foreach ($invoicesArray as $invoiceArray) {
            $invoices[] = new \Transip\Api\Library\Entity\Invoice($invoiceArray);
        }
        return $invoices;
    }

    public function getByInvoiceNumber($Invoice, $invoiceNumber)
    {
        $response = $this->httpClient->get($this->getResourceUrl($invoiceNumber));
        $invoice = $this->getParameterFromResponse($response, "invoice");
        return new \Transip\Api\Library\Entity\Invoice($invoice);
    }
}
