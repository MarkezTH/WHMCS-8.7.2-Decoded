<?php

namespace WHMCS\Service\Upgrade;

class Upgrade extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblupgrades";
    protected $columnMap = ["userId" => "userid", "orderId" => "orderid", "entityId" => "relid", "originalValue" => "originalvalue", "newValue" => "newvalue", "upgradeAmount" => "amount", "recurringChange" => "recurringchange", "creditAmount" => "credit_amount", "daysRemaining" => "days_remaining", "totalDaysInCycle" => "total_days_in_cycle", "newRecurringAmount" => "new_recurring_amount"];
    protected $dates = ["date"];
    protected $casts = ["calculation" => "array"];
    public $timestamps = false;
    public $qty = 1;
    public $minimumQuantity = 1;
    public $currency = NULL;
    public $applyTax = false;
    public $allowMultipleQuantities = false;
    public $localisedNewCycle = NULL;
    const TYPE_SERVICE = "service";
    const TYPE_ADDON = "addon";
    const TYPE_PACKAGE = "package";
    const TYPE_CONFIGOPTIONS = "configoptions";

    public function order()
    {
        return $this->belongsTo("WHMCS\\Order\\Order", "orderid");
    }

    public function service()
    {
        return $this->hasOne("WHMCS\\Service\\Service", "id", "relid");
    }

    public function addon()
    {
        return $this->hasOne("WHMCS\\Service\\Addon", "id", "relid");
    }

    public function originalProduct()
    {
        return $this->hasOne("WHMCS\\Product\\Product", "id", "originalvalue");
    }

    public function newProduct()
    {
        return $this->hasOne("WHMCS\\Product\\Product", "id", "newvalue");
    }

    public function originalAddon()
    {
        return $this->hasOne("WHMCS\\Product\\Addon", "id", "originalvalue");
    }

    public function newAddon()
    {
        return $this->hasOne("WHMCS\\Product\\Addon", "id", "newvalue");
    }

    public function isAddon()
    {
        return $this->type === "addon";
    }

    public function isConfigOptions()
    {
        return $this->type === "configoptions";
    }

    public function isPackage()
    {
        return $this->type === "package";
    }

    public function isService()
    {
        return $this->type === "service";
    }
}
