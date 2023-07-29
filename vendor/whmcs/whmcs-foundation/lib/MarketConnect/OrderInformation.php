<?php

namespace WHMCS\MarketConnect;

class OrderInformation
{
    public $orderNumber = "";
    public $domain = "";
    public $status = "";
    public $statusDescription = "";
    private $additionalInformation = [];
    public $timestamp = 0;
    public $cacheExpiryTime = 240;

    public function __construct($orderNumber = NULL)
    {
        if (!is_null($orderNumber)) {
            $this->orderNumber = $orderNumber;
            $this->loadFromCache($orderNumber);
        }
    }

    public function getAdditionalInformation()
    {
        return $this->additionalInformation;
    }

    public function hasAdditionalInformation()
    {
        return 0 < count($this->getAdditionalInformation());
    }

    public function getAdditionalInformationValue($dotPathName)
    {
        return \Illuminate\Support\Arr::get($this->getAdditionalInformation(), $dotPathName);
    }

    public function setAdditionalInformationValue($dotPathName, $value)
    {
        \Illuminate\Support\Arr::set($this->additionalInformation, $dotPathName, $value);
    }

    public function renameAdditionalInformationRootLevelKeys($searchReplace)
    {
        $output = [];
        foreach ($this->additionalInformation as $key => $value) {
            if (isset($searchReplace[$key])) {
                $key = $searchReplace[$key];
            }
            $output[$key] = $value;
        }
        $this->additionalInformation = $output;
    }

    public static function factory($params)
    {
        $orderNumber = isset($params["customfields"]["Order Number"]) ? $params["customfields"]["Order Number"] : NULL;
        return new OrderInformation($orderNumber);
    }

    public static function cache($orderNumber, $data)
    {
        if (empty($orderNumber)) {
            return false;
        }
        $data["timestamp"] = time();
        $transientData = new \WHMCS\TransientData();
        $transientData->store("marketconnect.order." . $orderNumber, json_encode($data), 108000);
    }

    protected function loadFromCache($orderNumber)
    {
        $transientData = new \WHMCS\TransientData();
        $data = $transientData->retrieve("marketconnect.order." . $orderNumber);
        if (!is_null($data)) {
            $this->load(json_decode($data, true));
        }
    }

    protected function load($data)
    {
        $this->domain = (string) $data["domain"];
        $this->status = (string) $data["status"];
        $this->statusDescription = (string) $data["statusDescription"];
        $this->additionalInformation = (array) $data["additionalInfo"];
        $this->timestamp = (int) $data["timestamp"];
    }

    public function getLastUpdated()
    {
        if (!empty($this->timestamp)) {
            $timestamp = \WHMCS\Carbon::createFromTimestamp($this->timestamp);
            if (!is_null($timestamp)) {
                return $timestamp->diffForHumans();
            }
        }
        return "Just now";
    }

    public function isCacheStale()
    {
        if (!empty($this->timestamp)) {
            $timestamp = \WHMCS\Carbon::createFromTimestamp($this->timestamp);
            if (!is_null($timestamp)) {
                return $this->cacheExpiryTime < $timestamp->diffInMinutes();
            }
        }
        return true;
    }
}
