<?php

namespace WHMCS\Product;

class ConfigOptions
{
    protected $cache = [];

    protected function getCurrencyID()
    {
        $whmcs = \WHMCS\Application::getInstance();
        return $whmcs->getCurrencyID();
    }

    protected function isCached($productID)
    {
        return isset($this->cache[$productID]) && is_array($this->cache[$productID]);
    }

    protected function getFromCache($productID, $optionLabel)
    {
        if ($this->isCached($productID)) {
            return $this->cache[$productID][$optionLabel];
        }
        return [];
    }

    protected function storeToCache($productID, $optionLabel, $optionsData)
    {
        $this->cache[$productID][$optionLabel] = $optionsData;
        return true;
    }

    protected function loadData($productID)
    {
        $ops = [];
        if (!$this->isCached($productID)) {
            $currencyId = $this->getCurrencyID();
            $info = [];
            $currencyIdInt = (int) $currencyId;
            $productIdInt = (int) $productID;
            $query = "SELECT tblproductconfigoptions.id,\n   tblproductconfigoptions.optionname,\n   tblproductconfigoptions.optiontype,\n   tblproductconfigoptions.qtyminimum,\n   tblproductconfigoptions.qtymaximum,\n    (\n        SELECT\n        CONCAT(msetupfee, '|', qsetupfee, '|', ssetupfee, '|', asetupfee, '|', bsetupfee, '|', tsetupfee, '|',\n                monthly, '|', quarterly, '|', semiannually, '|', annually, '|', biennially, '|', triennially)\n        FROM\n        tblproductconfigoptionssub\n        LEFT JOIN tblpricing ON tblproductconfigoptionssub.id = tblpricing.relid\n            AND tblpricing.type = 'configoptions'\n            AND tblpricing.currency = '" . $currencyIdInt . "'\n        WHERE configid = tblproductconfigoptions.id\n        AND hidden = 0\n        ORDER BY sortorder ASC, tblproductconfigoptionssub.id ASC\n        LIMIT 1\n    )\nFROM tblproductconfigoptions\nINNER JOIN tblproductconfiglinks ON tblproductconfigoptions.gid = tblproductconfiglinks.gid\nWHERE tblproductconfiglinks.pid = '" . $productIdInt . "'\nAND tblproductconfigoptions.hidden = 0;";
            $result = full_query($query);
            while ($data = mysql_fetch_array($result)) {
                $info[$data[0]] = ["name" => $data["optionname"], "type" => $data["optiontype"], "qtyminimum" => $data["qtyminimum"], "qtymaximum" => $data["qtymaximum"]];
                $ops[$data[0]] = explode("|", $data[5]);
            }
            $this->storeToCache($productID, "info", $info);
            $this->storeToCache($productID, "pricing" . $currencyId, $ops);
        }
        return $ops;
    }

    public function getBasePrice($productID, $billingCycle)
    {
        $cycles = new \WHMCS\Billing\Cycles();
        if ($cycles->isValidSystemBillingCycle($billingCycle)) {
            $this->loadData($productID);
            $optionsInfo = $this->getFromCache($productID, "info");
            $optionsPricing = $this->getFromCache($productID, "pricing" . $this->getCurrencyID());
            $pricingObj = new \WHMCS\Billing\LegacyPricing();
            $cycleindex = array_search($billingCycle, $pricingObj->getDBFields());
            $price = 0;
            foreach ($optionsPricing as $configID => $pricing) {
                if ($optionsInfo[$configID]["type"] == 1 || $optionsInfo[$configID]["type"] == 2) {
                    $price += $pricing[$cycleindex];
                } else {
                    if ($optionsInfo[$configID]["type"] != 3) {
                        if ($optionsInfo[$configID]["type"] == 4) {
                            $minquantity = $optionsInfo[$configID]["qtyminimum"];
                            if (0 < $minquantity) {
                                $price += $minquantity * $pricing[$cycleindex];
                            }
                        }
                    }
                }
            }
            return $price;
        } else {
            return false;
        }
    }

    public function hasConfigOptions($productID)
    {
        $this->loadData($productID);
        $optionsInfo = $this->getFromCache($productID, "info");
        if (0 < count($optionsInfo)) {
            return true;
        }
        return false;
    }
}
