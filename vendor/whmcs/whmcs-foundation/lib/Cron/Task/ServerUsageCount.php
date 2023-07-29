<?php

namespace WHMCS\Cron\Task;

class ServerUsageCount extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1900;
    protected $defaultFrequency = 60;
    protected $skipDailyCron = true;
    protected $defaultDescription = "Auto Update Server Usage Count";
    protected $defaultName = "Update Server Usage";
    protected $systemName = "ServerUsageCount";

    public function __invoke()
    {
        $servers = \WHMCS\Product\Server::enabled()->get();
        foreach ($servers as $server) {
            $remoteData = \WHMCS\Product\Server\Remote::firstOrNew(["server_id" => $server->id]);
            $moduleInterface = new \WHMCS\Module\Server();
            $moduleInterface->load($server->type);
            $countType = "GetUserCount";
            if (array_key_exists("max_domains", $remoteData->metaData) && 0 < $remoteData->metaData["max_domains"]) {
                $countType = "GetDomainCount";
            }
            $counts = $moduleInterface->call($countType, $moduleInterface->getServerParams($server));
            if ($counts !== \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                if (!array_key_exists("error", $counts)) {
                    $remoteData->numAccounts = $counts["totalAccounts"];
                    $metaData = $remoteData->metaData;
                    $metaData["ownedAccounts"] = $counts["ownedAccounts"];
                    $remoteData->metaData = $metaData;
                    $remoteData->save();
                }
            }
        }
        return $this;
    }
}
