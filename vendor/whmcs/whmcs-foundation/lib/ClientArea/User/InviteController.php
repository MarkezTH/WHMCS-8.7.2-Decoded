<?php

namespace WHMCS\ClientArea\User;

class InviteController
{
    public function redeem(\WHMCS\Http\Message\ServerRequest $request)
    {
        $token = $request->attributes()->get("token");
        $invite = \WHMCS\User\User\UserInvite::pending()->token($token)->first();
        if ($invite && $invite->isExpired()) {
            $invite = NULL;
        }
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaUserInvite");
        $view->setPageTitle(\Lang::trans("accountInvite.title"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("#", \Lang::trans("accountInvite.title"));
        if (!\Auth::user()) {
            \WHMCS\Authentication\LoginHandler::autoReturnUri();
        }
        return $view->setTemplate("user-invite-accept")->setTemplateVariables(["invite" => $invite, "formdata" => ["firstname" => getLastInput("firstname"), "lastname" => getLastInput("lastname"), "email" => getLastInput("email") ?: $invite->email, "password" => getLastInput("password")], "captcha" => new \WHMCS\Utility\Captcha(), "captchaForm" => \WHMCS\Utility\Captcha::FORM_LOGIN, "captchaFormRegister" => \WHMCS\Utility\Captcha::FORM_REGISTRATION, "accept_tos" => \WHMCS\Config\Setting::getValue("EnableTOSAccept"), "tos_url" => \WHMCS\Config\Setting::getValue("TermsOfService")]);
    }

    public function validate(\WHMCS\Http\Message\ServerRequest $request)
    {
        $token = $request->attributes()->get("token");
        $firstName = $request->request()->get("firstname", "");
        $lastName = $request->request()->get("lastname", "");
        $email = $request->request()->get("email", "");
        $password = $request->request()->get("password", "");
        $email = trim($email);
        $password = \WHMCS\Input\Sanitize::decode(trim($password));
        $newRegistration = false;
        try {
            $invite = \WHMCS\User\User\UserInvite::token($token)->first();
            if (!$invite) {
                throw new \WHMCS\Exception("Invite not found");
            }
            if (!$invite->isPending()) {
                throw new \WHMCS\Exception("Invite not in a valid state");
            }
            $userIds = $invite->client()->first()->users()->pluck("tblusers.id");
            if (\Auth::user()) {
                if ($userIds->contains(\Auth::user()->id)) {
                    throw new \WHMCS\Exception\Validation\DuplicateValue();
                }
            } else {
                $validate = new \WHMCS\Validate();
                $captcha = new \WHMCS\Utility\Captcha();
                if ($captcha->isEnabled() && $captcha->isEnabledForForm(\WHMCS\Utility\Captcha::FORM_REGISTRATION)) {
                    $captcha->validateAppropriateCaptcha(\WHMCS\Utility\Captcha::FORM_REGISTRATION, $validate);
                    if ($validate->hasErrors()) {
                        throw new \WHMCS\Exception\Validation\Required(\Lang::trans("googleRecaptchaIncorrect"));
                    }
                }
                if ($password && $validate->calcPasswordStrength($password) < \WHMCS\Config\Setting::getValue("RequiredPWStrength")) {
                    throw new \WHMCS\Exception\Validation\Required(\Lang::trans("pwstrengthfail"));
                }
                if (\WHMCS\Config\Setting::getValue("EnableTOSAccept") && !$validate->validate("required", "accept")) {
                    throw new \WHMCS\Exception\Validation\Required(\Lang::trans("ordererroraccepttos"));
                }
                if (!$validate->validate("banneddomain", "email", "clientareaerrorbannedemail", "", $email)) {
                    throw new \WHMCS\Exception\User\BannedEmail();
                }
                $user = \Auth::register($firstName, $lastName, $email, $password, \Lang::getName(), true, true);
                \Auth::login($user);
            }
            $invite->accept(\Auth::user());
            if ($newRegistration && \Auth::user()->needsToCompleteEmailVerification()) {
                \Auth::user()->sendEmailVerification();
            }
            \Auth::setClientId($invite->clientId);
            return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php?inviteaccepted=1");
        } catch (\WHMCS\Exception\Validation\DuplicateValue $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("invite-redeem", $invite->token)))->withError(\Lang::trans("accountInvite.userAlreadyAssociated"));
        } catch (\WHMCS\Exception\Validation\Required $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("invite-redeem", $invite->token)))->withError($e->getMessage())->withInput();
        } catch (\WHMCS\Exception\User\EmailAlreadyExists $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("invite-redeem", $invite->token)))->withError(\Lang::trans("accountInvite.emailAlreadyExists"))->withInput();
        } catch (\WHMCS\Exception\User\BannedEmail $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("invite-redeem", $invite->token)))->withWarning(\Lang::trans("clientareaerrorbannedemail"))->withInput();
        } catch (\Exception $e) {
            if ($invite) {
                $redirectRoute = routePath("invite-redeem", $invite->token);
            } else {
                $redirectRoute = routePath("index");
            }
            return (new \WHMCS\Http\RedirectResponse($redirectRoute))->withError(\Lang::trans("errorButTryAgain"))->withInput();
        }
    }
}
