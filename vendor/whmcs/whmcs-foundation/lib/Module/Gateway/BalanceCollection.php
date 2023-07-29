<?php

namespace WHMCS\Module\Gateway;

class BalanceCollection extends \Illuminate\Support\Collection
{
    public static function factoryFromItems($self, ...$balanceArray)
    {
        return new static($balanceArray);
    }

    public function addBalance($self, $balance)
    {
        $this->add($balance);
        return $this;
    }

    public static function factoryFromArray($self, $balances)
    {
        $balanceObjects = array_map(function ($balanceData) {
            return Balance::factoryFromArray($balanceData);
        }, $balances);
        return static::factoryFromItems(...$balanceObjects);
    }
}
