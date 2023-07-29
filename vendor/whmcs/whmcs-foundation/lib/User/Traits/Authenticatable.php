<?php

namespace WHMCS\User\Traits;

trait Authenticatable
{
    protected $authIdentifierName = "email";
    protected $secondFactorModuleName = "second_factor";
    protected $secondFactorConfigName = "second_factor_config";
    protected $rememberTokenName = "remember_token";

    public function getAuthIdentifierName()
    {
        return $this->authIdentifierName;
    }

    public function getAuthIdentifier()
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function getRememberToken()
    {
        if ($this->getRememberTokenName()) {
            return (string) $this->{$this->getRememberTokenName()};
        }
        return NULL;
    }

    public function setRememberToken($self, $value)
    {
        if ($this->getRememberTokenName()) {
            $this->{$this->getRememberTokenName()} = $value;
        }
        return $this;
    }

    public function getRememberTokenName()
    {
        return $this->rememberTokenName;
    }

    public function getSecondFactorModule()
    {
        if ($this->getSecondFactorModuleName()) {
            return (string) $this->{$this->getSecondFactorModuleName()};
        }
        return NULL;
    }

    public function setSecondFactorModuleName($self, $value)
    {
        if ($this->getSecondFactorModuleName()) {
            $this->{$this->getSecondFactorModuleName()} = $value;
        }
        return $this;
    }

    public function getSecondFactorModuleName()
    {
        return $this->secondFactorModuleName;
    }

    public function getSecondFactorConfig()
    {
        if ($this->getSecondFactorConfigName()) {
            $config = $this->{$this->getSecondFactorConfigName()};
            if (!is_string($config) || strlen($config) == 0) {
                return [];
            }
            $data = json_decode($config, true);
            if (json_last_error() !== JSON_ERROR_NONE || is_null($data)) {
                $data = safe_unserialize($config);
                if (is_array($data)) {
                    $this->setSecondFactorConfig($data)->save();
                }
            }
            if (!is_array($data)) {
                $data = [];
            }
            return $data;
        }
        return NULL;
    }

    public function setSecondFactorConfig($self, $value)
    {
        $value = empty($value) ? "" : json_encode($value);
        if ($this->getSecondFactorConfigName()) {
            $this->{$this->getSecondFactorConfigName()} = $value;
        }
        return $this;
    }

    public function getSecondFactorConfigName()
    {
        return $this->secondFactorConfigName;
    }

    public function banIpAddress()
    {
        \WHMCS\Database\Capsule::table("tblbannedips")->insert(["ip" => \App::getRemoteIp(), "reason" => "Login Attempts Exceeded", "expires" => \WHMCS\Carbon::now()->addMinutes(15)->toDateTimeString()]);
        return $this;
    }

    public function disableTwoFactorAuthentication()
    {
        $this->setSecondFactorModuleName("");
        $this->setSecondFactorConfig([]);
        return $this;
    }
}
