<?php

namespace WHMCS\Environment;

class Environment
{
    public static function toArray()
    {
        $report = [];
        if (!Php::isCli()) {
            $report["webServer"] = ["family" => WebServer::getServerFamily(), "version" => WebServer::getServerVersion(), "hasModRewrite" => WebServer::hasModRewrite()];
        }
        $ioncubeLoaderVersion = Ioncube\Loader\LocalLoader::getVersion();
        $report = array_merge($report, ["controlPanel" => WebServer::getControlPanelInfo(), "install" => ["isTesting" => (bool) is_dir(ROOTDIR . DIRECTORY_SEPARATOR . "install2"), "hasRootHtaccess" => WebServer::hasRootHtaccess(), "hasAdminHtaccess" => WebServer::hasAdminHtaccess(), "autoUpdatePinChannel" => \WHMCS\Config\Setting::getValue("WHMCSUpdatePinVersion")], "php" => ["version" => Php::getVersion(), "extensions" => Php::getLoadedExtensions(), "memoryLimit" => Php::getPhpMemoryLimitInBytes(), "ioncubeLoaderVersion" => $ioncubeLoaderVersion ? $ioncubeLoaderVersion->getVersion() : NULL], "db" => DbEngine::getInfo(), "curl" => Curl::getInfo()]);
        return $report;
    }
}
