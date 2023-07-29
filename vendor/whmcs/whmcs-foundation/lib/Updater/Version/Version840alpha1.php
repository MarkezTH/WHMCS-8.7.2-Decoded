<?php

namespace WHMCS\Updater\Version;

class Version840alpha1 extends IncrementalVersion implements \WHMCS\Scheduling\Contract\JobInterface
{
    use \WHMCS\Scheduling\Jobs\JobTrait;
    protected $updateActions = ["createStripeTransactionAuditJob", "setMarketConnectGeoTrustQuickSSLCertificateAsFeatured", "updateModPleskAccountsTableSchema"];
    const JOB_NAME = "stripe.transaction.audit";

    public function __construct(\WHMCS\Version\SemanticVersion $version = NULL)
    {
        if ($version) {
            parent::__construct($version);
        }
    }

    public function createStripeTransactionAuditJob()
    {
        $needToUpdate = \WHMCS\Version\SemanticVersion::compare(static::$startVersion, new \WHMCS\Version\SemanticVersion("8.2.1-release.1"), ">");
        if (!$needToUpdate) {
            return $this;
        }
        \WHMCS\Scheduling\Jobs\Queue::addOrUpdate(static::JOB_NAME, static::class, "resolveIncorrectStripeTransactions", []);
        return $this;
    }

    public static function resolveIncorrectStripeTransactions($offset = NULL)
    {
        $transactionsToCheck = $currencyCache = [];
        $newOffset = $transaction = NULL;
        try {
            $gatewayInterface = \WHMCS\Module\Gateway::factory("stripe");
            $params = $gatewayInterface->getParams();
        } catch (\Exception $e) {
            return NULL;
        }
        stripe_start_stripe($params);
        $requestOptions = ["limit" => 100, "created" => ["gte" => strtotime("2021-08-31 00:00:01")], "type" => \Stripe\BalanceTransaction::TYPE_CHARGE];
        if ($offset) {
            $requestOptions["starting_after"] = $offset;
        }
        $transactions = \Stripe\BalanceTransaction::all($requestOptions);
        foreach ($transactions->data as $transaction) {
            $transactionsToCheck[$transaction->id] = $transaction;
            $newOffset = trim($transaction->id);
        }
        unset($transactions);
        unset($transaction);
        $transactionRecords = \WHMCS\Billing\Payment\Transaction::whereIn("transid", array_keys($transactionsToCheck));
        if (empty($transactionsToCheck) || $transactionRecords->count() === 0) {
            \WHMCS\Scheduling\Jobs\Queue::remove(static::JOB_NAME);
            return NULL;
        }
        $transactionRecords = $transactionRecords->get();
        foreach ($transactionRecords as $whmcsTransaction) {
            $stripeTransaction = $transactionsToCheck[$whmcsTransaction->transactionId];
            if ($whmcsTransaction->fees != $stripeTransaction->fee) {
                $currencyCode = strtoupper($stripeTransaction->fee_details[0]->currency);
                if (!array_key_exists($currencyCode, $currencyCache)) {
                    $currencyCache[$currencyCode] = $transactionFeeCurrency = \WHMCS\Billing\Currency::where("code", $currencyCode)->first();
                } else {
                    $transactionFeeCurrency = $currencyCache[$currencyCode];
                }
                if (!empty($transactionFeeCurrency)) {
                    $transactionFee = convertCurrency(\WHMCS\Module\Gateway\Stripe\ApiPayload::formatAmountInbound($stripeTransaction->fee, $transactionFeeCurrency->code), $transactionFeeCurrency->id, $params["convertto"] ?: $whmcsTransaction->client->currencyId);
                    $whmcsTransaction->fees = $transactionFee;
                    $whmcsTransaction->save();
                }
            }
        }
        \WHMCS\Scheduling\Jobs\Queue::addOrUpdate(static::JOB_NAME, static::class, "resolveIncorrectStripeTransactions", [$newOffset]);
    }

    public function setMarketConnectGeoTrustQuickSSLCertificateAsFeatured()
    {
        $digicert = new \WHMCS\MarketConnect\Promotion\Service\Symantec();
        $productGroupId = \WHMCS\Product\Product::where("servertype", "marketconnect")->whereIn("configoption1", $digicert->getProductKeys())->value("gid");
        if ($productGroupId) {
            $countOfFeatured = \WHMCS\Product\Product::where("gid", $productGroupId)->where("is_featured", 1)->count();
            if (!$countOfFeatured) {
                \WHMCS\Product\Product::where("servertype", "marketconnect")->where("gid", $productGroupId)->whereIn("configoption1", [\WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_QUICKSSLPREMIUM, \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_QUICKSSLWILDCARD, \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TRUEBIZ, \WHMCS\MarketConnect\Promotion\Service\Symantec::SSL_TRUEBIZEV])->update(["is_featured" => 1]);
            }
        }
        return $this;
    }

    protected function updateModPleskAccountsTableSchema()
    {
        if (!\WHMCS\Database\Capsule::schema()->hasTable("mod_pleskaccounts")) {
            return NULL;
        }
        try {
            $update = \WHMCS\Database\Capsule::connection()->getPdo();
            $hasUnique = $update->query("select constraint_name from information_schema.table_constraints where table_schema = database() and table_name = 'mod_pleskaccounts' and constraint_type = 'UNIQUE'")->fetchColumn();
            $update->exec("alter table `mod_pleskaccounts` drop primary key, add primary key (userid, usertype);");
            if (!empty($hasUnique)) {
                $update->exec("alter table `mod_pleskaccounts` drop key " . $hasUnique . ";");
            }
        } catch (\Throwable $t) {
            logActivity("SQL update error for mod_pleskaccounts table: " . $t->getMessage());
        }
        return $this;
    }
}
