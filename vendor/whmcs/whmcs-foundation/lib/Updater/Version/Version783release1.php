<?php

namespace WHMCS\Updater\Version;

class Version783release1 extends IncrementalVersion
{
    protected $updateActions = ["convertCentovaCastHostnames"];

    protected function convertCentovaCastHostnames()
    {
        $servers = \WHMCS\Product\Server::ofModule("centovacast")->get();
        foreach ($servers as $server) {
            $url = $server->hostname;
            if (preg_match("#^https?://#", $url)) {
                $parts = parse_url($url);
                $server->hostname = $parts["host"];
                $server->port = $parts["port"];
                $server->accessHash = $parts["path"];
                $server->secure = strtolower($parts["scheme"]) === "https" ? "on" : "";
                $server->save();
            }
        }
    }
}
