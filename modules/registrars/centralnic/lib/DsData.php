<?php

namespace WHMCS\Module\Registrar\CentralNic;

class DsData
{
    protected $keyTag = 0;
    protected $alg = 0;
    protected $digestType = 0;
    protected $digest = "";

    public function __construct(int $keyTag, int $alg, int $digestType, $digest)
    {
        $this->keyTag = $keyTag;
        $this->alg = $alg;
        $this->digestType = $digestType;
        $this->digest = $digest;
    }

    public function getKeyTag()
    {
        return $this->keyTag;
    }

    public function getAlg()
    {
        return $this->alg;
    }

    public function getDigestType()
    {
        return $this->digestType;
    }

    public function getDigest()
    {
        return $this->digest;
    }
}
