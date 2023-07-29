<?php

namespace WHMCS\Service\Traits;

trait ProvisioningTraits
{
    protected $moduleInterface = NULL;

    public function moduleInterface($Server)
    {
        if (is_null($this->moduleInterface)) {
            $this->moduleInterface = \WHMCS\Module\Server::factoryFromModel($this);
        }
        return $this->moduleInterface;
    }

    public function getMxRecords()
    {
        return $this->moduleInterface()->call("GetMxRecords", ["mxDomain" => $this->domain]);
    }

    public function addMxRecords($self, $add)
    {
        $this->moduleInterface()->call("AddMxRecords", $add);
        return $this;
    }

    public function removeMxRecords($self, $remove = NULL, \WHMCS\Service\Properties $serviceProperties)
    {
        if ($remove) {
            if (is_null($serviceProperties)) {
                $serviceProperties = $this->serviceProperties;
            }
            $this->moduleInterface()->call("DeleteMxRecords", ["mxDomain" => $this->domain, "mxRecords" => $remove]);
            $dataString = "";
            foreach ($remove as $datum) {
                $dataString .= $datum["priority"] . ":" . $datum["mx"] . "\r\n";
            }
            $serviceProperties->save(["Original MX Records" => ["type" => "textarea", "value" => $dataString]]);
        }
        return $this;
    }

    public function getSPFRecord()
    {
        return $this->moduleInterface()->call("GetSPFRecord", ["spfDomain" => $this->domain]);
    }

    public function setSPFRecord($self, $record)
    {
        $this->moduleInterface()->call("SetSPFRecord", ["spfDomain" => $this->domain, "spfRecord" => $record]);
        return $this;
    }
}
