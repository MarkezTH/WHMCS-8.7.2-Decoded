<?php

namespace WHMCS\User\Client;

class Group extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblclientgroups";
    public $timestamps = false;
    protected $columnMap = ["name" => "groupname", "color" => "groupcolour", "discountPercentage" => "discountpercent", "exemptFromAutomation" => "susptermexempt"];
    protected $casts = ["susptermexempt" => "bool"];

    public function clients()
    {
        return $this->hasMany("WHMCS\\User\\Client", "groupid", "id");
    }
}
