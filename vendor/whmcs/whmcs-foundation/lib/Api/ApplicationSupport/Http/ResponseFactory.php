<?php

namespace WHMCS\Api\ApplicationSupport\Http;

class ResponseFactory
{
    const RESPONSE_FORMAT_NVP = "nvp";
    const RESPONSE_FORMAT_XML = "xml";
    const RESPONSE_FORMAT_JSON = "json";
    const RESPONSE_FORMAT_DEFAULT_HIGHLY_STRUCTURED = self::RESPONSE_FORMAT_XML;
    const RESPONSE_FORMAT_DEFAULT_BASIC_STRUCTURED = self::RESPONSE_FORMAT_NVP;

    public static function factory(ServerRequest $request, $responseData, $statusCode = \Symfony\Component\HttpFoundation\Response::HTTP_OK)
    {
        $responseType = $request->getResponseFormat();
        if ($responseType == static::RESPONSE_FORMAT_JSON) {
            try {
                $response = new \WHMCS\Http\Message\JsonResponse($responseData, $statusCode);
            } catch (\Exception $e) {
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $jsonError = json_last_error_msg();
                    $responseData = ["result" => "error", "message" => "Error generating JSON encoded response: " . $jsonError];
                } else {
                    $responseData = ["result" => "error", "message" => $e->getMessage()];
                }
                $response = new \WHMCS\Http\Message\JsonResponse($responseData, $statusCode);
            }
        } else {
            if ($responseType == static::RESPONSE_FORMAT_XML) {
                $responseData = array_merge(["action" => $request->getAction()], $responseData);
                $response = new \WHMCS\Http\Message\XmlResponse($responseData, $statusCode);
            } else {
                $responseStr = [];
                foreach ($responseData as $k => $v) {
                    $responseStr[] = $k . "=" . $v;
                }
                $response = new \Laminas\Diactoros\Response\TextResponse(implode(";", $responseStr), $statusCode);
            }
        }
        return $response;
    }

    public static function getSupportedResponseTypes()
    {
        return [static::RESPONSE_FORMAT_JSON, static::RESPONSE_FORMAT_XML, static::RESPONSE_FORMAT_NVP];
    }

    public static function isValidResponseType($type)
    {
        return in_array($type, static::getSupportedResponseTypes());
    }

    public static function isTypeHighlyStructured($type)
    {
        $highlyStructuredTypes = [static::RESPONSE_FORMAT_JSON, static::RESPONSE_FORMAT_XML];
        if (in_array($type, $highlyStructuredTypes)) {
            return true;
        }
        return false;
    }
}
