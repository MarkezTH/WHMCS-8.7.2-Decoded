<?php

namespace WHMCS\ClientArea\User;

class UserController
{
    public function profile(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true);
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaUserProfile");
        $view->setPageTitle(\Lang::trans("yourProfile"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb(routePath("user-profile"), \Lang::trans("yourProfile"));
        $sidebarName = "user";
        \Menu::addContext("userProfile", true);
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        $uneditableFields = explode(",", \WHMCS\Config\Setting::getValue("ClientsProfileUneditableFields"));
        return $view->setTemplate("user-profile")->setTemplateVariables(["user" => \Auth::user(), "uneditableFields" => $uneditableFields]);
    }

    public function saveProfile(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true, "user-profile");
        $firstNameLocked = $lastNameLocked = false;
        $firstName = $lastName = NULL;
        $uneditableFields = explode(",", \WHMCS\Config\Setting::getValue("ClientsProfileUneditableFields"));
        if (!in_array("firstname", $uneditableFields)) {
            $firstName = trim($request->request()->get("firstname", NULL));
        } else {
            $firstNameLocked = true;
        }
        if (!in_array("lastname", $uneditableFields)) {
            $lastName = trim($request->request()->get("lastname", NULL));
        } else {
            $lastNameLocked = true;
        }
        try {
            $user = \Auth::user();
            $oldUserDetails = $user->getDetails();
            if (empty($firstName) && !$firstNameLocked) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("clientareaerrorfirstname"));
            }
            if (empty($lastName) && !$lastNameLocked) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("clientareaerrorlastname"));
            }
            if (!$firstNameLocked) {
                $user->firstName = $firstName;
            }
            if (!$lastNameLocked) {
                $user->lastName = $lastName;
            }
            if ($user->isDirty()) {
                $user->save();
                run_hook("UserEdit", array_merge($user->getDetails(), ["olddata" => $oldUserDetails]));
                logActivity("User Profile Modified");
            }
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withSuccess(\Lang::trans("changessavedsuccessfully"));
        } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withError($e->getMessage());
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function saveProfileEmail(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true, "user-profile");
        $emailLocked = false;
        $uneditableFields = explode(",", \WHMCS\Config\Setting::getValue("ClientsProfileUneditableFields"));
        $email = "";
        if (!in_array("email", $uneditableFields)) {
            $email = trim($request->request()->get("email"));
        } else {
            $emailLocked = true;
        }
        try {
            if (empty($email) && !$emailLocked) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("validation.required", [":attribute" => \Lang::trans("clientareaemail")]));
            }
            if (\Auth::user()->email !== $email && !$emailLocked) {
                $validate = new \WHMCS\Validate();
                if (!$validate->validate("banneddomain", "email", "clientareaerrorbannedemail", "", $email)) {
                    throw new \WHMCS\Exception\User\BannedEmail();
                }
                \Auth::user()->changeEmail($email);
                logActivity("Email Modified");
            }
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withSuccess(\Lang::trans("changessavedsuccessfully"));
        } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
            $errorMsg = $e->getMessage();
            if (!$errorMsg) {
                $errorMsg = \Lang::trans("clientareaerroremailinvalid");
            }
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withError($errorMsg);
        } catch (\WHMCS\Exception\User\EmailAlreadyExists $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withError(\Lang::trans("ordererroruserexists"));
        } catch (\WHMCS\Exception\User\BannedEmail $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withWarning(\Lang::trans("clientareaerrorbannedemail"));
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-profile")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function accounts(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true);
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaUserAccountsSwitch");
        $view->setPageTitle(\Lang::trans("switchAccount.title"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb(routePath("user-accounts"), \Lang::trans("switchAccount.title"));
        $sidebarName = "user";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        $accounts = \Auth::user()->clients()->orderBy("owner", "desc")->orderBy("id", "asc")->get();
        return $view->setTemplate("user-switch-account")->setTemplateVariables(["accounts" => $accounts]);
    }

    public function accountsSwitchForced(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true);
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaUserAccountsForcedSwitch");
        $view->setPageTitle(\Lang::trans("switchAccount.title"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb(routePath("user-accounts"), \Lang::trans("switchAccount.title"));
        $sidebarName = "clientView";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        $requiredClientId = \WHMCS\Session::get("requiredClientId");
        $requiredClient = \Auth::user()->getClient($requiredClientId);
        return $view->setTemplate("user-switch-account-forced")->setTemplateVariables(["requiredClient" => $requiredClient]);
    }

    public function accountSwitch(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true, "user-switch-account");
        $clientId = $request->request()->get("id");
        try {
            \Auth::requireLogin();
            \Auth::setClientId($clientId);
            $returnUri = \WHMCS\Authentication\LoginHandler::getReturnUri();
            \WHMCS\Authentication\LoginHandler::clearReturnUri();
            if (empty($returnUri) || $returnUri === "clientarea.php") {
                return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php");
            }
            return new \WHMCS\Http\RedirectResponse($returnUri);
        } catch (\WHMCS\Exception\Authentication\LoginRequired $e) {
            return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php");
        } catch (\WHMCS\Exception\Authentication\InvalidClientStatus $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-accounts")))->withError(\Lang::trans("switchAccount.noLongerActive"));
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-accounts")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function password(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true);
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaPageChangePassword");
        $view->setPageTitle(\Lang::trans("clientareanavchangepw"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb(routePath("user-password"), \Lang::trans("clientareanavchangepw"));
        $sidebarName = "user";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        return $view->setTemplate("user-password");
    }

    public function savePassword(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true, "user-password");
        $existingPassword = $request->request()->get("existingpw");
        $newPassword = $request->request()->get("newpw");
        $confirmNewPassword = $request->request()->get("confirmpw");
        $newPassword = trim(\WHMCS\Input\Sanitize::decode($newPassword));
        $confirmNewPassword = trim(\WHMCS\Input\Sanitize::decode($confirmNewPassword));
        try {
            $user = \Auth::user();
            if (!$user->verifyPassword(\WHMCS\Input\Sanitize::decode($existingPassword))) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("existingpasswordincorrect"));
            }
            if (empty($newPassword)) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("ordererrorpassword"));
            }
            if (!(new \WHMCS\Validate())->validate("pwstrength", "newpw", "")) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("pwstrengthfail"));
            }
            if ($newPassword !== $confirmNewPassword) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("clientareaerrorpasswordnotmatch"));
            }
            $user->updatePassword($newPassword);
            \Auth::setSessionToken();
            logActivity("Password Change Request");
            return (new \WHMCS\Http\RedirectResponse(routePath("user-password")))->withSuccess(\Lang::trans("changessavedsuccessfully"));
        } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-password")))->withError($e->getMessage());
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-password")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function security(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true);
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaPageUserSecurity");
        $view->setPageTitle(\Lang::trans("clientareanavsecurity"));
        $view->setDisplayTitle(\Lang::trans("clientareanavsecurity"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb(routePath("user-security"), \Lang::trans("clientareanavsecurity"));
        $remoteAuthData = (new \WHMCS\Authentication\Remote\Management\Client\ViewHelper())->getTemplateData(\WHMCS\Authentication\Remote\Providers\AbstractRemoteAuthProvider::HTML_TARGET_CONNECT);
        $sidebarName = "user";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        $twoFactorAuthentication = new \WHMCS\TwoFactorAuthentication();
        return $view->setTemplate("user-security")->setTemplateVariables(["user" => \Auth::user(), "securityQuestions" => \WHMCS\User\User\SecurityQuestion::all(), "twoFactorAuthAvailable" => $twoFactorAuthentication->isActiveClients(), "twoFactorAuthEnabled" => \Auth::user()->hasTwoFactorAuthEnabled(), "twoFactorAuthRequired" => $twoFactorAuthentication->isForcedClients()])->setTemplateVariables($remoteAuthData);
    }

    public function saveSecurityQuestion(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true, "user-security");
        $currentAnswer = $request->request()->get("currentsecurityqans");
        $securityQuestionId = $request->request()->get("securityqid");
        $newAnswer1 = $request->request()->get("securityqans");
        $newAnswer2 = $request->request()->get("securityqans2");
        try {
            $user = \Auth::user();
            if ($user->hasSecurityQuestion() && !$user->verifySecurityQuestionAnswer($currentAnswer)) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("securitycurrentincorrect"));
            }
            if (empty($newAnswer1)) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("securityanswerrequired"));
            }
            if ($newAnswer1 !== $newAnswer2) {
                throw new \WHMCS\Exception\Validation\InvalidValue(\Lang::trans("securitybothnotmatch"));
            }
            $user->setSecurityQuestion($securityQuestionId, $newAnswer1);
            logActivity("Modified Security Question");
            return (new \WHMCS\Http\RedirectResponse(routePath("user-security")))->withSuccess(\Lang::trans("changessavedsuccessfully"));
        } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-security")))->withError($e->getMessage());
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("user-security")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function verification(\WHMCS\Http\Message\ServerRequest $request)
    {
        $token = $request->attributes()->get("token");
        $success = $expired = $invalid = false;
        try {
            $user = \WHMCS\User\User::emailVerificationToken($token)->first();
            if (!$user) {
                throw new \WHMCS\Exception\Validation\InvalidValue();
            }
            if ($user->getEmailVerificationTokenExpiry()->isPast()) {
                throw new \WHMCS\Exception\Validation\Expired();
            }
            $user->setEmailVerificationCompleted();
            $success = true;
        } catch (\WHMCS\Exception\Validation\Expired $e) {
            $expired = true;
        } catch (\Exception $e) {
            $invalid = true;
        }
        $view = new \WHMCS\ClientArea();
        $view->setPageTitle(\Lang::trans("emailVerification.title"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("#", \Lang::trans("emailVerification.title"));
        return $view->setTemplate("user-verify-email")->setTemplateVariables(["success" => $success, "expired" => $expired, "invalid" => $invalid, "showEmailVerificationBanner" => (bool) (!$success)]);
    }

    public function verificationResend(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            \Auth::requireLogin();
            \Auth::user()->sendEmailVerification();
            logActivity("Verification Email Resent");
            return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
        } catch (\WHMCS\Exception\Authentication\LoginRequired $e) {
            return new \WHMCS\Http\Message\JsonResponse(["unauthenticated" => true]);
        } catch (\Exception $e) {
            logActivity("Verification Email Resend Request Failed: " . $e->getMessage());
            return new \WHMCS\Http\Message\JsonResponse(["error" => true]);
        }
    }

    public function accessDenied(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLogin(true);
        $permission = $request->get("permission");
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaPageAccessDenied");
        $view->setPageTitle(\Lang::trans("accessdenied"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("#", \Lang::trans("accessdenied"));
        $sidebarName = "user";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        return $view->setTemplate("access-denied");
    }
}
