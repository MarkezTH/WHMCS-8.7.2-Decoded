<?php

namespace Transip\Api\Library\Repository\Invoice;

class ItemRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "invoice-items";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\InvoiceRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByInvoiceNumber($invoiceNumber)
    {
        $invoiceitems = [];
        $response = $this->httpClient->get($this->getResourceUrl($invoiceNumber));
        $invoiceItemsArray = $this->getParameterFromResponse($response, "invoiceItems");
        foreach ($invoiceItemsArray as $invoiceItemArray) {
            $invoiceitems[] = new \Transip\Api\Library\Entity\Invoice\InvoiceItem($invoiceItemArray);
        }
        return $invoiceitems;
    }
}
