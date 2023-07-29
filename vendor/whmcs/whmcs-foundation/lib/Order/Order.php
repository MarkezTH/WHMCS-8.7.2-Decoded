<?php

namespace WHMCS\Order;

class Order extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblorders";
    public $timestamps = false;
    protected $dates = ["date"];
    protected $columnMap = ["clientId" => "userid", "orderNumber" => "ordernum"];
    protected $appends = ["isPaid"];

    public function client()
    {
        return $this->belongsTo("WHMCS\\User\\Client", "userid");
    }

    public function contact()
    {
        return $this->belongsTo("WHMCS\\User\\Client\\Contact", "contactid");
    }

    public function services()
    {
        return $this->hasMany("WHMCS\\Service\\Service", "orderid");
    }

    public function addons()
    {
        return $this->hasMany("WHMCS\\Service\\Addon", "orderid");
    }

    public function domains()
    {
        return $this->hasMany("WHMCS\\Domain\\Domain", "orderid");
    }

    public function invoice()
    {
        return $this->hasOne("WHMCS\\Billing\\Invoice", "id", "invoiceid");
    }

    public function promotion()
    {
        return $this->hasOne("WHMCS\\Product\\Promotion", "code", "promocode");
    }

    public function requestor()
    {
        return $this->belongsTo("WHMCS\\User\\User", "requestor_id");
    }

    public function upgrade()
    {
        return $this->hasOne("WHMCS\\Service\\Upgrade\\Upgrade", "orderid");
    }

    public function adminRequestor()
    {
        return $this->belongsTo("WHMCS\\User\\Admin", "admin_requestor_id");
    }

    public function getOrderDataAttribute()
    {
        $orderData = $this->getRawAttribute("orderdata");
        if (!is_string($orderData) || strlen($orderData) == 0) {
            return NULL;
        }
        $data = json_decode($orderData, true);
        if (is_null($data) && json_last_error() !== JSON_ERROR_NONE) {
            $data = safe_unserialize($orderData);
        }
        return $data;
    }

    public function getIsPaidAttribute()
    {
        if (0 < $this->invoiceId) {
            return $this->invoice->status == "Paid";
        }
        return false;
    }

    public function getNameservers()
    {
        return removeEmptyValues(arrayTrim(explode(",", $this->nameservers)));
    }

    public function getEppCodeByDomain($domain)
    {
        $eppCodes = safe_unserialize($this->transferSecret);
        if (is_array($eppCodes) && array_key_exists($domain, $eppCodes)) {
            return $eppCodes[$domain];
        }
        return NULL;
    }

    public static function add(int $clientId, $orderNumber, $paymentMethod, $notes, int $contactId = 0, int $requestorId = 0, int $adminRequestorId = 0)
    {
        $order = new self();
        $order->userId = $clientId;
        $order->orderNumber = $orderNumber;
        $order->paymentMethod = $paymentMethod;
        $order->notes = $notes;
        $order->contactId = $contactId;
        $order->requestorId = $requestorId;
        $order->adminRequestorId = $adminRequestorId;
        $order->date = \WHMCS\Carbon::now();
        $order->status = \WHMCS\Utility\Status::PENDING;
        $order->ipAddress = \App::getRemoteIp();
        $order->save();
        logActivity("New Order Placed - Order ID: " . $order->id . " - User ID: " . $clientId, $clientId);
        return $order;
    }
}
