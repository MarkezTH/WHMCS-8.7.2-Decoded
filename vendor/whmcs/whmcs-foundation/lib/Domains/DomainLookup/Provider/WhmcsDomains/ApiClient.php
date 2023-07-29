<?php

namespace WHMCS\Domains\DomainLookup\Provider\WhmcsDomains;

class ApiClient
{
    private $url = "";
    private $apiKey = "";
    private $timeout = 3;
    private $httpClient = NULL;
    private $language = self::DEFAULT_LANGUAGE;
    private $enableSensitiveContentFilter = true;
    const DEFAULT_LANGUAGE = "english";
    const SUPPORTED_LANGUAGES = ["chinese" => "chi", "dutch" => "dut", "english" => "eng", "french" => "fre", "german" => "ger", "hindi" => "hin", "indonesian" => "ind", "italian" => "ita", "japanese" => "jpn", "korean" => "kor", "portuguese" => "por", "spanish" => "spa", "turkish" => "tur", "vietnamese" => "vie"];

    public function __construct($url = NULL, $apiKey = NULL)
    {
        if (!$url) {
            $url = $this->getDefaultUrl();
        }
        $this->setUrl($url);
        if ($apiKey) {
            $this->setApiKey($apiKey);
        }
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function getEnableSensitiveContentFilter()
    {
        return $this->enableSensitiveContentFilter;
    }

    public function setEnableSensitiveContentFilter($enableSensitiveContentFilter)
    {
        $this->enableSensitiveContentFilter = $enableSensitiveContentFilter;
        return $this;
    }

    public function setLanguage($language)
    {
        $language = strtolower($language);
        if (array_key_exists($language, self::SUPPORTED_LANGUAGES)) {
            $this->language = $language;
        }
        return $this;
    }

    private function getDefaultUrl()
    {
        return "https://domains.whmcs.net";
    }

    public function getUrl()
    {
        return $this->url;
    }

    private function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    private function getWhmcsAuthzToken()
    {
        $time = time();
        $license = \DI::make("license");
        return $license->hashMessage($time);
    }

    private function getHttpClientCustomHeaders()
    {
        if ($this->getApiKey()) {
            $custom["X-NAMESUGGESTION-APIKEY"] = $this->getApiKey();
        } else {
            $token = explode("|", $this->getWhmcsAuthzToken(), 2);
            list($custom["X-WHMCS-ID"], $custom["X-WHMCS-AUTHZ-TOKEN"]) = $token;
        }
        return $custom;
    }

    private function getHttpClient()
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = new \WHMCS\Http\Client\HttpClient(["base_uri" => $this->getUrl(), "timeout" => $this->getTimeout()]);
        }
        return $this->httpClient;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }

    protected function apiCall($endpoint, $params = [])
    {
        try {
            $client = $this->getHttpClient();
            $headers = array_merge(["Accept" => "application/json"], $this->getHttpClientCustomHeaders());
            $response = (string) $client->get($endpoint, ["query" => $params, "headers" => $headers])->getBody();
        } catch (\Exception $e) {
            throw new \WHMCS\Domains\DomainLookup\DomainLookupException($e->getMessage());
        }
        $results = json_decode($response, true);
        if (!is_array($results) || !isset($results["results"])) {
            throw new \WHMCS\Domains\DomainLookup\DomainLookupException(is_array($results) && isset($results["message"]) ? $results["message"] : "Invalid response");
        }
        return $results["results"];
    }

    protected function getSearchResult($apiDomainResult)
    {
        $domainName = strtolower($apiDomainResult["name"]);
        $availability = strtolower($apiDomainResult["availability"]);
        $domain = new \WHMCS\Domains\Domain($domainName);
        $searchResultStatus = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_UNKNOWN;
        switch ($availability) {
            case "available":
                $searchResultStatus = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED;
                break;
            case "registered":
                $searchResultStatus = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_REGISTERED;
                break;
            case "reserved":
                $searchResultStatus = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_RESERVED;
                break;
            case "unknown":
            case "invalid":
                $searchResultStatus = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_UNKNOWN;
                break;
            case "unsupported":
                $searchResultStatus = \WHMCS\Domains\DomainLookup\SearchResult::STATUS_TLD_NOT_SUPPORTED;
                break;
            default:
                $domain->setGeneralAvailability($searchResultStatus === \WHMCS\Domains\DomainLookup\SearchResult::STATUS_NOT_REGISTERED);
                $searchResult = \WHMCS\Domains\DomainLookup\SearchResult::factoryFromDomain($domain);
                $searchResult->setStatus($searchResultStatus);
                return $searchResult;
        }
    }

    public function bulkCheck($sld, $tlds)
    {
        if (!is_array($sld)) {
            $sld = [$sld];
        }
        $apiResults = $this->apiCall("bulk_check", ["names" => implode(",", $sld), "tlds" => implode(",", $tlds), "include-registered" => "true"]);
        if (empty($apiResults)) {
            $apiResults = [];
            foreach ($sld as $singleSld) {
                foreach ($tlds as $singleTld) {
                    $apiResults[] = ["name" => $singleSld . "." . trim($singleTld, "."), "availability" => "invalid"];
                }
            }
        }
        $searchResults = [];
        foreach ($apiResults as $apiResult) {
            $searchResults[] = $this->getSearchResult($apiResult);
        }
        return $searchResults;
    }

    protected function getApiLanguage()
    {
        if (!array_key_exists($this->language, self::SUPPORTED_LANGUAGES)) {
            return self::DEFAULT_LANGUAGE;
        }
        return self::SUPPORTED_LANGUAGES[$this->language];
    }

    public function suggest($name, $tlds, $maxResults = 20)
    {
        $params = ["name" => $name, "tlds" => implode(",", $tlds), "lang" => $this->getApiLanguage(), "max-results" => $maxResults, "sensitive-content-filter" => $this->enableSensitiveContentFilter ? "true" : "false"];
        $geotargetingEnabled = \WHMCS\Domains\DomainLookup\Settings::ofRegistrar("WhmcsWhois")->where("setting", "geotargetedResults")->first();
        if ($geotargetingEnabled && $geotargetingEnabled->value) {
            $userIp = \WHMCS\Utility\Environment\CurrentRequest::getIP();
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            if (filter_var($userIp, FILTER_VALIDATE_IP, $flags)) {
                $params["ip-address"] = $userIp;
            }
        }
        $apiResults = $this->apiCall("suggest", $params);
        $searchResults = [];
        foreach ($apiResults as $apiResult) {
            $searchResults[] = $this->getSearchResult($apiResult);
        }
        return $searchResults;
    }
}
