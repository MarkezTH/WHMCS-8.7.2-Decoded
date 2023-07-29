<?php

namespace WHMCS\Admin\Server;

class ServerController
{
    public function refreshRemoteData(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $server = \WHMCS\Product\Server::findOrFail($request->get("id"));
            $remoteData = \WHMCS\Product\Server\Remote::firstOrNew(["server_id" => $server->id]);
            $metaData = $remoteData->metaData;
            $serverInterface = $server->getModuleInterface();
            $serverDetails = $serverInterface->getServerParams($server);
            $remoteMetaData = $serverInterface->call("GetRemoteMetaData", $serverDetails);
            if ($remoteMetaData !== \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                if (array_key_exists("error", $remoteMetaData) && $remoteMetaData["error"]) {
                    throw new \WHMCS\Exception\Module\NotServicable($remoteMetaData["error"]);
                }
                $metaData = array_merge($metaData, $remoteMetaData);
            }
            $countType = "GetUserCount";
            $usageString = \AdminLang::trans("configservers.accounts");
            if (array_key_exists("max_domains", $metaData) && 0 < $metaData["max_domains"]) {
                $countType = "GetDomainCount";
                $usageString = \AdminLang::trans("configservers.domains");
            }
            $serverCounts = $serverInterface->call($countType, $serverDetails);
            if ($serverCounts !== \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                if (array_key_exists("error", $serverCounts) && $serverCounts["error"]) {
                    throw new \WHMCS\Exception\Module\NotServicable($serverCounts["error"]);
                }
                $remoteData->numAccounts = $serverCounts["totalAccounts"];
                $metaData["ownedAccounts"] = $serverCounts["ownedAccounts"];
            }
            $remoteData->metaData = $metaData;
            $remoteData->save();
            $remoteMetaDataOutput = $serverInterface->call("RenderRemoteMetaData", ["remoteData" => $remoteData]);
            if ($remoteMetaDataOutput == \WHMCS\Module\Server::FUNCTIONDOESNTEXIST) {
                $remoteMetaDataOutput = "";
            } else {
                $remoteMetaDataOutput .= "<br>" . \AdminLang::trans("global.lastUpdated") . ": Just now";
            }
            $serverUsageCount = $usageString . ": " . $remoteData->numAccounts;
            if (array_key_exists("service_count", $remoteData->metaData)) {
                $serverUsageCount .= "<br><small>" . \AdminLang::trans("configservers.services") . ": " . $remoteData->metaData["service_count"] . "</small>";
            }
            return new \WHMCS\Http\Message\JsonResponse(["success" => true, "metaData" => $remoteMetaDataOutput, "numAccounts" => $serverUsageCount]);
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["success" => false, "error" => ["title" => \AdminLang::trans("global.error"), "message" => $e->getMessage()]]);
        }
    }
}
