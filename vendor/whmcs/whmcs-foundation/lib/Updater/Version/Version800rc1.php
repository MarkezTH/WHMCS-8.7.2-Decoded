<?php

namespace WHMCS\Updater\Version;

class Version800rc1 extends IncrementalVersion
{
    protected $updateActions = ["fixCronTaskNaming", "removeTwoYearMarketConnectSslTerms"];

    public function __construct(\WHMCS\Version\SemanticVersion $version = NULL)
    {
        parent::__construct($version);
        $this->filesToRemove[] = ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "hookfunctions.php";
    }

    public function fixCronTaskNaming()
    {
        $query = \WHMCS\Database\Capsule::table("tbltask")->where("class_name", "WHMCS\\Cron\\Task\\DomainStatusSync");
        if ($query->count()) {
            $query->update(["name" => "Domain Status Synchronisation"]);
        }
        return $this;
    }

    protected function removeTwoYearMarketConnectSslTerms()
    {
        $currency = getCurrency();
        $products = $addons = [];
        foreach (\WHMCS\Product\Product::ssl()->get() as $product) {
            $biennial = $product->pricing()->biennial();
            if (!is_null($biennial)) {
                $products[] = $product->id;
            }
        }
        foreach (\WHMCS\Product\Addon::ssl()->get() as $addon) {
            $biennial = $addon->pricing()->biennial();
            if (!is_null($biennial)) {
                $addons[] = $addon->id;
            }
        }
        if (count($products)) {
            \WHMCS\Database\Capsule::table("tblpricing")->where("type", "product")->whereIn("relid", $products)->update(["bsetupfee" => "0", "biennially" => "-1"]);
        }
        if (count($addons)) {
            \WHMCS\Database\Capsule::table("tblpricing")->where("type", "addon")->whereIn("relid", $addons)->update(["bsetupfee" => "0", "biennially" => "-1"]);
        }
        return $this;
    }
}
