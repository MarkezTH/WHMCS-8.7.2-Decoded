<?php

namespace WHMCS\Domains\Traits;

trait DomainTraits
{
    public function getServiceDomain()
    {
        if ($this instanceof \WHMCS\Service\Addon) {
            $domain = $this->serviceProperties->get("Domain");
            if (!$domain) {
                $this->loadMissing("service");
                if (!is_null($this->service)) {
                    $domain = $this->service->getRawAttribute("domain");
                }
            }
        } else {
            $domain = $this->getRawAttribute("domain");
        }
        return $domain;
    }

    public function getDomainAttribute()
    {
        return $this->getServiceDomain();
    }

    public function getDomainPunycodeAttribute()
    {
        try {
            $domain = $this->getServiceDomain();
            if ($domain) {
                $wildCard = false;
                if (substr($domain, 0, 2) === "*.") {
                    $wildCard = true;
                    $domain = substr($domain, 2);
                }
                $domain = \WHMCS\Domains\Idna::toPunycode($domain);
                if ($wildCard) {
                    $domain = "*." . $domain;
                }
            }
            return $domain;
        } catch (\Exception $e) {
            return NULL;
        }
    }

    public function getIsIdnDomainAttribute()
    {
        return $this->getServiceDomain() !== $this->domainPunycode;
    }

    public function getNextDueDateFormattedAttribute()
    {
        $nextDueDate = $this->getRawAttribute("nextduedate");
        if ($nextDueDate instanceof \WHMCS\Carbon) {
            return $nextDueDate->toClientDateFormat();
        }
        $nextDueDate = fromMySQLDate($nextDueDate, false, true);
        return $nextDueDate;
    }

    public function getNextDueDatePropertiesAttribute()
    {
        $nextDueDate = $this->getRawAttribute("nextduedate");
        $propertyArray = [];
        if (!$nextDueDate instanceof \WHMCS\Carbon) {
            try {
                if ($nextDueDate == "0000-00-00") {
                    throw new \Carbon\Exceptions\InvalidDateException("Next Due Date", $nextDueDate);
                }
                $nextDueDate = \WHMCS\Carbon::parse($nextDueDate);
            } catch (\Exception $e) {
                $propertyArray["isPast"] = false;
                $propertyArray["isFuture"] = false;
                $propertyArray["daysTillExpiry"] = false;
                return $propertyArray;
            }
        }
        $propertyArray["isPast"] = $nextDueDate->isPast();
        $propertyArray["isFuture"] = $nextDueDate->subDay()->isFuture();
        $propertyArray["daysTillExpiry"] = $nextDueDate->diffInDays();
        return $propertyArray;
    }

    public function getRegistrationDateFormattedAttribute()
    {
        $registrationDate = $this->getRawAttribute("regdate");
        if ($registrationDate instanceof \WHMCS\Carbon) {
            return $registrationDate->toClientDateFormat();
        }
        $registrationDate = fromMySQLDate($registrationDate, false, true);
        return $registrationDate;
    }
}
