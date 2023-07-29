<?php

namespace WHMCS\Admin\Account;

class TwoFactorController extends \WHMCS\Authentication\TwoFactor\TwoFactorController
{
    protected $inAdminArea = true;
    protected $userIdSessionVariableName = "adminid";
}
