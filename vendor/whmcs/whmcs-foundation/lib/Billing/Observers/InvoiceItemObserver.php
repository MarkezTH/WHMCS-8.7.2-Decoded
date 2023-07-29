<?php

namespace WHMCS\Billing\Observers;

class InvoiceItemObserver
{
    public function created(\WHMCS\Billing\Invoice\Item $item)
    {
        $this->touchParent($item);
    }

    public function deleted(\WHMCS\Billing\Invoice\Item $item)
    {
        $this->touchParent($item);
    }

    public function forceDeleted(\WHMCS\Billing\Invoice\Item $item)
    {
        $this->touchParent($item);
    }

    public function updated(\WHMCS\Billing\Invoice\Item $item)
    {
        $this->touchParent($item);
    }

    protected function touchParent(\WHMCS\Billing\Invoice\Item $item)
    {
        if ($item->invoiceId) {
            $item->touchOwners();
        }
    }
}
