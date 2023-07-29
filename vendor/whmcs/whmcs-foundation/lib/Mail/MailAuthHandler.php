<?php

namespace WHMCS\Mail;

class MailAuthHandler
{
    const AUTH_TYPE_PLAIN = "plain";
    const AUTH_TYPE_OAUTH2 = "oauth2";
    const PROVIDER_GENERIC = "Generic";
    const PROVIDER_GOOGLE = "Google";
    const PROVIDER_MICROSOFT = "Microsoft";
    const PROVIDER_CLASSES = NULL;
    const PROVIDER_SMTP_HOSTS = NULL;
    const PROVIDER_SMTP_PORTS = NULL;
    const PROVIDER_POP_HOSTS = NULL;
    const PROVIDER_POP_PORTS = NULL;
    const CONTEXT_OUTGOING_MAIL = "outgoing_mail";
    const CONTEXT_SUPPORT_DEPARTMENT = "support_department";

    public function createProvider($providerName, $clientId, $clientSecret, $contextName)
    {
        switch ($contextName) {
            case self::CONTEXT_OUTGOING_MAIL:
                $callbackRouteName = "admin-setup-mail-provider-oauth2-callback";
                break;
            case self::CONTEXT_SUPPORT_DEPARTMENT:
                $callbackRouteName = "admin-setup-support-oauth2-callback";
                $params = ["clientId" => $clientId, "clientSecret" => $clientSecret, "redirectUri" => fqdnRoutePath($callbackRouteName)];
                $params = array_merge($params, $this->getProviderSpecificParams($providerName));
                if (!isset(self::PROVIDER_CLASSES[$providerName])) {
                    throw new \WHMCS\Exception("Failed to find a provider by name: " . $providerName);
                }
                $providerClass = self::PROVIDER_CLASSES[$providerName];
                return new $providerClass($params);
                break;
            default:
                throw new \WHMCS\Exception("Invalid mail auth context");
        }
    }

    public static function getProviderLegacyMailProtocolSupportMap()
    {
        $map = [self::PROVIDER_GENERIC => true];
        foreach (self::PROVIDER_CLASSES as $providerName => $providerClass) {
            $map[$providerName] = $providerClass::supportsLegacyMailProtocols();
        }
        return $map;
    }

    public static function getProviderAuthTypeMap()
    {
        $map = [self::PROVIDER_GENERIC => [self::AUTH_TYPE_PLAIN]];
        foreach (self::PROVIDER_CLASSES as $providerName => $providerClass) {
            $map[$providerName] = $providerClass::getSupportedAuthTypes();
        }
        return $map;
    }

    public function getProviderSpecificParams($providerName)
    {
        $options = [];
        switch ($providerName) {
            case self::PROVIDER_GOOGLE:
                $options = ["accessType" => "offline", "prompt" => "consent"];
                break;
            case self::PROVIDER_MICROSOFT:
                $options = ["prompt" => "consent"];
                break;
            default:
                return $options;
        }
    }

    public function getAuthorizationUrlOptions($providerName)
    {
        $options = [];
        switch ($providerName) {
            case self::PROVIDER_GOOGLE:
                $options = ["scope" => ["https://mail.google.com/"]];
                break;
            case self::PROVIDER_MICROSOFT:
                $options = ["scope" => Incoming\Provider\MicrosoftAuthProvider::SCOPES];
                break;
            default:
                return $options;
        }
    }
}
