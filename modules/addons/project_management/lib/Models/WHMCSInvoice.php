<?php

namespace WHMCS\Module\Addon\ProjectManagement\Models;

class WHMCSInvoice extends \WHMCS\Billing\Invoice
{
    protected $appends = ["balance"];
}
