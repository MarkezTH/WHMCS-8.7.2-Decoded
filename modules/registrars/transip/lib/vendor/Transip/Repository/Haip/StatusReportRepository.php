<?php

namespace Transip\Api\Library\Repository\Haip;

class StatusReportRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "status-reports";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\HaipRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByHaipName($haipName)
    {
        $statusReports = [];
        $response = $this->httpClient->get($this->getResourceUrl($haipName));
        $statusReportArray = $this->getParameterFromResponse($response, "statusReports");
        foreach ($statusReportArray as $statusReport) {
            $statusReports[] = new \Transip\Api\Library\Entity\Haip\StatusReport($statusReport);
        }
        return $statusReports;
    }
}
