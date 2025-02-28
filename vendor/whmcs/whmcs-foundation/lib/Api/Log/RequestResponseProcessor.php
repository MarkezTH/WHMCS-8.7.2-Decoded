<?php

namespace WHMCS\Api\Log;

class RequestResponseProcessor
{
    protected $variablesToMask = ["password", "Password", "secret", "password2", "hash", "accesshash", "access_hash", "cc_encryption_hash", "accesskey"];
    protected $loggableAttributes = NULL;

    public function formatRequestResponse($record)
    {
        $queryParams = [];
        $requestParams = [];
        $attributes = [];
        if (!empty($record["context"]["request"]) && $record["context"]["request"] instanceof \WHMCS\Api\ApplicationSupport\Http\ServerRequest) {
            $request = $record["context"]["request"];
            $queryParams = $request->getQueryParams();
            $requestParams = $request->getParsedBody();
            $attributes = $request->getAttributes();
            foreach ($attributes as $key => $value) {
                if (!in_array($key, $this->loggableAttributes)) {
                    unset($attributes[$key]);
                }
            }
        }
        $formattedResponse = "";
        $responseData = [];
        if (!empty($record["context"]["response"])) {
            $response = $record["context"]["response"];
            if ($response instanceof \WHMCS\Http\Message\JsonResponse || $response instanceof \WHMCS\Http\Message\XmlResponse) {
                $responseData = $response->getRawData();
            } else {
                if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                    $keyPairs = explode(";", (string) $response->getBody());
                    foreach ($keyPairs as $keyPair) {
                        $parts = explode("=", $keyPair, 2);
                        if (empty($parts[1])) {
                            $parts[1] = "";
                        }
                        $responseData[$parts[0]] = $parts[1];
                    }
                }
            }
        }
        foreach ($this->variablesToMask as $variable) {
            if (array_key_exists($variable, $requestParams)) {
                $requestParams[$variable] = str_repeat("*", strlen($requestParams[$variable]));
            }
            if (array_key_exists($variable, $queryParams)) {
                $queryParams[$variable] = str_repeat("*", strlen($queryParams[$variable]));
            }
            if (array_key_exists($variable, $attributes)) {
                $attributes[$variable] = str_repeat("*", strlen($attributes[$variable]));
            }
            if (array_key_exists($variable, $responseData)) {
                $responseData[$variable] = str_repeat("*", strlen($responseData[$variable]));
            }
        }
        $formattedRequest = jsonPrettyPrint(["GET" => $queryParams, "POST" => $requestParams, "attributes" => $attributes]);
        if ($responseData) {
            $formattedResponse = jsonPrettyPrint($responseData);
        }
        $record["extra"]["request_formatted"] = $formattedRequest;
        $record["extra"]["response_formatted"] = $formattedResponse;
        return $record;
    }

    public function processorResponseMetadata($record)
    {
        $record["extra"]["response_headers"] = "";
        $record["extra"]["response_status"] = $record["extra"]["response_headers"];
        if (!empty($record["context"]["response"]) && $record["context"]["response"] instanceof \Psr\Http\Message\ResponseInterface) {
            $response = $record["context"]["response"];
            $record["extra"]["response_status"] = $response->getStatusCode();
            $headers = [];
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $headers[] = $name . ": " . $value;
                }
            }
            $record["extra"]["response_headers"] = implode("\n", $headers);
        }
        return $record;
    }

    public function __invoke($record)
    {
        $record = $this->formatRequestResponse($record);
        $record = $this->processorResponseMetadata($record);
        return $record;
    }
}
