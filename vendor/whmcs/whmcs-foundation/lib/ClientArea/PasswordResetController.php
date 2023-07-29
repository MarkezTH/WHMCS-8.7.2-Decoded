<?php

namespace WHMCS\ClientArea;

class PasswordResetController
{
    private function initView()
    {
        $view = new \WHMCS\ClientArea();
        $view->setPageTitle(\Lang::trans("pwreset"));
        $view->addOutputHookFunction("ClientAreaPagePasswordReset");
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("clientarea.php", \Lang::trans("clientareatitle"));
        $view->addToBreadCrumb(routePath("password-reset-begin"), \Lang::trans("pwreset"));
        $view->assign("showingLoginPage", true);
        $view->assign("securityAnswer", NULL);
        $view->assign("successMessage", NULL);
        $view->assign("errorMessage", NULL);
        $view->assign("innerTemplate", NULL);
        $view->setTemplate("password-reset-container");
        return $view;
    }

    private function setInnerTemplate($template, \WHMCS\ClientArea $view)
    {
        $template = preg_replace("/[^a-z\\d\\-]+/", "", $template);
        $view->assign("innerTemplate", $template);
    }

    private function getUserFromKey($key = NULL)
    {
        if (!$key) {
            $key = $this->getStoredKey();
        }
        if (!$key) {
            return NULL;
        }
        return \WHMCS\User\User::resetToken($key)->first();
    }

    private function validateUser($user)
    {
        if (!$user) {
            throw new \WHMCS\Exception\Authentication\PasswordResetFailure(\Lang::trans("pwresetkeyinvalid"));
        }
        if (!$user->getResetTokenExpiry() || $user->getResetTokenExpiry()->isPast()) {
            throw new \WHMCS\Exception\Authentication\PasswordResetFailure(\Lang::trans("pwresetkeyexpired"));
        }
    }

    private function storeKey($key)
    {
        \WHMCS\Session::set("pw_reset_key", $key);
    }

    private function getStoredKey()
    {
        return \WHMCS\Session::get("pw_reset_key");
    }

    private function deleteKey()
    {
        \WHMCS\Session::delete("pw_reset_key");
    }

    private function validateUserSecurityAnswer($user, $answer)
    {
        $this->validateUser($user);
        if ($user->hasSecurityQuestion()) {
            if ($answer === "" || !$user->verifySecurityQuestionAnswer($answer)) {
                throw new \WHMCS\Exception\Authentication\PasswordResetFailure(\Lang::trans("pwresetsecurityquestionincorrect"));
            }
        } else {
            if ($answer !== "") {
                throw new \WHMCS\Exception\Authentication\PasswordResetFailure(\Lang::trans("pwresetsecurityquestionincorrect"));
            }
        }
    }

    public function emailPrompt(\WHMCS\Http\Message\ServerRequest $request)
    {
        $view = $this->initView();
        $view->assign("errorMessage", NULL);
        $captcha = new \WHMCS\Utility\Captcha();
        $templateData["captcha"] = $captcha;
        $templateData["captchaForm"] = \WHMCS\Utility\Captcha::FORM_LOGIN;
        $view->setTemplateVariables($templateData);
        $attributes = $request->getAttributes();
        if (isset($attributes["extraVars"])) {
            $view->setTemplateVariables($attributes["extraVars"]);
        }
        $this->setInnerTemplate("email-prompt", $view);
        return $view;
    }

    public function validateEmail(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token();
        try {
            $captcha = new \WHMCS\Utility\Captcha();
            if ($captcha->isEnabled()) {
                $validate = new \WHMCS\Validate();
                $captcha->validateAppropriateCaptcha(\WHMCS\Utility\Captcha::FORM_LOGIN, $validate);
                if ($validate->hasErrors()) {
                    throw new \WHMCS\Exception\Authentication\PasswordResetFailure(implode("\n", [$validate->getHTMLErrorOutput()]));
                }
                \WHMCS\Session::delete("CaptchaComplete");
            }
            $email = trim($request->get("email"));
            if (empty($email)) {
                throw new \WHMCS\Exception\Authentication\PasswordResetFailure(\Lang::trans("pwresetemailrequired"));
            }
            $user = \WHMCS\User\User::username($email)->first();
            if ($user) {
                try {
                    $user->sendPasswordResetEmail();
                } catch (\Exception $e) {
                    logActivity("Password Reset Request Failed: " . $e->getMessage(), 0, ["user" => $user, "addUserId" => $user->id, "requireIp" => true]);
                }
            }
            $view = $this->initView();
            $view->setTemplateVariables(["successTitle" => \Lang::trans("pwresetrequested"), "successMessage" => \Lang::trans("pwresetcheckemail")]);
            return $view;
        } catch (\WHMCS\Exception\Authentication\PasswordResetFailure $e) {
            return $this->emailPrompt($request->withAttribute("extraVars", ["errorMessage" => $e->getMessage()]));
        }
    }

