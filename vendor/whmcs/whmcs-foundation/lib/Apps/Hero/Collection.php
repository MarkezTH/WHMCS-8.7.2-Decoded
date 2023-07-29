<?php

namespace WHMCS\Apps\Hero;

class Collection
{
    public $heros = NULL;

    public function __construct($data = NULL)
    {
        $this->heros = $data ?? (new \WHMCS\Apps\Feed())->heros();
    }

    public function get()
    {
        $country = strtolower(\WHMCS\Config\Setting::getValue("DefaultCountry"));
        $heros = array_key_exists($country, $this->heros) ? $this->heros[$country] : $this->heros["default"];
        foreach ($heros as $key => $values) {
            $heros[$key] = new Model($values);
        }
        return $heros;
    }
}
