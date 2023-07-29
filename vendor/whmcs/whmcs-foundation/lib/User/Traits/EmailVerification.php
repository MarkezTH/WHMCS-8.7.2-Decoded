<?php

namespace WHMCS\User\Traits;

trait EmailVerification
{
    protected $settingEnabled = "EnableEmailVerification";
    protected $emailVerificationTokenName = "email_verification_token";
    protected $emailVerificationTokenExpiryName = "email_verification_token_expiry";
    protected $emailVerificationTimestampName = "email_verified_at";

    public function getEmailVerificationTokenName()
    {
        return $this->emailVerificationTokenName;
    }

    public function getEmailVerificationTokenExpiryName()
    {
        return $this->emailVerificationTokenExpiryName;
    }

    public function getEmailVerificationTimestampName()
    {
        return $this->emailVerificationTimestampName;
    }

    public function isEmailVerificationEnabled()
    {
        return (bool) \WHMCS\Config\Setting::getValue($this->settingEnabled);
    }

    public function emailVerified()
    {
        return !is_null($this->getRawAttribute($this->getEmailVerificationTimestampName()));
    }

    public function needsToCompleteEmailVerification()
    {
        if ($this->isEmailVerificationEnabled() && !$this->emailVerified()) {
            return true;
        }
        return false;
    }

    public function scopeEmailVerificationToken($query, $token)
    {
        return $query->where($this->getEmailVerificationTokenName(), $token);
    }

    public function getEmailVerificationTokenExpiry()
    {
        return $this->{$this->getEmailVerificationTokenExpiryName()};
    }

    public function newEmailVerificationToken()
    {
        $token = hash("sha256", $this->getKey() . time() . mt_rand(100000, 999999) . $this->email . $this->password);
        $this->{$this->getEmailVerificationTokenName()} = $token;
        $this->{$this->getEmailVerificationTokenExpiryName()} = \WHMCS\Carbon::now()->addMinutes(60);
        $this->save();
        return $this;
    }

    public function getEmailVerificationUrl()
    {
        return fqdnRoutePath("user-email-verification", $this->{$this->getEmailVerificationTokenName()});
    }

    public function sendEmailVerification()
    {
        try {
            $emailer = \WHMCS\Mail\Emailer::factoryByTemplate("Email Address Verification", $this->getKey(), ["verification_url" => $this->newEmailVerificationToken()->getEmailVerificationUrl()]);
            $emailer->send();
        } catch (\WHMCS\Exception\Mail\SendHookAbort $e) {
            logActivity("Email Verification Message Sending Aborted by Hook - UserID: " . $this->id);
        } catch (\WHMCS\Exception\Mail\EmailSendingDisabled $e) {
            logActivity("Email Verification Message Sending Aborted by Configuration - UserID: " . $this->id);
        } catch (\WHMCS\Exception\Mail\SendFailure $e) {
            logActivity("Could not send Email Verification message to " . $this->fullName . ". Error: " . $e->getMessage());
            return false;
        }
        return true;
    }

    public function setEmailVerificationCompleted()
    {
        $this->{$this->getEmailVerificationTokenName()} = "";
        $this->{$this->getEmailVerificationTokenExpiryName()} = NULL;
        $this->{$this->getEmailVerificationTimestampName()} = \WHMCS\Carbon::now();
        $this->save();
        run_hook("UserEmailVerificationComplete", ["userId" => $this->id]);
        return $this;
    }

    public function invalidateEmailVerification()
    {
        $this->{$this->getEmailVerificationTokenName()} = "";
        $this->{$this->getEmailVerificationTokenExpiryName()} = NULL;
        $this->{$this->getEmailVerificationTimestampName()} = NULL;
        return $this;
    }
}
