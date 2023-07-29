<?php

namespace WHMCS\Module\Contracts;

interface Oauth2SenderModuleInterface extends SenderModuleInterface
{
    public function setMailModuleInstance($mail);
}
