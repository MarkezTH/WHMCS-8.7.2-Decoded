<?php

namespace WHMCS\Environment\Ioncube\Inspector\Iterator;

class Loggable extends AbstractInspectorIterator
{
    const CONFIG_SCAN_METADATA_KEY = "PhpCompatLastScanTime";

    public static function fromDatabase()
    {
        return (new static())->exchangeWithLog();
    }

    protected function exchangeWithLog()
    {
        $items = [];
        $logged = \WHMCS\Environment\Ioncube\Log\File::all();
        foreach ($logged as $file) {
            $items[$file->getFileFingerprint()] = $file;
        }
        $this->exchangeArray($items);
        return $this;
    }

    protected function newMetaData()
    {
        _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F656E7669726F6E6D656E742F696F6E637562652F696E73706563746F722F6974657261746F722F6C6F676761626C652E7068703078376630316332633666326161_();
    }

    public function getMetaData()
    {
        $metadata = $this->newMetaData();
        $metadata->decode(\WHMCS\Config\Setting::getValue(self::CONFIG_SCAN_METADATA_KEY) ?? "");
        return $metadata;
    }

    public function saveMetaData($metadata)
    {
        if (!is_object($metadata)) {
            throw new \InvalidArgumentException("Argument #1 it not an object");
        }
        \WHMCS\Config\Setting::setValue(self::CONFIG_SCAN_METADATA_KEY, $metadata->encode());
        return $metadata;
    }

    public function resetMetadata()
    {
        \WHMCS\Config\Setting::setValue(self::CONFIG_SCAN_METADATA_KEY, "");
        return $this->newMetaData();
    }

    public function purgeAll()
    {
        \WHMCS\Environment\Ioncube\Log\File::query()->delete();
        return $this;
    }

    public function save()
    {
        (new \WHMCS\Environment\Ioncube\Log\File())->replaceAll($this->getArrayCopy());
        return $this;
    }
}
class _obfuscated_5C636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F456E7669726F6E6D656E742F496F6E637562652F496E73706563746F722F4974657261746F722F4C6F676761626C652E7068703078376630316332633666326161_
{
    public $timestamp = NULL;
    public $loader = NULL;
    public $php = NULL;
    public $app = NULL;

    public function encode()
    {
        return json_encode($this);
    }

    public function decode($encoded)
    {
        if (strlen($encoded) == 0) {
            return false;
        }
        $struct = json_decode($encoded);
        if (!is_object($struct)) {
            return false;
        }
        foreach (get_object_vars($this) as $property => $value) {
            if (isset($struct->{$property})) {
                $this->{$property} = $struct->{$property};
            }
        }
        return true;
    }

    public function getDateTime($Carbon)
    {
        if (is_null($this->timestamp)) {
            return NULL;
        }
        return \WHMCS\Carbon::createFromTimestamp($this->timestamp);
    }

    public function getAppVersion()
    {
        return new \WHMCS\Version\SemanticVersion($this->app ?? "0.0.0");
    }

    public function getLoaderVersion()
    {
        return new \WHMCS\Version\SemanticVersion($this->loader ?? "0.0.0");
    }

    public function hasScanned()
    {
        return !empty($this->timestamp);
    }

    public function needsScan($appVersion, int $phpVersionId, \WHMCS\Version\SemanticVersion $ioncubeLoader)
    {
        return !$this->hasScanned() || $this->isAppVersionStale($appVersion) || $this->isPhpVersionStale($phpVersionId) || $this->isLoaderVersionStale($ioncubeLoader);
    }

    public function update($self, $appVersion, int $phpVersionId, \WHMCS\Version\SemanticVersion $ioncubeLoader)
    {
        $this->timestamp = \WHMCS\Carbon::now()->getTimestamp();
        $this->app = $appVersion->getCanonical();
        $this->php = $phpVersionId;
        $this->loader = $ioncubeLoader->getCanonical();
        return $this;
    }

    public function isAppVersionStale($appVersion)
    {
        return !\WHMCS\Version\SemanticVersion::compare($appVersion, $this->getAppVersion(), "==");
    }

    public function isLoaderVersionStale($loaderVersion)
    {
        return !\WHMCS\Version\SemanticVersion::compare($loaderVersion, $this->getLoaderVersion(), "==");
    }

    public function isPhpVersionStale($phpVersionId)
    {
        return $phpVersionId !== $this->php;
    }
}
