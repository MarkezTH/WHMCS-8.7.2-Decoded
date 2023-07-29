<?php

namespace WHMCS\View\Template;

class CompatCache
{
    private $compatUtil = NULL;
    private static $cacheSig = "";
    private static $cacheData = [];
    const DELIMITER_SOURCE = "\n";
    const DELIMITER_VALUE = "=";
    const SETTING_CACHE_KEY = "TemplateCompatCache";

    public function __construct(CompatUtil $compatUtil = NULL, $reset = false)
    {
        if (is_null($compatUtil)) {
            $compatUtil = new CompatUtil($this);
        }
        $this->compatUtil = $compatUtil;
        if ($reset) {
            static::$cacheSig = "";
            static::$cacheData = [];
        } else {
            if (static::$cacheSig == "" || static::$cacheData) {
                $this->hydrateFromStoredCache();
            }
        }
        $this->rebuildInvalidCache();
    }

    public function getCompatibility($dependent, $provider)
    {
        $storage = $this->getCache();
        if (!empty($storage) && !empty($storage[$dependent][$provider])) {
            return $storage[$dependent][$provider];
        }
        return NULL;
    }

    public function getCache()
    {
        return static::$cacheData;
    }

    public function getSignature()
    {
        return static::$cacheSig;
    }

    private function getCompatUtil($CompatUtil)
    {
        return $this->compatUtil;
    }

    private function rebuildInvalidCache()
    {
        $currentSig = $this->getFilesCacheSig();
        if ($this->getSignature() === $currentSig && $this->getCache()) {
            return NULL;
        }
        $this->storeCache($currentSig, $this->buildCompatCache());
    }

    private function getFilesCacheSig()
    {
        return sha1($this->getFilesFingerprint());
    }

    private function getFilesFingerprint()
    {
        $items = [];
        foreach ($this->dataSources() as $templates) {
            foreach ($templates as $tmpl) {
                $items[] = $tmpl->getName() . static::DELIMITER_VALUE . $tmpl->getConfig()->checksum();
            }
            $items[] = "";
        }
        return implode(static::DELIMITER_SOURCE, $items);
    }

    private function hydrateFromStoredCache()
    {
        $data = (string) \WHMCS\TransientData::getInstance()->retrieve(static::SETTING_CACHE_KEY);
        $data = json_decode($data, true);
        if (is_array($data) && !empty($data["sig"]) && !empty($data["cache"])) {
            static::$cacheSig = (string) $data["sig"];
            static::$cacheData = $data["cache"];
        }
    }

    private function storeCache($signature, $data)
    {
        static::$cacheSig = $signature;
        static::$cacheData = $data;
        \WHMCS\TransientData::getInstance()->store(static::SETTING_CACHE_KEY, json_encode(["sig" => $signature, "cache" => $data]), 31557600);
    }

    private function dataSources()
    {
        return [OrderForm::all(), Theme::all()];
    }

    private function buildCompatCache()
    {
        $data = [];
        $compatUtil = $this->getCompatUtil();
        list($dependents, $providers) = $this->dataSources();
        foreach ($dependents as $dependent) {
            $depName = $dependent->getName();
            foreach ($providers as $provider) {
                $proName = $provider->getName();
                try {
                    $compatUtil->assertProviderCompatibility($dependent, $provider);
                    $data[$depName][$proName] = $compatUtil->detailsCompatible();
                } catch (\WHMCS\Exception\View\TemplateCompatUnknown $e) {
                    $data[$depName][$proName] = ["status" => CompatUtil::COMPAT_UNKNOWN, "reason" => $e->getMessage()];
                } catch (\Throwable $e) {
                    $data[$depName][$proName] = ["status" => CompatUtil::COMPAT_NO, "reason" => $e->getMessage()];
                }
            }
        }
        return $data;
    }
}
