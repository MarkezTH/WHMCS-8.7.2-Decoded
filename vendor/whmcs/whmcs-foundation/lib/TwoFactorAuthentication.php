<?php

namespace WHMCS;

class TwoFactorAuthentication
{
    protected $settings = [];
    protected $clientmodules = [];
    protected $adminmodules = [];
    protected $user = NULL;

    public function __construct()
    {
        $this->loadSettings();
    }

    protected function loadSettings()
    {
        $this->settings = safe_unserialize(Config\Setting::getValue("2fasettings"));
        if (!isset($this->settings["modules"])) {
            return false;
        }
        foreach ($this->settings["modules"] as $module => $data) {
            if (!empty($data["clientenabled"])) {
                $this->clientmodules[] = $module;
            }
            if (!empty($data["adminenabled"])) {
                $this->adminmodules[] = $module;
            }
        }
        return true;
    }

    public function getModuleSettings($module)
    {
        if (!is_array($this->settings["modules"]) || !array_key_exists($module, $this->settings["modules"]) || !is_array($this->settings["modules"][$module])) {
            return [];
        }
        return $this->settings["modules"][$module];
    }

    public function getModuleSetting($module, $name)
    {
        $settings = $this->getModuleSettings($module);
        return isset($settings[$name]) ? $settings[$name] : NULL;
    }

    public function setModuleSetting($module, $name, $value)
    {
        $this->settings["modules"][$module][$name] = $value;
        return $this;
    }

    public function isModuleEnabled($module)
    {
        return $this->isModuleEnabledForClients($module) || $this->isModuleEnabledForAdmins($module);
    }

    public function isModuleEnabledForClients($module)
    {
        $settings = $this->getModuleSettings($module);
        return (bool) ($settings["clientenabled"] ?? false);
    }

    public function isModuleEnabledForAdmins($module)
    {
        $settings = $this->getModuleSettings($module);
        return (bool) ($settings["adminenabled"] ?? false);
    }

    public function setModuleClientEnablementStatus($module, $status)
    {
        $this->setModuleSetting($module, "clientenabled", (int) (bool) $status);
        return $this;
    }

    public function setModuleAdminEnablementStatus($module, $status)
    {
        $this->setModuleSetting($module, "adminenabled", (int) (bool) $status);
        return $this;
    }

    public function isForced()
    {
        if ($this->isEndUser()) {
            return $this->isForcedClients();
        }
        if ($this->isAdminUser()) {
            return $this->isForcedAdmins();
        }
        return false;
    }

    public function isForcedClients()
    {
        return (bool) $this->settings["forceclient"];
    }

    public function isForcedAdmins()
    {
        return (bool) $this->settings["forceadmin"];
    }

    public function setForcedClients($status)
    {
        $this->settings["forceclient"] = (int) (bool) $status;
        return $this;
    }

    public function setForcedAdmins($status)
    {
        $this->settings["forceadmin"] = (int) (bool) $status;
        return $this;
    }

    public function save()
    {
        Config\Setting::setValue("2fasettings", safe_serialize($this->settings));
        return $this;
    }

    public function isActive()
    {
        if ($this->isEndUser()) {
            return $this->isActiveClients();
        }
        if ($this->isAdminUser()) {
            return $this->isActiveAdmins();
        }
        return false;
    }

    public function isActiveClients()
    {
        return count($this->clientmodules) ? true : false;
    }

    public function isActiveAdmins()
    {
        return count($this->adminmodules) ? true : false;
    }

    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    public function isEndUser()
    {
        return $this->user instanceof User\User;
    }

    public function isAdminUser()
    {
        return $this->user instanceof User\Admin;
    }

    public function getAvailableModules()
    {
        if ($this->isEndUser()) {
            return $this->getAvailableClientModules();
        }
        if ($this->isAdminUser()) {
            return $this->getAvailableAdminModules();
        }
        return array_unique(array_merge($this->getAvailableClientModules(), $this->getAvailableAdminModules()));
    }

    protected function getAvailableClientModules()
    {
        return $this->clientmodules;
    }

    protected function getAvailableAdminModules()
    {
        return $this->adminmodules;
    }

    public function isEnabled()
    {
        if (is_null($this->user)) {
            return false;
        }
        return $this->user->getSecondFactorModule();
    }

