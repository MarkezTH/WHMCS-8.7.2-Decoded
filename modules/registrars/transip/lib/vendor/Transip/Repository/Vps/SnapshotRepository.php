<?php

namespace Transip\Api\Library\Repository\Vps;

class SnapshotRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "snapshots";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($vpsName)
    {
        $snapshots = [];
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $snapshotsArray = $this->getParameterFromResponse($response, "snapshots");
        foreach ($snapshotsArray as $snapshotArray) {
            $snapshots[] = new \Transip\Api\Library\Entity\Vps\Snapshot($snapshotArray);
        }
        return $snapshots;
    }

    public function getByVpsNameSnapshotName($Snapshot, $vpsName, $snapshotName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName, $snapshotName));
        $snapshot = $this->getParameterFromResponse($response, "snapshot");
        return new \Transip\Api\Library\Entity\Vps\Snapshot($snapshot);
    }

    public function createSnapshot($vpsName, $description = true, $shouldStartVps)
    {
        $url = $this->getResourceUrl($vpsName);
        $parameters = ["description" => $description, "shouldStartVps" => $shouldStartVps];
        $this->httpClient->post($url, $parameters);
    }

    public function revertSnapshot($vpsName, $snapshotName = "", $destinationVpsName)
    {
        $url = $this->getResourceUrl($vpsName, $snapshotName);
        $this->httpClient->patch($url, ["destinationVpsName" => $destinationVpsName]);
    }

    public function deleteSnapshot($vpsName, $snapshotName)
    {
        $url = $this->getResourceUrl($vpsName, $snapshotName);
        $this->httpClient->delete($url);
    }
}
