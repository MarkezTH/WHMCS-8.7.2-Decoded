<?php

namespace WHMCS\Api\NG\Log;

class ApiNgRequestResponseProcessor extends \WHMCS\Api\Log\RequestResponseProcessor
{
    public function formatRequestResponse($record)
    {
        $requestData = $formattedRequest = $formattedResponse = "";
        $method = "";
        if (!empty($record["context"]["request"]) && $record["context"]["request"] instanceof \WHMCS\Http\Message\ServerRequest) {
            $request = $record["context"]["request"];
            $requestData = $request->getBody()->getContents() ?? "";
            $method = $request->getMethod();
        }
        $responseData = [];
        if (!empty($record["context"]["response"])) {
            $response = $record["context"]["response"];
            $responseData = $response->getPayload();
        }
        if (!empty($requestData)) {
            $formattedRequest = json_encode($this->maskValues($requestData), JSON_PRETTY_PRINT);
        }
        if (!empty($responseData)) {
            $formattedResponse = json_encode($this->maskValues($responseData), JSON_PRETTY_PRINT);
        }
        $record["extra"]["request_formatted"] = $formattedRequest;
        $record["extra"]["response_formatted"] = $formattedResponse;
        $record["method"] = $method;
        return $record;
    }

    public function processorRequestMetadata($record)
    {
        $record["extra"]["request_headers"] = "";
        if (!empty($record["context"]["request"]) && $record["context"]["request"] instanceof \WHMCS\Http\Message\ServerRequest) {
            $request = $record["context"]["request"];
            $headers = [];
            foreach ($request->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $headers[] = $name . ": " . $value;
                }
            }
            $record["extra"]["request_headers"] = implode("\n", $headers);
        }
        return $record;
    }

    public function __invoke($record)
    {
        $record = $this->processorRequestMetadata($record);
        return parent::__invoke($record);
    }

    protected function maskValues($data)
    {
        $result = is_array($data) ? $data : coalesce(json_decode($data, true), []);
        return json_encode($this->maskArrayValues($result));
    }

    protected function maskArrayValues($data)
    {
        array_walk_recursive($data, function (&$value, $key) {
            if (in_array($key, $this->variablesToMask)) {
                $value = str_repeat("*", strlen($value));
            }
        });
        return $data;
    }
}
