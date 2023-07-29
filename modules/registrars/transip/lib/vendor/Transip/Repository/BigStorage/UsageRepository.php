<?php

namespace Transip\Api\Library\Repository\BigStorage;

class UsageRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "usage";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\BigStorageRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getUsageStatistics($bigStorageName = 0, int $dateTimeStart = 0, int $dateTimeEnd)
    {
        $usages = [];
        $parameters = [];
        if (0 < $dateTimeStart) {
            $parameters["dateTimeStart"] = $dateTimeStart;
        }
        if (0 < $dateTimeEnd) {
            $parameters["dateTimeEnd"] = $dateTimeEnd;
        }
        $response = $this->httpClient->get($this->getResourceUrl($bigStorageName), $parameters);
        $usageStatistics = $this->getParameterFromResponse($response, "usage");
        foreach ($usageStatistics as $usage) {
            $usages[] = new \Transip\Api\Library\Entity\Vps\UsageDataDisk($usage);
        }
        return $usages;
    }
}
