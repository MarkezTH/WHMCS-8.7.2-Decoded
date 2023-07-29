<?php

namespace WHMCS\Utility;

class Country
{
    protected $countries = [];
    protected $countriesPath = NULL;

    public function __construct($countriesPath = "")
    {
        if (!empty($countriesPath)) {
            $this->countriesPath = $countriesPath;
        }
        $this->load();
    }

    public function countryOverrideFilepath()
    {
        return $this->countriesPath . "countries.json";
    }

    public function hasCountryOverride()
    {
        return file_exists($this->countryOverrideFilepath());
    }

    protected function load()
    {
        $path = $this->countriesPath . "dist.countries.json";
        $overridePath = $this->countryOverrideFilepath();
        $countries = array_merge($this->loadFile($path), $this->loadFile($overridePath));
        $statesPath = $this->countriesPath . "dist.states.json";
        $statesOverridePath = $this->countriesPath . "states.json";
        $states = array_merge($this->loadFile($statesPath), $this->loadFile($statesOverridePath));
        foreach ($countries as $code => $data) {
            if (!$data) {
                unset($countries[$code]);
            } else {
                $stateList = [];
                if (array_key_exists($code, $states)) {
                    $stateList = [];
                    foreach ($states[$code] as $stateData) {
                        if (!(array_key_exists("remove", $stateData) && $stateData["remove"] === true)) {
                            $stateList[] = $stateData;
                        }
                    }
                }
                $countries[$code]["states"] = $stateList;
            }
        }
        $this->countries = $countries;
    }

    protected function loadFile($path)
    {
        $countries = [];
        if (file_exists($path)) {
            $countries = file_get_contents($path);
            $countries = json_decode($countries, true);
            if (!is_array($countries)) {
                logActivity("Unable to load Countries File: " . $path);
                $countries = [];
            }
        }
        return $countries;
    }

    public function getCountries()
    {
        return $this->countries;
    }

    public function getCountryNameArray()
    {
        $countries = [];
        foreach ($this->getCountries() as $code => $data) {
            $countries[$code] = $data["name"];
        }
        return $countries;
    }

    public function getCountryNamesOnly()
    {
        $countries = [];
        foreach ($this->getCountries() as $data) {
            $countries[$data["name"]] = $data["name"];
        }
        return $countries;
    }

    public function getCallingCode($countryCode)
    {
        $countries = $this->getCountries();
        if (array_key_exists($countryCode, $countries)) {
            return $countries[$countryCode]["callingCode"];
        }
        return 0;
    }

    public function getStates($countryCode)
    {
        $countries = $this->getCountries();
        $data = [];
        if (array_key_exists($countryCode, $countries)) {
            $data = $countries[$countryCode]["states"];
        }
        return collect($data);
    }

    public function getStateCodeMapping($countryCode)
    {
        $states = $this->getStates($countryCode);
        $stateCodeMap = [];
        foreach ($states as $state) {
            $stateCodeMap[$state["code"]] = $state["name"];
        }
        return $stateCodeMap;
    }

    public function getStateNameFromCode($countryCode, $stateCode)
    {
        $states = $this->getStateCodeMapping($countryCode);
        if (array_key_exists($stateCode, $states)) {
            return $states[$stateCode];
        }
        return $stateCode;
    }

    public function getName($countryCode)
    {
        $countries = $this->getCountries();
        if (array_key_exists($countryCode, $countries)) {
            return $countries[$countryCode]["name"];
        }
        return $countryCode;
    }

    public function isValidCountryCode($countryCode)
    {
        return isset($this->countries[$countryCode]);
    }

    public function isValidCountryName($countryName)
    {
        return in_array($countryName, $this->getCountryNamesOnly());
    }
}
