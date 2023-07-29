<?php

namespace WHMCS\Billing\Invoice;

class Item extends \WHMCS\Model\AbstractModel implements \WHMCS\Billing\InvoiceItemInterface
{
    protected $table = "tblinvoiceitems";
    public $timestamps = false;
    protected $booleans = ["taxed"];
    protected $dates = ["dueDate"];
    protected $columnMap = ["relatedEntityId" => "relid"];
    protected $fillable = ["type", "relid", "description", "amount", "userid", "paymentmethod", "duedate", "taxed", "invoiceid"];

    public static function boot()
    {
        parent::boot();
        static::observe("WHMCS\\Billing\\Observers\\InvoiceItemObserver");
    }

    public function invoice()
    {
        return $this->belongsTo("WHMCS\\Billing\\Invoice", "invoiceid");
    }

    public function addon()
    {
        return $this->belongsTo("WHMCS\\Service\\Addon", "relid");
    }

    public function domain()
    {
        return $this->belongsTo("WHMCS\\Domain\\Domain", "relid");
    }

    public function service()
    {
        return $this->belongsTo("WHMCS\\Service\\Service", "relid");
    }

    public function scopeOnlyServices($query)
    {
        return $query->where("type", self::TYPE_SERVICE);
    }

    public function scopeOnlyAddons($query)
    {
        return $query->where("type", self::TYPE_SERVICE_ADDON);
    }

    public function scopeOnlyDomains($query)
    {
        return $query->whereIn("type", self::TYPE_GROUP_DOMAIN);
    }

    public function scopeClientId(\Illuminate\Database\Eloquent\Builder $query, int $userId)
    {
        return $query->where("userid", "=", $userId);
    }

    public function scopeNotInvoiced(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("invoiceid", "=", "0");
    }
}
