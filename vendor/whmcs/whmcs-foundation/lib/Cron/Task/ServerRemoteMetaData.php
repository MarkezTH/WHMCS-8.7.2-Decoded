<?php

namespace WHMCS\Cron\Task;

class ServerRemoteMetaData extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1910;
    protected $defaultFrequency = 60;
    protected $skipDailyCron = true;
    protected $defaultDescription = "Auto Update Server Meta Data";
    protected $defaultName = "Update Server Meta Data";
    protected $systemName = "ServerRemoteMetaData";

    public function __invoke()
    {
        $servers = \WHMCS\Product\Server::enabled()->get();
        foreach ($servers as $server) {
            $moduleInterface = new \WHMCS\Module\Server();
            $moduleInterface->load($server->type);
            $serverMetaData = $moduleInterface->call("GetRemoteMetaData", $moduleInterface->getServerParams($server));
            if ($serverMetaData !== \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                if (!array_key_exists("error", $serverMetaData)) {
                    $remoteData = \WHMCS\Product\Server\Remote::firstOrNew(["server_id" => $server->id]);
                    $metaData = $remoteData->metaData;
                    $metaData = array_merge($metaData, $serverMetaData);
                    $remoteData->metaData = $metaData;
                    $remoteData->save();
                }
            }
        }
        return $this;
    }
}
