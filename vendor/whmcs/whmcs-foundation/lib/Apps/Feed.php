<?php

namespace WHMCS\Apps;

class Feed
{
    protected $cacheTimeout = 604800;
    protected $feed = [];

    public function __construct()
    {
        $this->feed = $this->loadFeed();
        return $this;
    }

    protected function loadFeed()
    {
        $feedData = $this->loadFromCache();
        if (is_null($feedData)) {
            $feedData = $this->fetchFromRemote();
            $feedData = json_decode($feedData, true);
            if (!is_null($feedData)) {
                \WHMCS\TransientData::getInstance()->chunkedStore("apps.feed", json_encode($feedData), $this->cacheTimeout);
            }
        }
        if (is_null($feedData)) {
            throw new \WHMCS\Exception\Http\ConnectionError("Unable to retrieve Apps data feed.");
        }
        return $this->filterVersionedItems($feedData, \App::getVersion());
    }

    protected function loadFromCache()
    {
        $data = \WHMCS\TransientData::getInstance()->retrieveChunkedItem("apps.feed");
        if (!empty($data)) {
            return json_decode($data, true);
        }
        return NULL;
    }

    protected function fetchFromRemote()
    {
        return curlCall("https://appsfeed.whmcs.com/feed.json", "");
    }

    protected function filterVersionedItems($feed, $installedVersion)
    {
        $return = [];
        foreach ($feed as $key => $value) {
            if (is_array($value)) {
                $isValidVersion = true;
                if (array_key_exists("min_version", $value)) {
                    $minVersion = $value["min_version"];
                    $isValidVersion = \WHMCS\Version\SemanticVersion::compare($installedVersion, new \WHMCS\Version\SemanticVersion($minVersion), ">") || \WHMCS\Version\SemanticVersion::compare($installedVersion, new \WHMCS\Version\SemanticVersion($minVersion), "==");
                }
                if ($isValidVersion && array_key_exists("max_version", $value)) {
                    $maxVersion = $value["max_version"];
                    $isValidVersion = \WHMCS\Version\SemanticVersion::compare($installedVersion, new \WHMCS\Version\SemanticVersion($maxVersion), "<") || \WHMCS\Version\SemanticVersion::compare($installedVersion, new \WHMCS\Version\SemanticVersion($maxVersion), "==");
                }
                if ($isValidVersion) {
                    $value = $this->filterVersionedItems($value, $installedVersion);
                }
            }
            $return[$key] = $value;
        }
        return $return;
    }

    public function heros()
    {
        return $this->feed["heros"];
    }

    public function categories()
    {
        return $this->feed["categories"];
    }

    public function apps()
    {
        return $this->feed["apps"];
    }

    public function additionalApps()
    {
        return $this->feed["additional_apps"];
    }
}
