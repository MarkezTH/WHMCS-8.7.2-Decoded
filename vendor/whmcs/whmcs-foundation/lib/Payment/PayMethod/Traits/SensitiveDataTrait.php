<?php

namespace WHMCS\Payment\PayMethod\Traits;

trait SensitiveDataTrait
{
    private $sensitiveData = NULL;

    public function wipeSensitiveData()
    {
        $this->sensitiveData = [];
    }

    public function getSensitiveProperty($property)
    {
        $decryptedValue = NULL;
        $sensitiveData = $this->getRawSensitiveData();
        if (isset($sensitiveData[$property])) {
            $decryptedValue = $sensitiveData[$property];
        }
        return $decryptedValue;
    }

    public function setSensitiveProperty($property, $value)
    {
        if (is_null($this->sensitiveData)) {
            $this->getRawSensitiveData();
        }
        $this->sensitiveData[$property] = $value;
        return $this;
    }

    public function unsetSensitiveProperty($property)
    {
        if (is_array($this->sensitiveData) && array_key_exists($property, $this->sensitiveData)) {
            unset($this->sensitiveData[$property]);
        }
        return $this;
    }

    public function getRawSensitiveData()
    {
        if (is_null($this->sensitiveData)) {
            $name = $this->getSensitiveDataAttributeName();
            $data = $this->{$name};
            if (!empty($data)) {
                $encryptionKey = $this->getEncryptionKey();
                $decrypted = $this->aesDecryptValue($data, $encryptionKey);
                if ($decrypted) {
                    $decrypted = json_decode($decrypted, true);
                    if (is_array($decrypted)) {
                        $this->sensitiveData = $decrypted;
                    }
                }
            }
        }
        return $this->sensitiveData;
    }

    public function getSensitiveData()
    {
        $data = "";
        $sensitiveData = $this->getRawSensitiveData();
        if (!empty($sensitiveData)) {
            $data = $this->aesEncryptValue(json_encode($sensitiveData), $this->getEncryptionKey());
        }
        return $data;
    }
}
