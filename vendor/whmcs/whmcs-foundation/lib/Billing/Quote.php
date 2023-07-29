<?php

namespace WHMCS\Billing;

class Quote extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblquotes";
    public $timestamps = false;
    protected $columnMap = ["status" => "stage", "validUntilDate" => "validuntil", "clientId" => "userid", "lastModifiedDate" => "lastmodified", "customerNotes" => "customernotes", "adminNotes" => "adminnotes", "dateCreated" => "datecreated", "dateSent" => "datesent", "dateAccepted" => "dateaccepted", "taxId" => "tax_id"];
    protected $dates = ["validuntil", "datecreated", "lastmodified", "datesent", "dateaccepted"];

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public function items()
    {
        return $this->hasMany("WHMCS\\Billing\\Quote\\Item", "quoteid");
    }

    public function getLink()
    {
        return \App::get_admin_folder_name() . "/quotes.php?action=manage&id.php?id=" . $this->id;
    }
}
