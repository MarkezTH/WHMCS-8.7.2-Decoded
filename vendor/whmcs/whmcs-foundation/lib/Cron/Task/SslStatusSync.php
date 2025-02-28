<?php

namespace WHMCS\Cron\Task;

class SslStatusSync extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1650;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "SSL Status Sync";
    protected $defaultName = "SSL Sync";
    protected $systemName = "SslSync";
    protected $outputs = ["success" => ["defaultValue" => 0, "identifier" => "success", "name" => "SSL Status Sync Success"]];
    protected $icon = "far fa-lock-alt";
    protected $isBooleanStatus = false;
    protected $successCountIdentifier = "success";
    protected $successKeyword = "Synced";

    public function __invoke()
    {
        return $this->getNewDomains()->syncSslStatus();
    }

    private function applyCollationIfCompatible($columnName)
    {
        $db = \DI::make("db");
        $columnName = preg_replace("/[^a-z0-9\\_\\.]+/i", "", $columnName);
        if (strlen($columnName) === 0) {
            throw new \WHMCS\Exception("Invalid column name");
        }
        if (strcasecmp($db->getCharacterSet(), "utf8") === 0) {
            return \WHMCS\Database\Capsule::raw("concat(\"\" COLLATE utf8_unicode_ci, " . $columnName . ")");
        }
        return $columnName;
    }

    protected function getNewDomains()
    {
        $services = \WHMCS\Database\Capsule::table("tblhosting")->where("domain", "!=", "")->leftJoin("tblsslstatus", $this->applyCollationIfCompatible("tblhosting.domain"), "=", "tblsslstatus.domain_name")->whereNull("tblsslstatus.id")->limit(100)->pluck("userid", "domain")->all();
        foreach ($services as $domainName => $userId) {
            if (filter_var($domainName, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                \WHMCS\Domain\Ssl\Status::factory($userId, $domainName)->syncAndSave();
            }
        }
        $domains = \WHMCS\Database\Capsule::table("tbldomains")->where("domain", "!=", "")->leftJoin("tblsslstatus", $this->applyCollationIfCompatible("tbldomains.domain"), "=", "tblsslstatus.domain_name")->whereNull("tblsslstatus.id")->limit(100)->pluck("userid", "domain")->all();
        foreach ($domains as $domainName => $userId) {
            \WHMCS\Domain\Ssl\Status::factory($userId, $domainName)->syncAndSave();
        }
        return $this;
    }

    protected function syncSslStatus()
    {
        $sslsToSync = \WHMCS\Domain\Ssl\Status::whereNull("last_synced_date")->orWhere("last_synced_date", "<=", \WHMCS\Carbon::now()->subDay())->orderBy("last_synced_date", "DESC")->take(100)->get();
        $domainsSyncedCount = 0;
        foreach ($sslsToSync as $ssl) {
            if (filter_var($ssl->domainName, FILTER_VALIDATE_DOMAIN)) {
                try {
                    $ssl->syncAndSave();
                    $domainsSyncedCount++;
                } catch (\Throwable $e) {
                }
            }
        }
        $this->output("success")->write((int) $domainsSyncedCount);
        return $this;
    }
}
