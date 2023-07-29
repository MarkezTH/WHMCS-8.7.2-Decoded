<?php

namespace WHMCS\Payment\Contracts;

interface SensitiveDataInterface
{
    public function getEncryptionKey();

    public function wipeSensitiveData();

    public function getSensitiveDataAttributeName();

    public function getSensitiveProperty($property);

    public function setSensitiveProperty($property, $value);

    public function getSensitiveData();
}
