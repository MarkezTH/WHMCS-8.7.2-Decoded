<?php

namespace WHMCS\Billing\VAT;

class HMRC
{
    const PRODUCTION_ENVIRONMENT_URL = "https://api.service.hmrc.gov.uk/";

    public function getEndpoint()
    {
        return self::PRODUCTION_ENVIRONMENT_URL;
    }

    public function validate($vatNumber)
    {
        $client = $this->getHttpClient();
        $request = $client->get("/organisations/vat/check-vat-number/lookup/" . $vatNumber, [\GuzzleHttp\RequestOptions::HEADERS => ["Accept" => "application/vnd.hmrc.1.0+json"]]);
        $payload = json_decode($request->getBody()->getContents());
        if (json_last_error() !== JSON_ERROR_NONE) {
            $payload = NULL;
        }
        $request->getStatusCode();
        switch ($request->getStatusCode()) {
            case 200:
                return true;
                break;
            case 400:
                if (is_object($payload) && $payload->code === "INVALID_REQUEST" && strpos($payload->message, "Invalid targetVrn") !== false) {
                    return false;
                }
                $exceptionMessage = "Invalid Request";
                break;
            case 404:
                return false;
                break;
            case 500:
                $exceptionMessage = "HMRC Internal Server Error. Please try again later";
                break;
            default:
                $exceptionMessage = "Unknown error occurred";
                throw new \WHMCS\Exception($exceptionMessage);
        }
    }

    private function getHttpClient($HttpClient, $exceptions)
    {
        $configuration = [\GuzzleHttp\RequestOptions::HTTP_ERRORS => $exceptions, \GuzzleHttp\RequestOptions::VERIFY => true, "base_uri" => $this->getEndpoint()];
        return new \WHMCS\Http\Client\HttpClient($configuration);
    }
}
