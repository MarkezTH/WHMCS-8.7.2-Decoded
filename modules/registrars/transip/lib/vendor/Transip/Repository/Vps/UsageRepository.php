<?php

namespace Transip\Api\Library\Repository\Vps;

class UsageRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const TYPE_CPU = "cpu";
    const TYPE_DISK = "disk";
    const TYPE_NETWORK = "network";
    const RESOURCE_NAME = "usage";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($vpsName = [], $types = 0, int $dateTimeStart = 0, int $dateTimeEnd)
    {
        $parameters = [];
        if (0 < count($types)) {
            $parameters["types"] = implode(",", $types);
        }
        if (0 < $dateTimeStart) {
            $parameters["dateTimeStart"] = $dateTimeStart;
        }
        if (0 < $dateTimeEnd) {
            $parameters["dateTimeEnd"] = $dateTimeEnd;
        }
        $usages = [];
        $response = $this->httpClient->get($this->getResourceUrl($vpsName), $parameters);
        $usageTypesArray = $this->getParameterFromResponse($response, "usage");
        foreach ($usageTypesArray as $usageType => $usageArray) {
            switch ($usageType) {
                case self::TYPE_CPU:
                    foreach ($usageArray as $usage) {
                        $usages[$usageType][] = new \Transip\Api\Library\Entity\Vps\UsageDataCpu($usage);
                    }
                    break;
                case self::TYPE_DISK:
                    foreach ($usageArray as $usage) {
                        $usages[$usageType][] = new \Transip\Api\Library\Entity\Vps\UsageDataDisk($usage);
                    }
                    break;
                case self::TYPE_NETWORK:
                    foreach ($usageArray as $usage) {
                        $usages[$usageType][] = new \Transip\Api\Library\Entity\Vps\UsageDataNetwork($usage);
                    }
                    break;
            }
        }
        return $usages;
    }
}
