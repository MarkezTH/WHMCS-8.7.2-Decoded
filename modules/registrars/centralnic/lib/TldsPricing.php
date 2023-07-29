<?php

namespace WHMCS\Module\Registrar\CentralNic;

class TldsPricing
{
    protected $api = NULL;
    protected $list = NULL;

    public function __construct(Api\ApiInterface $api)
    {
        $this->api = $api;
    }

    public function load()
    {
        if (empty($this->list)) {
            $this->list = collect();
            try {
                $response = (new Commands\QueryZoneList($this->api))->execute();
                foreach ($response->getData()["zone"] ?? [] as $id => $zone) {
                    if ("YEAR" == $response->getData()["periodtype"][$id]) {
                        if (!empty($response->getData()["active"][$id])) {
                            $tlds = $this->extractTlds($zone, $response->getData()["3rds"][$id]);
                            foreach ($tlds as $index => $tld) {
                                $this->list->add(new TldPricing($tld, $zone, (double) $response->getData()["setup"][$id] ?? 0, (double) $response->getData()["annual"][$id] ?? 0, (double) $response->getData()["transfer"][$id] ?? 0, (double) $response->getData()["trade"][$id] ?? 0, (double) $response->getData()["restore"][$id] ?? 0, (double) $response->getData()["application"][$id] ?? 0, $response->getData()["currency"][$id] ?? "", (int) $response->getData()["domaincount"][$id] ?? 0));
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception("Unable to retrieve zone from remote provider", $e->getCode(), $e);
            }
        }
        return $this;
    }

    public function getAll()
    {
        return $this->list;
    }

    public function findPricing($zone)
    {
        return $this->getAll()->first(function ($item) use($zone) {
            return $item->tld() == $zone;
        });
    }

    protected function extractTlds($zone, $tldList)
    {
        $tlds = [];
        if (strpos($tldList, " ") !== false) {
            if (strpos($tldList, ",") !== false) {
                $tlds = explode(", ", $tldList);
            } else {
                if (preg_match("/[a-z\\.]/", $zone)) {
                    $tlds[] = $zone;
                }
            }
        } else {
            $tlds[] = $tldList;
        }
        return $tlds;
    }
}
