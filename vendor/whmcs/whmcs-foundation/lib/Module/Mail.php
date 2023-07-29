<?php

namespace WHMCS\Module;

class Mail extends AbstractModule
{
    protected $type = self::TYPE_MAIL;
    protected $senderInterface = NULL;
    protected $settings = NULL;
    protected $protectedConfiguration = ["oauth2_callback_url"];
    protected static $defaultConfiguration = ["module" => "PhpMail", "configuration" => ["encoding" => 0]];

    private function __construct()
    {
    }

    public static function factory()
    {
        $mail = new self();
        $settings = $mail->getSettings();
        $module = $settings["module"];
        try {
            $mail->load($module);
        } catch (\WHMCS\Exception\Module\InvalidConfiguration $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Module\NotServicable("Unable to load mail module: " . $module);
        }
        $mail->moduleParams = array_merge($mail->moduleParams, $settings["configuration"]);
        return $mail;
    }

    public function load($module, $globalVariable = NULL)
    {
        $this->senderInterface = NULL;
        $response = parent::load($module, $globalVariable);
        if (!$response) {
            throw new \WHMCS\Exception\Module\NotFound();
        }
        return $response;
    }

    protected function setLoadedModule($module)
    {
        parent::setLoadedModule($module);
        $this->senderInterface = $this->createMailInterface();
    }

    public function getDisplayName()
    {
        $displayName = $this->senderInterface->getDisplayName();
        if (!$displayName) {
            $displayName = ucfirst($this->senderInterface->getName());
        }
        return \WHMCS\Input\Sanitize::makeSafeForOutput($displayName);
    }

    public function getSettings()
    {
        if (is_null($this->settings) || !is_array($this->settings)) {
            try {
                $this->setSettings();
            } catch (\Exception $e) {
                $this->settings = self::$defaultConfiguration;
            }
        }
        return $this->settings;
    }

    public function setSettings($settings = NULL)
    {
        if (is_array($settings)) {
            if (empty($settings["module"])) {
                throw new \WHMCS\Exception\Module\InvalidConfiguration("Missing module definition");
            }
            if (empty($settings["configuration"])) {
                throw new \WHMCS\Exception\Module\InvalidConfiguration("Missing module configuration");
            }
        }
        if (is_null($settings)) {
            $settings = self::getStoredConfiguration();
        }
        $this->settings = $settings;
    }

    public function getConfiguration()
    {
        return $this->call("settings");
    }

    public static function getStoredConfiguration()
    {
        $mailConfig = json_decode(\WHMCS\Input\Sanitize::decode(decrypt(\WHMCS\Config\Setting::getValue("MailConfig"))), true);
        if (is_null($mailConfig)) {
            $mailConfig = self::$defaultConfiguration;
        }
        return $mailConfig;
    }

    public function call($method, $params = [], \WHMCS\Mail\Message $message = NULL)
    {
        if ($this->functionExists($method)) {
            $params = $this->prepareParams($params);
            $params = array_merge($this->getParams(), $params);
            return $this->senderInterface->{$method}($params, $message);
        }
        return self::FUNCTIONDOESNTEXIST;
    }

    public function send(\WHMCS\Mail\Message $message)
    {
        if (\WHMCS\Config\Setting::getValue("DisableEmailSending")) {
            throw new \WHMCS\Exception\Mail\EmailSendingDisabled("Email Sending has been disabled globally.");
        }
        try {
            $this->call("send", [], $message);
        } catch (\WHMCS\Exception\Mail\InvalidAddress $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Mail\SendFailure("Email Sending Failed: " . $e->getMessage());
        }
    }

    public function functionExists($method)
    {
        if (!$this->getLoadedModule()) {
            return false;
        }
        return method_exists($this->senderInterface, $method);
    }

    public function validateConfiguration($newSettings)
    {
        $this->call("testConnection", $this->prepareSettingsToValidate($newSettings));
    }

