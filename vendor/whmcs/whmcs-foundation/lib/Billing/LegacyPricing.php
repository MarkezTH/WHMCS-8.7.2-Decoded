<?php

namespace WHMCS\Billing;

class LegacyPricing
{
    protected $db_fields = ["msetupfee", "qsetupfee", "ssetupfee", "asetupfee", "bsetupfee", "tsetupfee", "monthly", "quarterly", "semiannually", "annually", "biennially", "triennially"];

    public function getDBFields()
    {
        return $this->db_fields;
    }
}
