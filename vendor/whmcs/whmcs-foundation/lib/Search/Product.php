<?php

namespace WHMCS\Search;

class Product implements SearchInterface
{
    public function search($searchTerm)
    {
        if (is_array($searchTerm)) {
            $productId = !empty($searchTerm["productId"]) ? $searchTerm["productId"] : 0;
            $searchTerm = !empty($searchTerm["searchTerm"]) ? $searchTerm["searchTerm"] : 0;
        } else {
            $productId = 0;
        }
        $data = [];
        if (!is_null($searchTerm)) {
            $data = $this->fuzzySearch($searchTerm, $productId);
        }
        return $data;
    }

    protected function fuzzySearch($searchTerm, int $productId)
    {
        $searchResults = [];
        $matches = \WHMCS\Product\Product::query()->where("id", "!=", $productId);
        if ($searchTerm) {
            $matches->where(function (\Illuminate\Database\Eloquent\Builder $query) use($searchTerm) {
                $query->where("name", "LIKE", "%" . $searchTerm . "%");
                if (is_numeric($searchTerm)) {
                    $query->orWhere("id", "=", (int) $searchTerm)->orWhere("id", "LIKE", "%" . (int) $searchTerm . "%");
                }
            });
        } else {
            $matches->limit(30);
        }
        foreach ($matches->get() as $product) {
            $display = $product->name;
            $searchResults[] = ["id" => $product->id, "name" => $display, "group" => $product->productGroup->name, "groupid" => $product->productGroup->id, "order" => $product->productGroup->displayOrder + 1];
        }
        if (count($searchResults) < 1 && $searchTerm) {
            $searchResults[] = ["id" => -1 * abs(crc32($searchTerm)), "name" => \AdminLang::trans("global.norecordsfound"), "noResults" => \AdminLang::trans("global.searchTerm", [":searchTerm" => $searchTerm])];
        }
        return $searchResults;
    }
}
