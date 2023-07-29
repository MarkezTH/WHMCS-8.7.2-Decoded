<?php

namespace WHMCS\Cron\Task;

class SslReissues extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1850;
    protected $defaultFrequency = 1440;
    protected $defaultDescription = "SSL Certificate Reissues";
    protected $defaultName = "SSL Certificate Reissues";
    protected $systemName = "SslReissues";
    protected $outputs = ["requested" => ["defaultValue" => 0, "identifier" => "requested", "name" => "Requested SSL Certificate Reissues"], "manual" => ["defaultValue" => 0, "identifier" => "manual", "name" => "Manual SSL Certificate Reissues"]];
    protected $icon = "far fa-lock-alt";
    protected $isBooleanStatus = false;
    protected $successCountIdentifier = "success";
    protected $successKeyword = "Reissued";
    private $activeServiceStatuses = NULL;

    public function __invoke()
    {
        $today = \WHMCS\Carbon::today()->startOfDay();
        $certExpiryCheck = $today->copy()->addDays(30)->endOfDay();
        \WHMCS\Service\Ssl::whereNotNull("certificate_expiry_date")->where("certificate_expiry_date", "<", $today)->update(["status" => \WHMCS\Service\Ssl::STATUS_EXPIRED]);
        $toCheck = \WHMCS\Service\Ssl::whereNotNull("certificate_expiry_date")->whereIn("status", [\WHMCS\Service\Ssl::STATUS_COMPLETED, \WHMCS\Service\Ssl::STATUS_REISSUED])->whereDate("certificate_expiry_date", "<=", $certExpiryCheck->toDateTimeString())->get();
        foreach ($toCheck as $sslOrder) {
            try {
                $owningModel = $sslOrder->getOwningService();
            } catch (\Throwable $t) {
                $sslOrder->stateCancelled();
                $sslOrder->save();
            }
            if (!in_array($owningModel->status, $this->activeServiceStatuses)) {
                $sslOrder->stateCancelled();
                $sslOrder->save();
            } else {
                $sslDetails = $sslOrder->configurationData;
                if (!isset($sslDetails["reissueAttempts"])) {
                    $sslDetails["reissueAttempts"] = 0;
                }
                try {
                    $serverInterface = $sslOrder->getOwnProvisioningModule();
                    if (!$serverInterface->functionExists("reissue_certificate")) {
                        $sslOrder->status = \WHMCS\Service\Ssl::STATUS_REISSUE_PENDING;
                        $sslOrder->configurationData = $sslDetails;
                        $sslOrder->save();
                        $sslOrder->sendEmail(\WHMCS\Service\Ssl::EMAIL_REISSUE_DUE, ["noSupport" => true]);
                    }
                } catch (\Throwable $t) {
                    $sslOrder->status = \WHMCS\Service\Ssl::STATUS_REISSUE_FAILED;
                    $sslOrder->save();
                }
                $sslOrder->save();
                $reissueResponse = $serverInterface->call("reissue_certificate");
                $sslOrder->refresh();
                if (!empty($reissueResponse["error"])) {
                    $sslOrder->reissueAttemptFailure($reissueResponse["error"]);
                } else {
                    $sslOrder->resetReissueAttempts();
                }
                $sslOrder->save();
            }
        }
    }
}
