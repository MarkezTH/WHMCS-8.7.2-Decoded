<?php

namespace WHMCS\Module\Gateway\BP;

class BitPay
{
    protected $params = [];

    public function __construct($params)
    {
        $this->params = $params;
        if (empty($params["apiKey"])) {
            $this->generatePairingCodeAndApiKey();
        }
    }

    public function getConnectionClient()
    {
        $privateKey = $this->getPrivateKey();
        $publicKey = $this->getPublicKey($privateKey);
        $client = $this->getClient();
        $adapter = $this->getCurlAdapter();
        $client->setPrivateKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setNetwork($this->getNetwork());
        $client->setAdapter($adapter);
        return $client;
    }

    protected function getPrivateKey()
    {
        $hexString = base64_decode(\WHMCS\Config\Setting::getValue("BitPayPrivateKey"));
        if (!$hexString) {
            $privateKey = Key\PrivateKey::create("private.key")->generate();
            $hexString = (string) $privateKey;
            \WHMCS\Config\Setting::setValue("BitPayPrivateKey", base64_encode($hexString));
        }
        $privateKey = new Key\PrivateKey();
        $privateKey->setHex($hexString);
        return $privateKey;
    }

    protected function getPublicKey(Key\PrivateKey $privateKey)
    {
        return $privateKey->getPublicKey();
    }

    private function generatePairingCodeAndApiKey()
    {
        \WHMCS\Module\GatewaySetting::setValue("bp", "pairingCode", "");
        try {
            $client = $this->getConnectionClient();
            $publicKey = $client->getPublicKey();
            $sin = \Bitpay\SinKey::create()->setPublicKey($publicKey)->generate();
            $token = $client->createToken(["facade" => "merchant", "label" => "WHMCS - " . \WHMCS\Config\Setting::getValue("CompanyName"), "id" => (string) $sin]);
            $this->params["pairingCode"] = $token->getPairingCode();
            $this->params["apiKey"] = $token->getToken();
            \WHMCS\Module\GatewaySetting::setValue("bp", "apiKey", $token->getToken());
            \WHMCS\Module\GatewaySetting::setValue("bp", "pairingCode", $token->getPairingCode());
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function getClient()
    {
        return new Client();
    }

    protected function getCurlAdapter()
    {
        return new \Bitpay\Client\Adapter\CurlAdapter();
    }

    protected function getNetwork()
    {
        $network = "Bitpay\\Network\\Livenet";
        if ($this->params["testMode"]) {
            $network = "Bitpay\\Network\\Testnet";
        }
        return new $network();
    }
}
