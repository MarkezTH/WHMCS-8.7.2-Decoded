<?php

namespace WHMCS\User\Traits;

trait PasswordResets
{
    protected $resetTokenName = "reset_token";
    protected $resetTokenExpiryName = "reset_token_expiry";

    public function getResetTokenName()
    {
        return $this->resetTokenName;
    }

    public function getResetTokenExpiryName()
    {
        return $this->resetTokenExpiryName;
    }

    public function getResetTokenExpiry()
    {
        return $this->{$this->getResetTokenExpiryName()};
    }

    public function scopeResetToken($query, $token)
    {
        return $query->where($this->getResetTokenName(), $token);
    }

    public function newPasswordResetToken()
    {
        $resetKey = hash("sha256", $this->getKey() . mt_rand(100000, 999999) . $this->password);
        $this->{$this->getResetTokenName()} = $resetKey;
        $this->{$this->getResetTokenExpiryName()} = \WHMCS\Carbon::now()->addHours(2);
        $this->save();
        return $this;
    }

    public function getPasswordResetUrl()
    {
        return fqdnRoutePath("password-reset-use-key", $this->{$this->getResetTokenName()});
    }

    public function sendPasswordResetEmail()
    {
        $emailer = \WHMCS\Mail\Emailer::factoryByTemplate("Password Reset Validation", $this->getKey(), ["reset_password_url" => $this->newPasswordResetToken()->getPasswordResetUrl()]);
        $email = $emailer->send();
        logActivity("Password Reset Requested", 0, ["userDesc" => "Visitor", "addUserId" => $this->id, "requireIp" => true]);
        return $email;
    }
}
