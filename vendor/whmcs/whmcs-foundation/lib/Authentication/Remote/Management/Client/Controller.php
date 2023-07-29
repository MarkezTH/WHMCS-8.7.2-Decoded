<?php

namespace WHMCS\Authentication\Remote\Management\Client;

class Controller
{
    public function getLinks(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $responseData["data"] = [];
            $remoteAuth = \DI::make("remoteAuth");
            if (count($remoteAuth->getEnabledProviders()) === 0 || !\Auth::user()) {
                return new \WHMCS\Http\Message\JsonResponse($responseData);
            }
            $userRemoteAccountLinks = \Auth::user()->remoteAccountLinks;
            if (!$userRemoteAccountLinks) {
                return new \WHMCS\Http\Message\JsonResponse($responseData);
            }
            $linkedAccounts = (new ViewHelper())->getTableData($userRemoteAccountLinks);
            $responseData["data"] = $linkedAccounts;
            return new \WHMCS\Http\Message\JsonResponse($responseData);
        } catch (\Exception $e) {
            if ($e instanceof \WHMCS\Exception) {
                $message = $e->getMessage();
            } else {
                $message = "General error";
            }
            return new \WHMCS\Http\Message\JsonResponse(["data" => $message], 400);
        }
    }

    public function delete(\WHMCS\Http\Message\ServerRequest $request)
    {
        $authnId = $request->getAttribute("authnid");
        try {
            $accountLink = \Auth::user()->remoteAccountLinks()->where("id", "=", $authnId)->firstOrFail();
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["status" => "error", "message" => "failed to load Remote Authentication User ID: " . $authnId], 400);
        }
        \DI::make("remoteAuth")->logAccountLinkDeletion($accountLink);
        $accountLink->delete();
        return new \WHMCS\Http\Message\JsonResponse(["status" => "success", "message" => "Remote Auth User removed."], 200);
    }
}
