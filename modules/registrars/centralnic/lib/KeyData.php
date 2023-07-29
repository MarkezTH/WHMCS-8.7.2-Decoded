<?php

namespace WHMCS\Module\Registrar\CentralNic;

class KeyData
{
    protected $flag = 0;
    protected $protocol = 0;
    protected $alg = 0;
    protected $pubKey = "";

    public function __construct(int $flag, int $protocol, int $alg, $pubKey)
    {
        $this->flag = $flag;
        $this->protocol = $protocol;
        $this->alg = $alg;
        $this->pubKey = $pubKey;
    }

    public function getFlag()
    {
        return $this->flag;
    }

    public function getProtocol()
    {
        return $this->protocol;
    }

    public function getAlg()
    {
        return $this->alg;
    }

    public function getPubKey()
    {
        return $this->pubKey;
    }
}
