<?php

namespace WHMCS\Api\NG\Versions\V2\Controllers;

class ApiServiceController extends \WHMCS\Api\NG\Versions\V2\AbstractApiController implements \WHMCS\Api\NG\Versions\V2\PagedResponseInterface
{
    use \WHMCS\Api\NG\Versions\V2\PagedResponseTrait;

    public function getStatus($JsonResponse, $request)
    {
        $timezone = "UTC";
        $currencies = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate(\WHMCS\Billing\Currency::all());
        return $this->createResponse(["server_time" => \WHMCS\Carbon::now($timezone)->toRfc3339String(), "timezone" => $timezone, "currencies" => $currencies]);
    }

    public function getCurrencies($JsonResponse, $request)
    {
        $data = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($this->paginateData(\WHMCS\Billing\Currency::all(), $request));
        return $this->createResponse($data);
    }

    public function getRecurringCycles($JsonResponse, $request)
    {
        $responseData = [];
        $cycles = new \WHMCS\Billing\Cycles();
        foreach ($cycles->getRecurringCycles() as $cycle => $description) {
            $responseData[] = ["cycle" => $cycle, "description" => $description, "months" => $cycles->getNumberOfMonths($cycle)];
        }
        return $this->createResponse($responseData);
    }

    public function keepAlive($JsonResponse, $request)
    {
        return $this->createResponse();
    }
}
