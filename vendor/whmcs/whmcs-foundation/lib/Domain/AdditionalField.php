<?php

namespace WHMCS\Domain;

class AdditionalField extends \WHMCS\Model\AbstractModel
{
    protected $table = "tbldomainsadditionalfields";
    protected $fillable = ["domainid", "name"];

    public function domain()
    {
        return $this->belongsTo("WHMCS\\Domain\\Domain", "domainid");
    }
}
