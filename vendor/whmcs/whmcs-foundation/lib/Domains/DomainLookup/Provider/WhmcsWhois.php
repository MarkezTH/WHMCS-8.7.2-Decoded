<?php

namespace WHMCS\Domains\DomainLookup\Provider;

class WhmcsWhois extends BasicWhois
{
    public function getSettings()
    {
        if (is_null($tlds)) {
            $tlds = \WHMCS\Database\Capsule::table("tbldomainpricing")->orderBy("order", "ASC")->pluck("extension", "extension")->all();
        }
        return ["suggestTlds" => ["FriendlyName" => \AdminLang::trans("general.suggesttldsinfo"), "Type" => "dropdown", "Description" => "<div class=\"text-muted text-center small\">" . \AdminLang::trans("global.ctrlclickmultiselection") . "</div>", "Default" => "", "Size" => 10, "Options" => $tlds, "Multiple" => true]];
    }
}