    protected function getModule()
    {
        if (is_null($this->user)) {
            return false;
        }
        return $this->user->getSecondFactorModule();
    }

    public function moduleCall($function, $module = "", $extraParams = [])
    {
        $mod = new Module\Security();
        $module = $module ? $module : $this->getModule();
        $loaded = $mod->load($module);
        if (!$loaded) {
            return false;
        }
        $params = $this->buildParams($module);
        $params = array_merge($params, $extraParams);
        $result = $mod->call($function, $params);
        return $result;
    }

    public function getFields()
    {
        return $this->moduleCall("get_fields");
    }

    protected function buildParams($module)
    {
        $params = [];
        $params["settings"] = $this->settings["modules"][$module];
        $params["user_info"] = ["id" => $this->user->id, "email" => $this->user->email, "username" => $this->user->getAuthIdentifier()];
        $params["user_settings"] = $this->user->getSecondFactorConfig();
        $params["post_vars"] = $_POST;
        $params["twoFactorAuthentication"] = $this;
        return $params;
    }

    public function generateChallenge()
    {
        return $this->moduleCall("challenge");
    }

    public function validateChallenge()
    {
        return $this->moduleCall("verify");
    }

    public function activateUser($module, $settings = [])
    {
        if (is_null($this->user)) {
            return false;
        }
        $encryptionHash = \App::getApplicationConfig()->cc_encryption_hash;
        $backupCode = sha1($encryptionHash . $this->user->id . time());
        $backupCode = substr($backupCode, 0, 16);
        $settings["backupcode"] = sha1($backupCode);
        $this->user->setSecondFactorModuleName($module);
        $this->user->setSecondFactorConfig($settings);
        $this->user->save();
        return substr($backupCode, 0, 4) . " " . substr($backupCode, 4, 4) . " " . substr($backupCode, 8, 4) . " " . substr($backupCode, 12, 4);
    }

    public function disableUser()
    {
        $this->user->setSecondFactorModuleName("");
        $this->user->setSecondFactorConfig([]);
        $this->user->save();
        return true;
    }

    public function validateAndDisableUser($inputVerifyPassword)
    {
        if (!$this->isEnabled()) {
            throw new Exception("Not enabled");
        }
        if ($this->isEndUser()) {
            if (!$this->user->verifyPassword(Input\Sanitize::decode($inputVerifyPassword))) {
                throw new Exception("Password incorrect. Please try again.");
            }
        } else {
            if ($this->isAdminUser()) {
                $auth = new Auth();
                $auth->getInfobyID($this->user->id);
                if (!$auth->comparePassword($inputVerifyPassword)) {
                    throw new Exception("Password incorrect. Please try again.");
                }
            } else {
                throw new Exception("No user defined");
            }
        }
        $this->disableUser();
        return true;
    }

    public function saveUserSettings($arr)
    {
        $config = $this->user->getSecondFactorConfig();
        $config = array_merge($config, $arr);
        $this->user->setSecondFactorConfig($config);
        $this->user->save();
    }

    public function getUserSetting($key)
    {
        $config = $this->user->getSecondFactorConfig();
        return array_key_exists($key, $config) ? $config[$key] : "";
    }

    public function verifyBackupCode($code)
    {
        $backupCode = $this->getUserSetting("backupcode");
        if (!$backupCode) {
            return false;
        }
        $code = preg_replace("/[^a-z0-9]/", "", strtolower($code));
        $code = sha1($code);
        return $backupCode == $code;
    }

    public function generateNewBackupCode()
    {
        $encryptionHash = \App::getApplicationConfig()->cc_encryption_hash;
        $backupCode = sha1($encryptionHash . $this->user->id . time() . rand(10000, 99999));
        $backupCode = substr($backupCode, 0, 16);
        $this->saveUserSettings(["backupcode" => sha1($backupCode)]);
        return substr($backupCode, 0, 4) . " " . substr($backupCode, 4, 4) . " " . substr($backupCode, 8, 4) . " " . substr($backupCode, 12, 4);
    }

    public function isEnrollmentNeeded()
    {
        return $this->isForced() && !$this->isEnabled() && $this->isActive();
    }
}
