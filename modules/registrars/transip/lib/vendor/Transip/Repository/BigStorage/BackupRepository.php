<?php

namespace Transip\Api\Library\Repository\BigStorage;

class BackupRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "backups";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\BigStorageRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByBigStorageName($bigStorageName)
    {
        $backups = [];
        $response = $this->httpClient->get($this->getResourceUrl($bigStorageName));
        $backupsArray = $this->getParameterFromResponse($response, "backups");
        foreach ($backupsArray as $backupArray) {
            $backups[] = new \Transip\Api\Library\Entity\BigStorage\Backup($backupArray);
        }
        return $backups;
    }

    public function revertBackup($bigStorageName, int $backupId = "", $destinationBigStorageName)
    {
        $this->httpClient->patch($this->getResourceUrl($bigStorageName, $backupId), ["action" => "revert", "destinationBigStorageName" => $destinationBigStorageName]);
    }
}
