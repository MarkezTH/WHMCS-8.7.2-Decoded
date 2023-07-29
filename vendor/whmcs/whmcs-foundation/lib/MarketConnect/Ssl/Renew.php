<?php

namespace WHMCS\MarketConnect\Ssl;

class Renew
{
    public $orderNumber = NULL;
    public $term = NULL;
    public $callbackUrl = NULL;
    public $useInstantIssuance = false;
    protected $ssl = NULL;
    protected $finalized = false;

    public function __construct(\WHMCS\Service\Ssl $ssl)
    {
        $this->ssl = $ssl;
    }

    public function populate()
    {
        $this->order($this->ssl->getOrderNumber());
        return $this;
    }

    public function order($self, $number)
    {
        $this->orderNumber = $number;
        return $this;
    }

    public function term($self, $number)
    {
        $this->term = $number;
        return $this;
    }

    public function callbackUrl($self, $url)
    {
        $this->callbackUrl = $url;
        return $this;
    }

    public function finalize()
    {
        if ($this->isFinalized()) {
            return $this;
        }
        $this->callbackUrl = fqdnRoutePath("store-ssl-callback");
        $this->finalized = true;
        return $this;
    }

    public function isFinalized()
    {
        return $this->finalized;
    }

    public function setUseInstantIssuance($self, $value)
    {
        $this->useInstantIssuance = $value;
        return $this;
    }
}