    public function useKey(\WHMCS\Http\Message\ServerRequest $request)
    {
        $key = trim($request->get("key"));
        $routeName = "password-reset-begin";
        if (!empty($key)) {
            try {
                $user = $this->getUserFromKey($key);
                $this->validateUser($user);
                $this->storeKey($key);
                if ($user->hasSecurityQuestion()) {
                    $routeName = "password-reset-security-prompt";
                } else {
                    $routeName = "password-reset-change-prompt";
                }
            } catch (\WHMCS\Exception\Authentication\PasswordResetFailure $e) {
                $this->deleteKey();
            }
        }
        return new \Laminas\Diactoros\Response\RedirectResponse(routePath($routeName));
    }

    public function securityPrompt(\WHMCS\Http\Message\ServerRequest $request)
    {
        $user = $this->getUserFromKey();
        $view = $this->initView();
        try {
            $this->validateUser($user);
        } catch (\WHMCS\Exception\Authentication\PasswordResetFailure $e) {
            return $view->assign("errorMessage", $e->getMessage());
        }
        if (!$user->hasSecurityQuestion()) {
            return new \Laminas\Diactoros\Response\RedirectResponse("password-reset-change-prompt");
        }
        $view->assign("securityQuestion", $user->getSecurityQuestion());
        $this->setInnerTemplate("security-prompt", $view);
        return $view;
    }

    public function securityValidate(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token();
        $view = $this->initView();
        $user = $this->getUserFromKey();
        $this->setInnerTemplate("security-prompt", $view);
        if ($user && $user->hasSecurityQuestion()) {
            $view->assign("securityQuestion", $user->getSecurityQuestion());
        }
        $answer = $request->get("answer");
        $view->assign("errorMessage", NULL);
        try {
            $this->validateUserSecurityAnswer($user, $answer);
        } catch (\WHMCS\Exception\Authentication\PasswordResetFailure $e) {
            return $view->assign("errorMessage", $e->getMessage(), true);
        }
        $view->assign("securityAnswer", $answer);
        $this->setInnerTemplate("change-prompt", $view);
        return $view;
    }

    public function changePrompt(\WHMCS\Http\Message\ServerRequest $request)
    {
        $view = $this->initView();
        $user = $this->getUserFromKey();
        try {
            $this->validateUserSecurityAnswer($user, "");
        } catch (\WHMCS\Exception\Authentication\PasswordResetFailure $e) {
            return $view->assign("errorMessage", $e->getMessage());
        }
        $this->setInnerTemplate("change-prompt", $view);
        return $view;
    }

    public function changePerform(\WHMCS\Http\Message\ServerRequest $request)
    {
        check_token();
        $view = $this->initView();
        $user = $this->getUserFromKey();
        try {
            $this->validateUserSecurityAnswer($user, $request->get("answer"));
        } catch (\WHMCS\Exception\Authentication\PasswordResetFailure $e) {
            return $view->assign("errorMessage", $e->getMessage());
        }
        $validate = new \WHMCS\Validate();
        if ($validate->validate("required", "newpw", "ordererrorpassword") && $validate->validate("pwstrength", "newpw", "pwstrengthfail") && $validate->validate("required", "confirmpw", "clientareaerrorpasswordconfirm")) {
            $validate->validate("match_value", "newpw", "clientareaerrorpasswordnotmatch", "confirmpw");
        }
        $newPassword = \WHMCS\Input\Sanitize::decode(trim($request->get("newpw", "")));
        if ($newPassword !== "" && !$validate->hasErrors()) {
            $user->updatePassword($newPassword);
            logActivity("Password Reset Completed", 0, ["user" => $user, "addUserId" => $user->id, "requireIp" => true]);
            sendMessage("Password Reset Confirmation", $user->id);
            $this->deleteKey();
            \WHMCS\Session::delete("CaptchaComplete");
            try {
                \Auth::authenticate($user->email, $newPassword);
            } catch (\WHMCS\Exception\Authentication\RequiresSecondFactor $e) {
                \WHMCS\FlashMessages::add(\Lang::trans("pwresetvalidationsuccess"), "success");
                return new \Laminas\Diactoros\Response\RedirectResponse(routePath("login-two-factor-challenge"));
            }
            $assetHelper = \DI::make("asset");
            $view->setTemplateVariables(["successTitle" => \Lang::trans("pwresetvalidationsuccess"), "successMessage" => sprintf(\Lang::trans("pwresetsuccessdesc"), "<a href=\"" . $assetHelper->getWebRoot() . "/clientarea.php\">", "</a>")]);
            return $view;
        }
        $this->setInnerTemplate("change-prompt", $view);
        $view->assign("securityAnswer", $request->get("answer"));
        $view->assign("errorMessage", $validate->getHTMLErrorOutput());
        return $view;
    }
}
