<?php

namespace WHMCS\Search;

class Service implements SearchInterface
{
    public function search($searchTerm)
    {
        if (is_array($searchTerm)) {
            $clientId = !empty($searchTerm["clientId"]) ? $searchTerm["clientId"] : 0;
            $searchTerm = !empty($searchTerm["searchTerm"]) ? $searchTerm["searchTerm"] : "";
        } else {
            $clientId = 0;
        }
        $data = [];
        if (!is_null($searchTerm)) {
            $data = $this->fuzzySearch($searchTerm, $clientId);
        }
        return $data;
    }

    protected function fuzzySearch($searchTerm, int $clientId)
    {
        $searchResults = [];
        $matches = \WHMCS\Service\Service::with("product")->where("userid", $clientId);
        if ($searchTerm) {
            $matches->where(function (\Illuminate\Database\Eloquent\Builder $query) use($searchTerm) {
                $query->where("domain", "LIKE", $searchTerm . "%")->orWhereHas("product", function (\Illuminate\Database\Eloquent\Builder $query) use($searchTerm) {
                    $query->where("name", "like", $searchTerm . "%");
                });
                if (is_numeric($searchTerm)) {
                    $query->orWhere("id", "=", (int) $searchTerm)->orWhere("id", "LIKE", "%" . (int) $searchTerm . "%");
                }
            });
        } else {
            $matches->limit(30);
        }
        foreach ($matches->get() as $service) {
            $display = $service->product->name;
            if ($service->domain) {
                $display .= " - " . $service->domain;
            }
            $searchResults[] = ["id" => $service->id, "name" => $display, "color" => $service->getHexColorFromStatus()];
        }
        if (count($searchResults) < 1 && $searchTerm) {
            $searchResults[] = ["id" => -1 * abs(crc32($searchTerm)), "name" => \AdminLang::trans("global.norecordsfound"), "noResults" => \AdminLang::trans("global.searchTerm", [":searchTerm" => $searchTerm])];
        }
        return $searchResults;
    }
}
