<?php

namespace WHMCS\Payment\PayMethod\Adapter;

class BankAccount extends BankAccountModel implements \WHMCS\Payment\Contracts\BankAccountDetailsInterface
{
    use \WHMCS\Payment\PayMethod\Traits\BankAccountDetailsTrait;
}
