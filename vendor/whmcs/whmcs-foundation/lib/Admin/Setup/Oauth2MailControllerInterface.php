<?php

namespace WHMCS\Admin\Setup;

interface Oauth2MailControllerInterface
{
    public function getStoredClientSecret($request);
}
