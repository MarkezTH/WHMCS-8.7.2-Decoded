<?php

namespace Transip\Api\Library\Repository\Vps;

class BackupRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "backups";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($vpsName)
    {
        $backups = [];
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $backupsArray = $this->getParameterFromResponse($response, "backups");
        foreach ($backupsArray as $backupArray) {
            $backups[] = new \Transip\Api\Library\Entity\Vps\Backup($backupArray);
        }
        return $backups;
    }

    public function revertBackup($vpsName, int $backupId)
    {
        $this->httpClient->patch($this->getResourceUrl($vpsName, $backupId), ["action" => "revert"]);
    }

    public function convertBackupToSnapshot($vpsName, int $backupId = "", $snapshotDescription)
    {
        $parameters["description"] = $snapshotDescription;
        $parameters["action"] = "convert";
        $this->httpClient->patch($this->getResourceUrl($vpsName, $backupId), $parameters);
    }
}
