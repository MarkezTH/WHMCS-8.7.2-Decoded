<?php

namespace WHMCS\Search;

class User implements SearchInterface
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

    protected function fuzzySearch($searchTerm = 0, int $clientId)
    {
        $searchResults = [];
        $matches = \WHMCS\User\User::where("id", "!=", 0);
        if ($clientId) {
            $matches->whereDoesntHave("clients", function (\Illuminate\Database\Eloquent\Builder $query) use($clientId) {
                $query->where("tblclients.id", "=", $clientId);
            });
        }
        if ($searchTerm) {
            $matches->where(function (\Illuminate\Database\Eloquent\Builder $query) use($searchTerm) {
                $query->where(\WHMCS\Database\Capsule::raw("CONCAT(first_name, ' ', last_name)"), "LIKE", "%" . $searchTerm . "%")->orWhere("email", "LIKE", "%" . $searchTerm . "%");
                if (is_numeric($searchTerm)) {
                    $query->orWhere("id", "=", (int) $searchTerm)->orWhere("id", "LIKE", "%" . (int) $searchTerm . "%");
                }
            });
        } else {
            $matches->limit(30);
        }
        foreach ($matches->get() as $user) {
            $searchResults[] = ["id" => $user->id, "name" => \WHMCS\Input\Sanitize::decode($user->fullName), "email" => \WHMCS\Input\Sanitize::decode($user->email)];
        }
        if (count($searchResults) < 1 && $searchTerm) {
            if (filter_var($searchTerm, FILTER_VALIDATE_EMAIL)) {
                $searchResults[] = ["id" => "invite-" . $searchTerm, "name" => \AdminLang::trans("user.createNewUser", [":email" => $searchTerm]), "email" => $searchTerm];
            } else {
                $searchResults[] = ["id" => -1 * abs(crc32($searchTerm)), "name" => \AdminLang::trans("global.norecordsfound"), "email" => \AdminLang::trans("global.searchTerm", [":searchTerm" => $searchTerm])];
            }
        }
        return $searchResults;
    }
}
