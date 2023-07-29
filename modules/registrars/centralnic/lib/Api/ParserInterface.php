<?php

namespace WHMCS\Module\Registrar\CentralNic\Api;

interface ParserInterface
{
    public function buildPayload($params);

    public function parseResponse($response);

    public function getResponseDataValue($key, $data);

    public function getResponseCode($response);

    public function getResponseDescription($response);

    public function getResponseData($response);
}
