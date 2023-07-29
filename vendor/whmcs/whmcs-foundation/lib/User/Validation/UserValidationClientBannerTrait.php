<?php

namespace WHMCS\User\Validation;

trait UserValidationClientBannerTrait
{
    public function dismissClientBanner()
    {
        \WHMCS\Session::setAndRelease(self::CLIENT_BANNER_DISMISS_VAR_NAME, true);
    }

    private function isClientBannerDismissed()
    {
        return (bool) \WHMCS\Session::get(self::CLIENT_BANNER_DISMISS_VAR_NAME);
    }

    public function shouldShowClientBanner()
    {
        $loggedInUser = \Auth::user();
        return $loggedInUser && $loggedInUser->isValidationPending() && !$this->isClientBannerDismissed();
    }
}
