<?php

namespace M101Domain;

class Domain
{
    public $name = NULL;
    public $status = [];
    public $registrant = NULL;
    public $contacts = [];
    public $ns = [];
    public $cr_date = NULL;
    public $up_date = NULL;
    public $ex_date = NULL;
    public $key = NULL;
    protected $lockedStatuses = ["clientTransferProhibited", "clientHold", "serverTransferProhibited", "serverHold"];

    public function isLocked()
    {
        foreach ($this->status as $status) {
            if (in_array($status, $this->lockedStatuses)) {
                return true;
            }
        }
        return false;
    }
}
