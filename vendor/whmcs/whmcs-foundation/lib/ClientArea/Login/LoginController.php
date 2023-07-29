<?php

namespace WHMCS\ClientArea\Login;

class LoginController
{
    const TWO_FACTOR_REMEMBER_ME = "rememberMe2FA";

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        if (\Auth::user()) {
            return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php");
        }
        $view = new \WHMCS\ClientArea();
        $remoteAuthData = (new \WHMCS\Authentication\Remote\Management\Client\ViewHelper())->getTemplateData(\WHMCS\Authentication\Remote\Providers\AbstractRemoteAuthProvider::HTML_TARGET_LOGIN);
        foreach ($remoteAuthData as $key => $value) {
            $view->assign($key, $value);
        }
        if (\WHMCS\Session::getAndDelete("login_ssoredirect")) {
            \WHMCS\FlashMessages::add(\Lang::trans("sso.redirectafterlogin"), "info");
        } else {
            if (\WHMCS\Session::getAndDelete("CaptchaError")) {
                \WHMCS\FlashMessages::add(\Lang::trans("googleRecaptchaIncorrect"), "info");
            }
        }
        return $view->setTemplate("login")->setPageTitle(\Lang::trans("login"))->setTemplateVariables(["loginpage" => true, "showingLoginPage" => true, "formaction" => routePath("login-validate"), "captcha" => new \WHMCS\Utility\Captcha(), "captchaForm" => \WHMCS\Utility\Captcha::FORM_LOGIN]);
    }

    public function validateLogin(\WHMCS\Http\Message\ServerRequest $request)
    {
        if (!\DI::make("config")->allow_external_login_forms && !\WHMCS\TokenManager::init(\App::self())->isRequestTokenValid()) {
            \WHMCS\FlashMessages::add("Invalid CSRF Protection Token", "error");
            return $this->index($request);
        }
        $username = $request->request()->get("username");
        $password = $request->request()->get("password");
        $rememberMe = $request->request()->get("rememberme");
        $goto = $request->request()->get("goto");
        $username = trim($username);
        $password = \WHMCS\Input\Sanitize::decode(trim($password));
        if (\Auth::user()) {
            return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php");
        }
        $captcha = new \WHMCS\Utility\Captcha();
        if (!\WHMCS\Authentication\LoginHandler::isOauthLoginRequest(true) && $captcha->isEnabled() && $captcha->isEnabledForForm(\WHMCS\Utility\Captcha::FORM_LOGIN)) {
            $validate = new \WHMCS\Validate();
            $captcha->validateAppropriateCaptcha(\WHMCS\Utility\Captcha::FORM_LOGIN, $validate);
            if ($validate->hasErrors()) {
                return (new \WHMCS\Http\RedirectResponse(routePath("login-index")))->withError($validate->getErrors()[0]);
            }
        }
        $loginHandler = new \WHMCS\Authentication\LoginHandler();
        if ($goto) {
            \WHMCS\Authentication\LoginHandler::validateAndSaveGotoRequest($goto);
        }
        $returnUri = \WHMCS\Authentication\LoginHandler::getReturnUri();
        if (empty($returnUri)) {
            $returnUri = "clientarea.php";
        }
        try {
            \Auth::authenticate($username, $password);
            if ($rememberMe) {
                \Auth::setRememberCookie();
            }
            if (\Auth::hasMultipleClients()) {
                return new \WHMCS\Http\RedirectResponse(routePath("user-accounts"));
            }
            \WHMCS\Authentication\LoginHandler::clearReturnUri();
            return new \WHMCS\Http\RedirectResponse($returnUri);
        } catch (\WHMCS\Exception\Authentication\RequiresSecondFactor $e) {
            \WHMCS\Session::delete(self::TWO_FACTOR_REMEMBER_ME);
            if ($rememberMe) {
                \WHMCS\Session::set(self::TWO_FACTOR_REMEMBER_ME, true);
            }
            return new \WHMCS\Http\RedirectResponse(routePath("login-two-factor-challenge"));
        } catch (\Exception $e) {
        }
        $loginShareClient = $loginHandler->dispatchLoginShareHooks($username, $password);
        if ($loginShareClient) {
            try {
                \Auth::login($loginShareClient->owner());
                \Auth::setClientId($loginShareClient->id);
                return new \WHMCS\Http\RedirectResponse($returnUri);
            } catch (\Exception $e) {
            }
        }
        return (new \WHMCS\Http\RedirectResponse(routePath("login-index")))->withError(\Lang::trans("loginincorrect"));
    }

    public function twoFactorChallenge(\WHMCS\Http\Message\ServerRequest $request)
    {
        $user = \Auth::twoFactorChallengeUser();
        if (!$user || !$user->second_factor) {
            return new \WHMCS\Http\RedirectResponse(routePath("login-index"));
        }
        $incorrect = (bool) (int) $request->query()->get("incorrect");
        $usingBackup = (bool) (int) $request->query()->get("backup");
        $config = $user->getSecondFactorConfig();
        $error = "";
        $remainingAttempts = 0;
        if ($incorrect && $usingBackup) {
            $remainingAttempts = \WHMCS\User\User::TWOFA_BACKUP_ATTEMPTS - $config["backup_attempts"];
        } else {
            if ($incorrect && !$usingBackup) {
                $remainingAttempts = \WHMCS\User\User::TWOFA_ATTEMPTS - $config["attempts"];
            }
        }
        if ($incorrect && $remainingAttempts) {
            $error = \Lang::trans("twofa2ndfactorincorrect", [":attempts" => $remainingAttempts]);
        }
        return (new \WHMCS\ClientArea())->setTemplate("two-factor-challenge")->setPageTitle(\Lang::trans("clientlogin"))->setTemplateVariables(["showingLoginPage" => true, "challenge" => (new \WHMCS\TwoFactorAuthentication())->setUser($user)->generateChallenge(), "error" => $error, "usingBackup" => $usingBackup]);
    }

    public function twoFactorChallengeVerify(\WHMCS\Http\Message\ServerRequest $request)
    {
        $user = \Auth::twoFactorChallengeUser();
        if (!$user || !$user->second_factor) {
            return new \WHMCS\Http\RedirectResponse(routePath("login-index"));
        }
        $returnUri = \WHMCS\Authentication\LoginHandler::getReturnUri();
        if (empty($returnUri)) {
            $returnUri = routePath("clientarea-home");
        }
        try {
            \Auth::verifySecondFactor();
            if (\WHMCS\Session::getAndDelete(self::TWO_FACTOR_REMEMBER_ME)) {
                \Auth::setRememberCookie();
            }
            if (isset($_SESSION["2fafromcart"])) {
                unset($_SESSION["2fafromcart"]);
                return new \WHMCS\Http\RedirectResponse(\App::getSystemURL() . "cart.php?a=checkout");
            }
            if (\Auth::hasMultipleClients()) {
                return new \WHMCS\Http\RedirectResponse(routePath("user-accounts"));
            }
            \WHMCS\Authentication\LoginHandler::clearReturnUri();
            return new \WHMCS\Http\RedirectResponse($returnUri);
        } catch (\WHMCS\Exception\Authentication\InvalidSecondFactor $e) {
            return new \WHMCS\Http\RedirectResponse(routePathWithQuery("login-two-factor-challenge", [], ["incorrect" => "1"]));
        }
    }

    public function twoFactorBackupCodeVerify(\WHMCS\Http\Message\ServerRequest $request)
    {
        $user = \Auth::twoFactorChallengeUser();
        if (!$user || !$user->second_factor) {
            return new \WHMCS\Http\RedirectResponse(routePath("login-index"));
        }
        try {
            $twoFactorBackupCode = $request->request()->get("twofabackupcode");
            \Auth::verifySecondFactorBackupCode($twoFactorBackupCode);
            \WHMCS\Session::set("twoFactorRedeemNewBackupCode", "1");
            return new \WHMCS\Http\RedirectResponse(routePath("login-two-factor-challenge-backup-new"));
        } catch (\WHMCS\Exception\Authentication\InvalidSecondFactor $e) {
            return new \WHMCS\Http\RedirectResponse(routePathWithQuery("login-two-factor-challenge", [], ["incorrect" => "1", "backup" => "1"]));
        }
    }

    public function twoFactorBackupCodeNew(\WHMCS\Http\Message\ServerRequest $request)
    {
        if (!\WHMCS\Session::exists("twoFactorRedeemNewBackupCode")) {
            return new \WHMCS\Http\RedirectResponse(routePath("login-index"));
        }
        \WHMCS\Session::delete("twoFactorRedeemNewBackupCode");
        $user = \Auth::user();
        if (!$user) {
            return new \WHMCS\Http\RedirectResponse(routePath("login-index"));
        }
        return (new \WHMCS\ClientArea())->setTemplate("two-factor-new-backup-code")->setPageTitle(\Lang::trans("clientlogin"))->setTemplateVariables(["showingLoginPage" => true, "newBackupCode" => (new \WHMCS\TwoFactorAuthentication())->setUser($user)->generateNewBackupCode()]);
    }

    public function cartLogin(\WHMCS\Http\Message\ServerRequest $request)
    {
        $loginEmail = $request->request()->get("username");
        $loginPassword = \WHMCS\Input\Sanitize::decode($request->request()->get("password"));
        try {
            \Auth::authenticate($loginEmail, $loginPassword);
            $response = ["success" => true];
        } catch (\WHMCS\Exception\Authentication\RequiresSecondFactor $e) {
            \WHMCS\Session::set("2fafromcart", true);
            $response = ["success" => true, "redirectUrl" => routePath("login-two-factor-challenge")];
        } catch (\Exception $e) {
            $response = ["error" => \Lang::trans("loginincorrect")];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }
}
