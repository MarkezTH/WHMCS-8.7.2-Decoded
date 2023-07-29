<?php

namespace WHMCS\Billing\Payment\Dispute;

class DisputeCollection extends \Illuminate\Support\Collection
{
    public static function factoryFromItems($DisputeCollection, ...$dispute)
    {
        return new static($dispute);
    }

    public static function factoryFromArray($DisputeCollection, $disputes)
    {
        $disputeObjects = array_map(function ($disputeArray) {
            return \WHMCS\Billing\Payment\Dispute::factoryFromArray($disputeArray);
        }, $disputes);
        return static::factoryFromItems(...$disputeObjects);
    }

    public function addDispute($self, $dispute)
    {
        $this->add($dispute);
        return $this;
    }
}
