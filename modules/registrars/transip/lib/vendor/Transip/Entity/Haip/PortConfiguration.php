<?php

namespace Transip\Api\Library\Entity\Haip;

class PortConfiguration extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $id = NULL;
    protected $name = NULL;
    protected $sourcePort = NULL;
    protected $targetPort = NULL;
    protected $mode = NULL;
    protected $endpointSslMode = NULL;

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getSourcePort()
    {
        return $this->sourcePort;
    }

    public function getTargetPort()
    {
        return $this->targetPort;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function getEndpointSslMode()
    {
        return $this->endpointSslMode;
    }

    public function setName($PortConfiguration, $name)
    {
        $this->name = $name;
        return $this;
    }

    public function setSourcePort($PortConfiguration, $sourcePort)
    {
        $this->sourcePort = $sourcePort;
        return $this;
    }

    public function setTargetPort($PortConfiguration, $targetPort)
    {
        $this->targetPort = $targetPort;
        return $this;
    }

    public function setMode($PortConfiguration, $mode)
    {
        $this->mode = $mode;
        return $this;
    }

    public function setEndpointSslMode($PortConfiguration, $endpointSslMode)
    {
        $this->endpointSslMode = $endpointSslMode;
        return $this;
    }
}