    protected function createMailInterface()
    {
        $class = "\\WHMCS\\Module\\Mail\\" . $this->getLoadedModule();
        if (!class_exists($class)) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration("Unable to load mail module: " . $class);
        }
        $senderInterface = new $class();
        if (!$senderInterface instanceof Contracts\SenderModuleInterface) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration("Mail module must implement \\WHMCS\\Mail\\SenderInterface");
        }
        if ($senderInterface instanceof Contracts\Oauth2SenderModuleInterface) {
            $senderInterface->setMailModuleInstance($this);
        }
        return $senderInterface;
    }

    public function updateConfiguration($parameters = [])
    {
        $moduleSettings = $this->getConfiguration();
        $settingsToSave = [];
        if (0 < count($parameters)) {
            foreach ($parameters as $key => $value) {
                if (array_key_exists($key, $moduleSettings)) {
                    $settingsToSave[$key] = $value;
                }
            }
        }
        if (0 < count($settingsToSave)) {
            $this->saveConfiguration($settingsToSave);
        }
    }

    public function updateOauth2RefreshToken($refreshToken)
    {
        $storedConfiguration = self::getStoredConfiguration();
        if ($storedConfiguration["module"] === $this->getLoadedModule()) {
            $storedConfiguration["configuration"]["oauth2_refresh_token"] = $refreshToken;
            $this->saveConfiguration($storedConfiguration["configuration"], true);
        }
    }

    protected function saveConfiguration($newSettings = [], $isImplicitUpdate = false)
    {
        $moduleName = $this->getLoadedModule();
        $moduleSettings = $this->getConfiguration();
        $previousConfiguration = $this->getSettings();
        $settingsToSave = [];
        $changes = [];
        $isChanged = false;
        if ($moduleName != $previousConfiguration["module"]) {
            $isChanged = true;
            $changes[] = "Mail Provider " . $this->getDisplayName() . " Activated";
        }
        $previousSettings = $previousConfiguration["configuration"];
        foreach ($moduleSettings as $key => $values) {
            $isLoggableChange = !in_array($key, $this->protectedConfiguration) && !$isImplicitUpdate;
            $type = NULL;
            if (array_key_exists("Type", $values)) {
                $type = $values["Type"];
            }
            if ($type !== "System") {
                if (isset($newSettings[$key])) {
                    $settingsToSave[$key] = $newSettings[$key];
                } else {
                    if ($type === "yesno") {
                        $settingsToSave[$key] = "";
                    } else {
                        if (isset($values["Default"])) {
                            $settingsToSave[$key] = $values["Default"];
                        }
                    }
                }
                if ($type === "password" && isset($newSettings[$key]) && isset($previousSettings[$key])) {
                    $updatedPassword = interpretMaskedPasswordChangeForStorage($newSettings[$key], $previousSettings[$key]);
                    if ($updatedPassword === false || $isImplicitUpdate) {
                        $settingsToSave[$key] = $previousSettings[$key];
                    } else {
                        if ($isLoggableChange) {
                            $changes[] = "'" . $key . "' value modified";
                        }
                    }
                }
                if ($type === "yesno") {
                    if (!empty($settingsToSave[$key]) && $settingsToSave[$key] !== "off" && $settingsToSave[$key] !== "disabled") {
                        $settingsToSave[$key] = "on";
                    } else {
                        $settingsToSave[$key] = "";
                    }
                    if (empty($previousSettings[$key])) {
                        $previousSettings[$key] = "";
                    }
                    if ($previousSettings[$key] != $settingsToSave[$key] && $isLoggableChange) {
                        $newSetting = $settingsToSave[$key] ?: "off";
                        $changes[] = "'" . $key . "' set to '" . $newSetting . "'";
                    }
                } else {
                    if (empty($settingsToSave[$key])) {
                        $settingsToSave[$key] = "";
                    }
                    if (empty($previousSettings[$key])) {
                        $previousSettings[$key] = "";
                    }
                    if ($isLoggableChange && $type !== "password" && (!$previousSettings[$key] && $settingsToSave[$key] || $previousSettings[$key] != $settingsToSave[$key])) {
                        $changes[] = "'" . $key . "' set to '" . $settingsToSave[$key] . "'";
                    }
                }
                $isChanged = $isChanged || $previousSettings[$key] != $settingsToSave[$key];
            }
        }
        if ($isChanged) {
            array_walk($settingsToSave, function (&$value, $index) {
                $value = \WHMCS\Input\Sanitize::decode($value);
            });
            $newConfiguration = ["module" => $this->getLoadedModule(), "configuration" => $settingsToSave];
            if (!$isImplicitUpdate) {
                $this->validateConfiguration($newConfiguration["configuration"]);
            }
            \WHMCS\Config\Setting::setValue("MailConfig", encrypt(json_encode($newConfiguration)));
            logAdminActivity("Mail Provider Configuration Modified: '" . $this->getDisplayName() . "' - " . implode(". ", $changes) . ".");
            $this->setSettings($newConfiguration);
        }
        return $this;
    }

    public function getSenderInterface($SenderModuleInterface)
    {
        return $this->senderInterface;
    }

    public function environmentCheck()
    {
        $return = [];
        if ($this->functionExists("validateEnvironment")) {
            $return = $this->call("validateEnvironment");
        }
        return $return;
    }
}
