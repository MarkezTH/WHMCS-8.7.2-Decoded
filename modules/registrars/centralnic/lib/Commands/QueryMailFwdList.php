<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

class QueryMailFwdList extends AbstractCommand
{
    protected $command = "QueryMailFwdList";

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api, $domain)
    {
        $this->setParam("dnszone", $domain);
        parent::__construct($api);
    }

    public static function getList($response)
    {
        $list = [];
        if ($response->getCode() != 200 || !$response->getData()) {
            return $list;
        }
        for ($i = 0; $i < $response->getDataValue("total"); $i++) {
            $from = explode("@", $response->getData()["from"][$i] ?? "");
            $list[$i] = ["prefix" => $from[0], "forwardto" => $response->getData()["to"][$i]];
        }
        return $list;
    }

    public static function diff($domain, $currentList, $newList)
    {
        $adding = [];
        $deleting = [];
        foreach ($newList as $prefix => $forwardTo) {
            if (!(empty($prefix) || empty($forwardTo))) {
                $from = $prefix . "@" . $domain;
                $to = $forwardTo;
                if (isset($currentList[$prefix])) {
                    if ($currentList[$prefix] != $to) {
                        $deleting[$from] = $currentList[$prefix];
                        $adding[$from] = $to;
                    }
                } else {
                    $adding[$from] = $to;
                }
            }
        }
        foreach ($currentList as $prefix => $forwardTo) {
            $from = $prefix . "@" . $domain;
            if (!isset($newList[$prefix])) {
                $deleting[$from] = $forwardTo;
            }
        }
        return ["deleting" => $deleting, "adding" => $adding];
    }
}
