<?php

namespace WHMCS\Module\Fraud\MaxMind;

class Payment extends \WHMCS\Model\AbstractModel
{
    protected $table = "mod_maxmind_payment";
    protected $fillable = ["processor", "whmcs_module"];

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->increments("id");
                $table->char("processor", 128)->default("");
                $table->char("whmcs_module", 128)->default("");
                $table->timestamp("created_at")->default("0000-00-00 00:00:00");
                $table->timestamp("updated_at")->default("0000-00-00 00:00:00");
                $table->unique("whmcs_module");
                $table->index(["processor", "whmcs_module"], "index_modules");
            });
            \WHMCS\Database\Capsule::table($this->getTable())->insert($this->getDefaultTableData());
        }
    }

    protected function getDefaultTableData()
    {
        return [["processor" => "authorizenet", "whmcs_module" => "acceptjs"], ["processor" => "authorizenet", "whmcs_module" => "authorize"], ["processor" => "authorizenet", "whmcs_module" => "authorizecim"], ["processor" => "authorizenet", "whmcs_module" => "authorizeecheck"], ["processor" => "authorizenet", "whmcs_module" => "planetauthorize"], ["processor" => "bluepay", "whmcs_module" => "bluepay"], ["processor" => "bluepay", "whmcs_module" => "bluepayecheck"], ["processor" => "bluepay", "whmcs_module" => "bluepayremote"], ["processor" => "ccavenue", "whmcs_module" => "ccavenue"], ["processor" => "ccavenue", "whmcs_module" => "ccavenuev2"], ["processor" => "eway", "whmcs_module" => "ewayv4"], ["processor" => "eway", "whmcs_module" => "ewaytokens"], ["processor" => "mollie", "whmcs_module" => "mollieideal"], ["processor" => "moneris_solutions", "whmcs_module" => "moneris"], ["processor" => "moneris_solutions", "whmcs_module" => "monerisvault"], ["processor" => "skrill", "whmcs_module" => "moneybookers"], ["processor" => "skrill", "whmcs_module" => "skrill"], ["processor" => "optimal_payments", "whmcs_module" => "optimalpayments"], ["processor" => "paypal", "whmcs_module" => "payflowpro"], ["processor" => "paypal", "whmcs_module" => "paypal"], ["processor" => "paypal", "whmcs_module" => "paypalpaymentspro"], ["processor" => "paypal", "whmcs_module" => "paypalpaymentsproref"], ["processor" => "payza", "whmcs_module" => "payza"], ["processor" => "psigate", "whmcs_module" => "psigate"], ["processor" => "securetrading", "whmcs_module" => "securetrading"], ["processor" => "stripe", "whmcs_module" => "stripe"], ["processor" => "sagepay", "whmcs_module" => "sagepayrepeats"], ["processor" => "sagepay", "whmcs_module" => "sagepaytokens"], ["processor" => "sagepay", "whmcs_module" => "protx"], ["processor" => "sagepay", "whmcs_module" => "protxvspform"], ["processor" => "usa_epay", "whmcs_module" => "usaepay"], ["processor" => "worldpay", "whmcs_module" => "worldpay"], ["processor" => "worldpay", "whmcs_module" => "worldpayfuturepay"], ["processor" => "worldpay", "whmcs_module" => "worldpayinvisible"], ["processor" => "worldpay", "whmcs_module" => "worldpayinvisiblexml"]];
    }

    public static function getPaymentModule($paymentModule)
    {
        try {
            $processor = self::where("whmcs_module", $paymentModule)->firstOrFail()->processor;
        } catch (\Exception $e) {
            $processor = "other";
        }
        return $processor;
    }
}
