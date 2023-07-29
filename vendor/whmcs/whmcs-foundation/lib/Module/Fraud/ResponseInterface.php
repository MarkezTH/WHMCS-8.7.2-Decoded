<?php

namespace WHMCS\Module\Fraud;

interface ResponseInterface
{
    public function __construct($jsonData, $httpCode);

    public function isSuccessful();

    public function getHttpCode();

    public function isEmpty();

    public function get($key);

    public function toArray();

    public function toJson();
}
