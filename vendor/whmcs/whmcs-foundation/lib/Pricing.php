<?php

namespace WHMCS;

class Pricing
{
    private $data = [];
    private $cycles = ["monthly" => 1, "quarterly" => 3, "semiannually" => 6, "annually" => 12, "biennially" => 24, "triennially" => 36];
    protected $currency = NULL;

    public function loadPricing($type, $relid, $pricingCurrency = NULL, int $qty = 1)
    {
        if (is_null($pricingCurrency)) {
            global $currency;
            $pricingCurrency = $currency;
        }
        if (is_array($pricingCurrency) || $pricingCurrency instanceof Billing\Currency) {
            $this->currency = $pricingCurrency;
        }
        if (is_null($this->currency)) {
            $this->currency = getCurrency();
        }
        $result = select_query("tblpricing", "", ["type" => $type, "currency" => (int) $this->currency["id"], "relid" => (int) $relid]);
        $data = mysql_fetch_array($result);
        if (is_array($data)) {
            foreach ($data as $key => $price) {
                if (is_numeric($price) && 0 < $price) {
                    $data[$key] = $price * $qty;
                }
            }
            $this->data = $data;
        } else {
            $this->data = ["monthly" => "-1", "quarterly" => "-1", "semiannually" => "-1", "annually" => "-1", "biennially" => "-1", "triennially" => "-1"];
        }
    }

    public function getData($key)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : "";
    }

    public function getRelID()
    {
        return (int) $this->getData("relid");
    }

    public function getSetup($cycle)
    {
        return (double) $this->getData(substr($cycle, 0, 1) . "setupfee");
    }

    public function getPrice($cycle)
    {
        return (double) $this->getData($cycle);
    }

    public function getAvailableBillingCycles()
    {
        $active_cycles = [];
        foreach ($this->cycles as $cycle => $months) {
            if ($this->getData($cycle) != -1) {
                $active_cycles[] = $cycle;
            }
        }
        return $active_cycles;
    }

    public function hasBillingCyclesAvailable()
    {
        return 0 < count($this->getAvailableBillingCycles()) ? true : false;
    }

    public function getFirstAvailableCycle()
    {
        $cycles = $this->getAvailableBillingCycles();
        return 0 < count($cycles) ? $cycles[0] : "";
    }

    public function getAllCycleOptions()
    {
        $cycles = [];
        foreach ($this->cycles as $cycle => $months) {
            $price = $this->getPrice($cycle);
            if ($price != -1) {
                $cycles[] = $this->getCycleData($cycle, $months);
            }
        }
        return $cycles;
    }

    public function getOneTimePricing()
    {
        $data = $this->getCycleData("monthly");
        $data["cycle"] = "onetime";
        return $data;
    }

    protected function getCycleData($cycle, $months = 0)
    {
        $setupfee = $this->getSetup($cycle);
        $price = $this->getPrice($cycle);
        if (!function_exists("getCartConfigOptions")) {
            require ROOTDIR . "/includes/configoptionsfunctions.php";
        }
        $configoptions = getCartConfigOptions($this->getRelID(), [], $cycle, "", true);
        if (count($configoptions)) {
            foreach ($configoptions as $option) {
                $setupfee += $option["selectedsetup"];
                $price += $option["selectedrecurring"];
            }
        }
        if (0 < $months) {
            $breakdown = ["monthly" => new View\Formatter\Price($price / $months, $this->currency), "yearly" => 12 <= $months ? new View\Formatter\Price($price / ($months / 12), $this->currency) : NULL];
        } else {
            $breakdown = [];
        }
        return ["cycle" => $cycle, "setupfee" => new View\Formatter\Price($setupfee, $this->currency), "price" => new View\Formatter\Price($price, $this->currency), "breakdown" => $breakdown];
    }

    public function getAllCycleOptionsIndexedByCycle()
    {
        $cycles = $this->getAllCycleOptions();
        $cyclesToReturn = [];
        foreach ($cycles as $key => $data) {
            $cyclesToReturn[$data["cycle"]] = $data;
        }
        return $cyclesToReturn;
    }
}
