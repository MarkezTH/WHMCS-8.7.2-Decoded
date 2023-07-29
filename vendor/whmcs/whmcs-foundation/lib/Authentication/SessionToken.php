<?php

namespace WHMCS\Authentication;

class SessionToken implements Contracts\Token
{
    protected $data = [];
    protected $requiredKeys = ["id", "email", "password", "userip", "timestamp", "hash"];

    public function __construct($token = NULL)
    {
        if (!is_null($token)) {
            $this->setData(json_decode($token, true) ?: []);
        }
    }

    protected static function hashValueForStorage($value)
    {
        if ($value === "") {
            throw new \WHMCS\Exception\Authentication\InvalidCredentials();
        }
        return hash("sha256", $value);
    }

    public static function factoryFromUser(\WHMCS\User\User $user)
    {
        $self = new static();
        return $self->setData(["id" => $user->id, "email" => $user->email, "password" => static::hashValueForStorage($user->password), "userip" => $user->currentIp(), "timestamp" => time()]);
    }

    protected function setData($self, $data)
    {
        $this->data = $data;
        return $this;
    }

    public function validFormat()
    {
        if (!is_array($this->data) || count($this->data) !== count($this->requiredKeys)) {
            return false;
        }
        foreach ($this->requiredKeys as $key) {
            if (empty($this->data[$key])) {
                return false;
            }
        }
        return true;
    }

    public function id()
    {
        return $this->data["id"];
    }

    protected function email()
    {
        return $this->data["email"];
    }

    protected function password()
    {
        return $this->data["password"];
    }

    protected function userip()
    {
        return $this->data["userip"];
    }

    protected function twofactor()
    {
        return $this->data["twofactor"];
    }

    protected function hash()
    {
        return $this->data["hash"];
    }

    public function generate()
    {
        $data = $this->data;
        $data["hash"] = $this->generateHash();
        return json_encode($data);
    }

    protected function getAppHash()
    {
        return \DI::make("config")->cc_encryption_hash;
    }

    public function generateHash()
    {
        $encryptionHash = $this->getAppHash();
        $data = $this->data;
        if (array_key_exists("hash", $data)) {
            unset($data["hash"]);
        }
        $data["salt"] = substr($encryptionHash, 0, 20);
        return hash("sha256", implode("|", $data));
    }

    public function validateUser($user = true, $validateIp)
    {
        if ($this->id() !== $user->id) {
            return false;
        }
        if ($this->email() !== $user->email) {
            return false;
        }
        if ($this->password() !== static::hashValueForStorage($user->password)) {
            return false;
        }
        if ($validateIp && $this->userip() !== $user->currentIp()) {
            return false;
        }
        if ($this->hash() !== $this->generateHash()) {
            return false;
        }
        return true;
    }
}
