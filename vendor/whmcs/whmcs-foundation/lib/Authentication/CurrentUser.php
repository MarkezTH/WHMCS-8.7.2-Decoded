<?php

namespace WHMCS\Authentication;

use WHMCS\Application\Support\Facades\Auth;

class CurrentUser
{
    public function isAuthenticatedUser()
    {
        return (bool) $this->user();
    }

    public function isAuthenticatedAdmin()
    {
        return (bool) $this->admin();
    }

    public function isMasqueradingAdmin()
    {
        return (bool) ($this->admin() && $this->client() && $this->user());
    }

    public function user()
    {
        return Auth::user();
    }

    public function admin()
    {
        return \WHMCS\User\Admin::getAuthenticatedUser();
    }

    public function client()
    {
        return Auth::client();
    }
}
