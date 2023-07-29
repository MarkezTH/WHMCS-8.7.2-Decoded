<?php

namespace WHMCS\UsageBilling\Invoice\Item;

abstract class AbstractUsageItem implements \WHMCS\UsageBilling\Contracts\Invoice\UsageItemInterface
{
    private $serviceMetric = NULL;
    private $calculations = [];

    public function __construct(\WHMCS\UsageBilling\Service\ServiceMetric $serviceMetric)
    {
        $serviceMetric = $this->useHistoricalUsage($serviceMetric);
        $this->setServiceMetric($serviceMetric);
        $usageItem = $serviceMetric->usageItem();
        $pricingSchema = $usageItem->pricingSchema;
        $calculations = $this->reduceIncludedCalculations($this->getUsageCalculations($serviceMetric, $pricingSchema));
        $this->setCalculations($calculations);
    }

    private function reduceIncludedCalculations($calculations)
    {
        $includedTotal = NULL;
        $reducedCalculations = [];
        foreach ($calculations as $calculation) {
            if ($calculation instanceof \WHMCS\UsageBilling\Invoice\Calculation\Included) {
                $includedTotal = ($includedTotal ?: 0) + $calculation->consumed();
            } else {
                $reducedCalculations[] = $calculation;
            }
        }
        if (!is_null($includedTotal)) {
            array_unshift($reducedCalculations, new \WHMCS\UsageBilling\Invoice\Calculation\Included($includedTotal, NULL, NULL, true));
        }
        return $reducedCalculations;
    }

    public function getInvoiceItem()
    {
        if (!$this->getCalculations()) {
            return NULL;
        }
        $attributes = $this->getDefaultServiceAttributes();
        $price = $this->calculatePrice();
        if ($price instanceof \WHMCS\View\Formatter\Price) {
            $price = $price->toNumeric();
        }
        if (!$price) {
            $price = 0;
        }
        $attributes["amount"] = $price;
        $attributes["description"] = $this->getLineItemDescription();
        return new \WHMCS\Billing\Invoice\Item($attributes);
    }

    protected function useHistoricalUsage(\WHMCS\UsageBilling\Service\ServiceMetric $serviceMetric)
    {
        $historicalUsage = $serviceMetric->historicUsage();
        if ($historicalUsage) {
            $serviceMetric = $serviceMetric->withUsage($historicalUsage)->withHistoricUsage(NULL);
        }
        return $serviceMetric;
    }

    public function getServiceMetric()
    {
        return $this->serviceMetric;
    }

    public function setServiceMetric($serviceMetric)
    {
        $this->serviceMetric = $serviceMetric;
        return $this;
    }

    public function getCalculations()
    {
        return $this->calculations;
    }

    public function setCalculations($calculations)
    {
        $this->calculations = $calculations;
        return $this;
    }

    public function getServiceName()
    {
        $service = $this->getServiceMetric()->service();
        return $service->product->name . " - " . $service->domain;
    }

    public function getModule()
    {
        return $this->getServiceMetric()->service()->serverModel->getModuleInterface();
    }

    protected function getDefaultServiceAttributes()
    {
        $serviceMetric = $this->getServiceMetric();
        $service = $serviceMetric->service();
        if (\WHMCS\Config\Setting::getValue("ContinuousInvoiceGeneration")) {
            $dateField = "nextinvoicedate";
        } else {
            $dateField = "nextduedate";
        }
        return ["type" => \WHMCS\Billing\InvoiceItemInterface::TYPE_BILLABLE_USAGE, "relid" => (int) $serviceMetric->tenantStatId(), "userid" => $service->clientId, "paymentmethod" => $service->paymentGateway, "duedate" => $service->{$dateField}, "taxed" => false, "invoiceid" => 0];
    }
    public abstract function getUsageCalculations(\WHMCS\UsageBilling\Service\ServiceMetric $serviceMetric, \WHMCS\UsageBilling\Contracts\Pricing\PricingSchemaInterface $pricingSchema);

