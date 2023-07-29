<?php

namespace WHMCS\ClientArea\User;

class EmailVerification
{
    const SESSION_DISMISS_NAME = "EmvDismissed";

    public static function dismiss()
    {
        \WHMCS\Session::setAndRelease(self::SESSION_DISMISS_NAME, true);
    }

    public static function isDismissed()
    {
        return (bool) \WHMCS\Session::get(self::SESSION_DISMISS_NAME);
    }

    public static function shouldShowEmailVerificationBanner()
    {
        return \Auth::user() && \Auth::user()->needsToCompleteEmailVerification() && !self::isDismissed();
    }
}
