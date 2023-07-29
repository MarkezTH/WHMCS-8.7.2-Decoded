<?php

namespace Transip\Api\Library\Repository\Vps;

class TCPMonitorRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "tcp-monitors";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\VpsRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByVpsName($vpsName)
    {
        $response = $this->httpClient->get($this->getResourceUrl($vpsName));
        $tcpMonitorsArray = $this->getParameterFromResponse($response, "tcpMonitors");
        $tcpMonitors = [];
        foreach ($tcpMonitorsArray as $tcpMonitor) {
            $tcpMonitors[] = new \Transip\Api\Library\Entity\Vps\TCPMonitor($tcpMonitor);
        }
        return $tcpMonitors;
    }

    public function create($vpsName, \Transip\Api\Library\Entity\Vps\TCPMonitor $monitor)
    {
        $this->httpClient->post($this->getResourceUrl($vpsName), ["tcpMonitor" => $monitor]);
    }

    public function update($vpsName, \Transip\Api\Library\Entity\Vps\TCPMonitor $monitor)
    {
        $this->httpClient->put($this->getResourceUrl($vpsName, $monitor->getIpAddress()), ["tcpMonitor" => $monitor]);
    }

    public function delete($vpsName, $ipAddress)
    {
        $this->httpClient->delete($this->getResourceUrl($vpsName, $ipAddress), []);
    }
}
