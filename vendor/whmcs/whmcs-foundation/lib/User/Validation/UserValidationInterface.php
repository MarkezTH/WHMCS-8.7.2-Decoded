<?php

namespace WHMCS\User\Validation;

interface UserValidationInterface
{
    public function isEnabled();

    public function isAutoEnabled();

    public function initiateForUser($user);

    public function refreshStatusForUser($user);

    public function isRequestComplete($user);

    public function getSubmitUrlForUser($user);

    public function getSubmitHost();

    public function getViewHost();

    public function getViewUrlForUser($user);

    public function getStatusForOutput($user);

    public function getStatusColor($status);

    public function sendVerificationEmail($user);

    public function shouldShowClientBanner();

    public function dismissClientBanner();
}
