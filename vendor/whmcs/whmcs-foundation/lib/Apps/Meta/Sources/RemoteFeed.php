<?php

namespace WHMCS\Apps\Meta\Sources;

class RemoteFeed
{
    public function getAdditionalApps()
    {
        $apps = [];
        foreach ((new \WHMCS\Apps\Feed())->additionalApps() as $slug => $data) {
            $apps[$slug] = $this->parseJson($data);
        }
        return $apps;
    }

    public function getAppByModuleName($AbstractVersion, $moduleType, $moduleName)
    {
        $slug = $moduleType . "." . $moduleName;
        $apps = (new \WHMCS\Apps\Feed())->apps();
        $additionalApps = (new \WHMCS\Apps\Feed())->additionalApps();
        $metaData = NULL;
        if (isset($apps[$slug])) {
            $metaData = $apps[$slug];
        } else {
            if (isset($additionalApps[$slug])) {
                $metaData = $additionalApps[$slug];
            }
        }
        if (!is_null($metaData)) {
            $metaData = $this->parseJson($metaData);
        }
        return $metaData;
    }

    protected function getSchemaMajorVersion($metaData)
    {
        if (isset($metaData["schema"])) {
            $versionParts = explode(".", $metaData["schema"]);
            return $versionParts[0];
        }
        throw new \WHMCS\Exception("Schema not defined.");
    }

    public function parseJson($metaData)
    {
        $majorVersion = $this->getSchemaMajorVersion($metaData);
        $schemaClass = "\\WHMCS\\Apps\\Meta\\Schema\\Version" . (int) $majorVersion . "\\Remote";
        if (class_exists($schemaClass)) {
            return new $schemaClass($metaData);
        }
        throw new \WHMCS\Exception("Invalid schema version.");
    }
}
