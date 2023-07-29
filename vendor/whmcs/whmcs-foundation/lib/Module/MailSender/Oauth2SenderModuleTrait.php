<?php

namespace WHMCS\Module\MailSender;

trait Oauth2SenderModuleTrait
{
    protected $mailModule = NULL;

    public function setMailModuleInstance($mail)
    {
        $this->mailModule = $mail;
    }

    public function updateOauth2RefreshToken($refreshToken)
    {
        if ($this->mailModule) {
            $this->mailModule->updateOauth2RefreshToken($refreshToken);
        }
    }
}