    protected function getLineItemDescription()
    {
        $serviceMetric = $this->getServiceMetric();
        $metricName = $serviceMetric->displayName();
        $units = $serviceMetric->units();
        $usage = $serviceMetric->usage();
        $type = $serviceMetric->type();
        $usageDateRange = "";
        if ($type != \WHMCS\UsageBilling\Service\ServiceMetric::TYPE_SNAPSHOT) {
            $now = \WHMCS\Carbon::now();
            $usageRecord = $usage->collectedAt();
            $usageDateRange = "";
            if ($type == \WHMCS\UsageBilling\Service\ServiceMetric::TYPE_PERIOD_MONTH) {
                $usageDateRange = $usageRecord->startOfMonth()->toAdminDateFormat();
                if ($usageRecord->month === $now->month) {
                    $usageDateRange .= " - " . $now->toAdminDateFormat();
                } else {
                    $usageDateRange .= " - " . $usageRecord->endOfMonth()->toAdminDateFormat();
                }
            }
            if ($type == \WHMCS\UsageBilling\Service\ServiceMetric::TYPE_PERIOD_DAY) {
                $usageDateRange = $usageRecord->startOfDay()->toAdminDateFormat();
            }
        }
        if ($usageDateRange) {
            $usageDateRange = " (" . $usageDateRange . ")";
        }
        $serviceName = sprintf("%s\n%s %s %s", $this->getServiceName(), $units->decorate($usage->value()), $metricName, $usageDateRange);
        $calculations = $this->getCalculations();
        $descriptions = [];
        foreach ($calculations as $calculation) {
            $descriptions[] = $this->getSingleLineDescription($calculation, $units);
        }
        array_unshift($descriptions, $serviceName);
        $description = implode("\n", $descriptions);
        return $description;
    }

    private function getSingleLineDescription(\WHMCS\UsageBilling\Contracts\Invoice\UsageCalculationInterface $calculation, \WHMCS\UsageBilling\Contracts\Metrics\UnitInterface $units)
    {
        $consumed = $calculation->consumed();
        if (valueIsZero($consumed)) {
            return "";
        }
        $consumed = $units->formatForType($units->roundForType($consumed));
        if ($calculation->isIncluded()) {
            return \Lang::trans("metrics.invoiceitem.included", [":included" => $consumed, ":metricname" => $units->perUnitName($consumed)]);
        }
        $pricing = $calculation->price();
        $pricePerUnitPrice = new \WHMCS\View\Formatter\Price($pricing->monthly, $pricing->currency->toArray());
        $description = \Lang::trans("metrics.invoiceitem.perunit", [":consumed" => $consumed, ":metricname" => $units->perUnitName($consumed), ":price" => $pricePerUnitPrice->toFull(), ":perUnitName" => $units->perUnitName(1)]);
        return $description;
    }

    protected function calculatePrice()
    {
        $pricingAmounts = $this->getCalculations();
        $allPricing = [];
        foreach ($pricingAmounts as $pricingDetails) {
            if (!$pricingDetails->isIncluded()) {
                $pricing = $pricingDetails->price();
                $consumed = $pricingDetails->consumed();
                $pricePerUnit = $pricing->monthly;
                if ($consumed < 0) {
                    $consumed = 0;
                }
                $factoredPrice = $consumed * $pricePerUnit;
                $price = new \WHMCS\View\Formatter\Price($factoredPrice, $pricing->currency->toArray());
                $priceFormatted = $price->toNumeric();
                if ($priceFormatted < $factoredPrice && valueIsZero($priceFormatted) && !valueIsZero($consumed)) {
                    $allPricing[] = new \WHMCS\View\Formatter\Price("0.01", $pricing->currency->toArray());
                } else {
                    $allPricing[] = $price;
                }
            }
        }
        $aggregatedPrice = 0;
        foreach ($allPricing as $sumMe) {
            $aggregatedPrice += (double) $sumMe->toNumeric();
        }
        if ($aggregatedPrice < 0) {
            return NULL;
        }
        return new \WHMCS\View\Formatter\Price($aggregatedPrice, $allPricing[0]->getCurrency());
    }
}
