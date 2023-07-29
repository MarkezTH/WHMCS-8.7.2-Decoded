<?php

namespace WHMCS\Security\BanControl;

class EmailDomain extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblbannedemails";
    protected $attributes = ["count" => 0];
    protected $fillable = ["domain", "count"];
    public $timestamps = false;
}
