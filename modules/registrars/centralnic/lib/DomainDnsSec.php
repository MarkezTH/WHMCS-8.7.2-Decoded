<?php

namespace WHMCS\Module\Registrar\CentralNic;

class DomainDnsSec extends Domain
{
    protected $dsKey = NULL;
    protected $dsData = NULL;
    protected $api = NULL;
    const DNSSEC_ALGORITHM_NUMBERS = ["8" => "RSA/SHA256", "10" => "RSA/SHA512", "12" => "GOST R 34.10-2001", "13" => "ECDSA/SHA-256", "14" => "ECDSA/SHA-384", "15" => "Ed25519", "16" => "Ed448"];
    const DNSSEC_FLAGS = ["256" => "Zone Signing Key", "257" => "Key Signing Key"];
    const DNSSEC_DIGEST_ALGORITHM = ["2" => "SHA-256", "3" => "GOST R 34.11-94", "4" => "SHA-384"];
    const DNSSEC_PROTOCOLS = ["3" => "DNSSEC"];

    public function __construct(Api\ApiInterface $api, $name)
    {
        $this->api = $api;
        parent::__construct($name);
        $this->initializeData();
    }

    public function load()
    {
        $this->initializeData();
        try {
            $domainInfo = (new Commands\StatusDomain($this->api, $this->getName()))->execute();
            foreach ($domainInfo->getData()["dnssecdsdata"] ?? [] as $dsData) {
                $split = preg_split("/\\s+/", $dsData);
                if ($split !== false) {
                    list($keyTag, $alg, $digestType, $digest) = $split;
                    $this->dsData->add(new DsData($keyTag, $alg, $digestType, $digest));
                }
            }
            foreach ($domainInfo->getData()["dnssec"] ?? [] as $dsKey) {
                $split = preg_split("/\\s+/", $dsKey);
                if ($split !== false) {
                    list($flag, $protocol, $alg, $pubKey) = $split;
                    $this->dsKey->add(new KeyData($flag, $protocol, $alg, $pubKey));
                }
            }
        } catch (\Exception $e) {
            throw new \Exception("Unable to retrieve DNSSEC data", $e->getCode(), $e);
        }
        return $this;
    }

    public function getDsData()
    {
        return $this->dsData ?? collect();
    }

    public function getKeyData()
    {
        return $this->dsKey ?? collect();
    }

    public function addDnsSecRecord($record)
    {
        $this->dsKey->add($record);
        return $this;
    }

    public function save()
    {
        $modify = new Commands\ModifyDomain($this->api, $this->getName());
        $this->getKeyData()->each(function ($key, $i) use($modify) {
            $modify->setParam("DNSSEC" . $i, sprintf("%d %d %d %s", $key->getFlag(), $key->getProtocol(), $key->getAlg(), $key->getPubKey()));
        });
        $modify->execute();
    }

    public function deleteAll()
    {
        (new Commands\ModifyDomain($this->api, $this->getName()))->setParam("DNSSECDELALL", 1)->execute();
    }

    protected function initializeData()
    {
        $this->dsKey = collect();
        $this->dsData = collect();
    }
}
