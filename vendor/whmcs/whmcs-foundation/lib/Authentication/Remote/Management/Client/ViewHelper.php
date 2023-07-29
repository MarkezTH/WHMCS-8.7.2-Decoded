<?php

namespace WHMCS\Authentication\Remote\Management\Client;

class ViewHelper
{
    private function getLinkedAccounts($clientId, $contactId = NULL)
    {
        $responseData = ["linked" => 0, "accounts" => []];
        $remoteAuth = \DI::make("remoteAuth");
        if (count($remoteAuth->getEnabledProviders()) === 0) {
            return $responseData;
        }
        $userRemoteAccountLinks = NULL;
        if ($contactId) {
            $contact = \WHMCS\User\Client\Contact::find($contactId);
            $userRemoteAccountLinks = $contact->remoteAccountLinks;
        } else {
            if ($clientId) {
                $client = \WHMCS\User\Client::find($clientId);
                $userRemoteAccountLinks = $client->remoteAccountLinks;
            }
        }
        if (!$userRemoteAccountLinks) {
            return $responseData;
        }
        $linkedAccounts = [];
        foreach ($userRemoteAccountLinks as $account) {
            $provider = $remoteAuth->getProviderByName($account->provider);
            $linkedAccounts[$account->id] = $provider->parseMetadata($account->metadata);
        }
        $responseData["linked"] = count($linkedAccounts);
        $responseData["accounts"] = $linkedAccounts;
        return $responseData;
    }

    public function getTemplateData($targetHtml = NULL)
    {
        if (is_null($targetHtml)) {
            $targetHtml = \WHMCS\Authentication\Remote\Providers\AbstractRemoteAuthProvider::HTML_TARGET_REGISTER;
        }
        $data = [];
        $remoteAuth = \DI::make("remoteAuth");
        $providers = $remoteAuth->getEnabledProviders();
        $data["linkableProviders"] = NULL;
        if (0 < count($providers)) {
            $twoFa = new \WHMCS\TwoFactorAuthentication();
            $twoFa->setUser(\Auth::user());
            $isTwoFaEnforcementActive = $twoFa->isForced() && !$twoFa->isEnabled() && $twoFa->isActiveClients() && !\WHMCS\User\Admin::getAuthenticatedUser();
            if (!$isTwoFaEnforcementActive) {
                $providersData = [];
                foreach ($providers as $provider) {
                    $providersData[] = ["provider" => $provider, "code" => $provider->getHtml($targetHtml), "login_button" => $provider->getHtmlButton(\WHMCS\Authentication\Remote\Providers\AbstractRemoteAuthProvider::HTML_TARGET_LOGIN)];
                }
                $data["linkableProviders"] = $providersData;
            }
        }
        $userLinkedProviderData = [];
        if ($providers) {
            $userLinkedProviderData = $this->getLinkedAccounts(\WHMCS\Session::get("uid"), \WHMCS\Session::get("cid"));
        }
        $data["userLinkedProviderData"] = $userLinkedProviderData;
        $data["linkedAccountsUrl"] = routePath("auth-manage-client-links");
        $data["remote_auth_prelinked"] = false;
        if ($remoteAuth->isPrelinkPerformed()) {
            $data["remote_auth_prelinked"] = true;
            $data["password"] = $remoteAuth->generateRandomPassword();
        }
        return $data;
    }

    public function getTableData($userRemoteAccountLinks)
    {
        $remoteAuth = \DI::make("remoteAuth");
        $linkedAccounts = [];
        foreach ($userRemoteAccountLinks as $account) {
            $templateArray = ["buttonTitle" => \Lang::trans("unlink"), "modalTitle" => \Lang::trans("remoteAuthn.areYouSure"), "modalBody" => \Lang::trans("remoteAuthn.unlinkDesc"), "closeBtnTitle" => \Lang::trans("cancel"), "saveBtnTitle" => \Lang::trans("unlink"), "targetUrl" => routePath("auth-manage-client-delete") . $account->id, "modalId" => $account->id, "saveBtnIcon" => "fas fa-ban", "closeBtnIcon" => "fas fa-unlink"];
            $confirmationModal = (new \WHMCS\ClientArea())->getSingleTPLOutput("includes/confirmation", $templateArray);
            $provider = $remoteAuth->getProviderByName($account->provider);
            $meta = $provider->parseMetadata($account->metadata);
            $linkedAccounts[] = [$meta->getProviderName(), $meta->getFullName(), $meta->getEmailAddress(), $confirmationModal];
        }
        return $linkedAccounts;
    }
}
