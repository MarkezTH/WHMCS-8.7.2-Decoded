<?php

namespace WHMCS\Authentication\Remote\Providers;

abstract class AbstractRemoteAuthProvider
{
    protected $config = [];
    protected $twoFaRedirectUrl = "";
    protected $description = "";
    protected $configurationDescription = "";
    const NAME = "";
    const FRIENDLY_NAME = "";
    const HTML_TARGET_LOGIN = "login";
    const HTML_TARGET_REGISTER = "register";
    const HTML_TARGET_CHECKOUT = "checkout";
    const HTML_TARGET_CONNECT = "connect";
    const LOGIN_RESULT_GENERAL_ERROR = "error";
    const LOGIN_RESULT_LOGGED_IN = "logged_in";
    const LOGIN_RESULT_LINKING_COMPLETE = "linking_complete";
    const LOGIN_RESULT_LOGIN_TO_LINK = "login_to_link";
    const LOGIN_RESULT_2FA_NEEDED = "2fa_needed";
    const LOGIN_RESULT_OTHER_USER_EXISTS = "other_user_exists";
    const LOGIN_RESULT_ALREADY_LINKED_HERE = "already_linked";
    const LOGIN_RESULT_USER_NOT_AUTHORIZED = "not_authorized";

    public function __construct()
    {
        $this->setConfiguration(\WHMCS\Authentication\Remote\ProviderSetting::where("provider", "=", static::NAME)->pluck("value", "setting")->toArray());
    }

    public function getConfigurationFields()
    {
        return [];
    }
    public abstract function getEnabled();
    public abstract function setEnabled($value);
    public abstract function linkAccount($context);
    public abstract function verifyConfiguration();
    public abstract function getHtmlScriptCode($htmlTarget);
    public abstract function getHtmlButton($htmlTarget);

    public function getHtml($htmlTarget)
    {
        return $this->getHtmlScriptCode($htmlTarget) . $this->getHtmlButton($htmlTarget);
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getConfigurationDescription()
    {
        return $this->configurationDescription;
    }

    public function getConfiguration()
    {
        return $this->config;
    }

    public function setConfiguration($config)
    {
        $this->config = $config;
    }

    public function saveConfiguration()
    {
        \WHMCS\Authentication\Remote\ProviderSetting::forProvider($this)->delete();
        foreach ($this->getConfiguration() as $settingName => $value) {
            $providerSetting = new \WHMCS\Authentication\Remote\ProviderSetting();
            $providerSetting->provider = static::NAME;
            $providerSetting->setting = $settingName;
            $providerSetting->value = $value;
            $providerSetting->save();
        }
    }
    public abstract function parseMetadata($metadata);

    protected function findAccountLink($remoteUserId)
    {
        $accountLink = \WHMCS\Authentication\Remote\AccountLink::viaProvider($this)->where("remote_user_id", "=", $remoteUserId)->first();
        return $accountLink;
    }

    protected function linkLoggedInUser($remoteUserId, $context = NULL)
    {
        if (!\Auth::user()) {
            return false;
        }
        $accountLink = new \WHMCS\Authentication\Remote\AccountLink();
        $accountLink->provider = static::NAME;
        $accountLink->remote_user_id = $remoteUserId;
        $accountLink->userId = \Auth::user()->id;
        $accountLink->metadata = $context;
        $accountLink->save();
        $remoteAuth = \DI::make("remoteAuth");
        $remoteAuth->logAccountLinkCreation($accountLink);
        $remoteAuth->eraseProviderContext($this);
        return true;
    }

    protected function logUserWithAccountLink(\WHMCS\Authentication\Remote\AccountLink $accountLink)
    {
        $remoteAuth = \DI::make("remoteAuth");
        if (!$accountLink->user) {
            return static::LOGIN_RESULT_GENERAL_ERROR;
        }
        $remoteAuth->eraseProviderContext($this);
        try {
            \Auth::attemptRemoteAuthLogin($accountLink);
        } catch (\WHMCS\Exception\Authentication\RequiresSecondFactor $e) {
            $remoteAuth->logAccountLinkLogin($accountLink, true);
            return static::LOGIN_RESULT_2FA_NEEDED;
        }
        $remoteAuth->logAccountLinkLogin($accountLink, false);
        return static::LOGIN_RESULT_LOGGED_IN;
    }

    protected function isLoggedInUsersAccountLink(\WHMCS\Authentication\Remote\AccountLink $accountLink)
    {
        if ($accountLink->userId !== \Auth::user()->id) {
            return false;
        }
        return true;
    }

    protected function processRemoteUserId($remoteUserId, $context, $failIfExists)
    {
        $accountLink = $this->findAccountLink($remoteUserId);
        if ($accountLink && $failIfExists) {
            if ($this->isLoggedInUsersAccountLink($accountLink)) {
                return static::LOGIN_RESULT_ALREADY_LINKED_HERE;
            }
            return static::LOGIN_RESULT_OTHER_USER_EXISTS;
        }
        if ($accountLink) {
            return $this->logUserWithAccountLink($accountLink);
        }
        if (\Auth::user() && $this->linkLoggedInUser($remoteUserId, $context)) {
            return static::LOGIN_RESULT_LINKING_COMPLETE;
        }
        $this->saveContext($context);
        return static::LOGIN_RESULT_LOGIN_TO_LINK;
    }

    protected function saveContext($context)
    {
        $remoteAuth = \DI::make("remoteAuth");
        $remoteAuth->saveProviderContext($this, $context);
    }

    protected function retrieveContext()
    {
        $remoteAuth = \DI::make("remoteAuth");
        return $remoteAuth->retrieveProviderContext($this);
    }

    public function getRegistrationFormData($context)
    {
        return [];
    }

    public function getRemoteAccountName($context)
    {
        return "";
    }

    public function isOnCheckout()
    {
        if (\App::getCurrentFilename() == "cart" && \App::getFromRequest("a") == "checkout") {
            return true;
        }
        return false;
    }
}
