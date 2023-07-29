<?php

namespace WHMCS\Authentication;

class AuthManager
{
    protected $user = NULL;
    protected $client = NULL;
    protected $loggedOut = false;
    const TOKEN_NAME = "login_auth_tk";
    const SESSION_CLIENTID_NAME = "uid";
    const SESSION_TWOFACTOR_CLIENTID_NAME = "login_tfid";

    public function user()
    {
        if ($this->loggedOut) {
            return NULL;
        }
        if (!is_null($this->user)) {
            return $this->user;
        }
        $token = $this->getSessionToken();
        if ($token) {
            $this->user = $this->retrieveUserByToken($token);
        }
        if (is_null($this->user)) {
            $token = $this->getCookieToken();
            $user = $this->retrieveUserByToken($token);
            if ($user) {
                $this->login($user);
            }
        }
        return $this->user;
    }

    public function client()
    {
        if (!$this->user()) {
            return NULL;
        }
        if ($this->client) {
            return $this->client;
        }
        $clientId = \WHMCS\Session::get(self::SESSION_CLIENTID_NAME);
        if ($clientId) {
            try {
                $this->client = $this->user->getClient($clientId);
            } catch (\Exception $e) {
            }
        }
        return $this->client;
    }

    public function assertClient($Client, $exceptionMessage)
    {
        if ($exceptionMessage === "") {
            $exceptionMessage = "A logged in client is required";
        }
        $client = $this->client();
        if (!$client) {
            throw new \WHMCS\Exception\User\ClientRequiredException($exceptionMessage);
        }
        return $client;
    }

    public function hasMultipleClients()
    {
        if (!$this->user()) {
            return false;
        }
        return 1 < $this->user->getNumberOfClients();
    }

    public function permissions()
    {
        $this->user();
        if ($this->client()) {
            if ($this->client->authedUserIsOwner()) {
                return \WHMCS\User\Permissions::all();
            }
            return $this->client->pivot->getPermissions();
        }
        return \WHMCS\User\Permissions::none();
    }

    public function hasPermission($permission)
    {
        return $this->permissions()->hasPermission($permission);
    }

    public function authenticate($username, $password)
    {
        $user = $this->retrieveUserByUsername($username);
        if (!$user) {
            throw new \WHMCS\Exception\Authentication\UsernameNotFound();
        }
        if (!$user->verifyPassword($password)) {
            throw new \WHMCS\Exception\Authentication\InvalidPassword();
        }
        if ($user->hasTwoFactorAuthEnabled()) {
            $twoFa = new \WHMCS\TwoFactorAuthentication();
            if ($twoFa->isModuleEnabledForClients($user->getSecondFactorModule())) {
                $this->requireTwoFactorChallenge($user);
            }
        }
        return $this->login($user);
    }

    public function adminMasquerade(\WHMCS\User\User $user, \WHMCS\User\Client $client)
    {
        if (!\WHMCS\User\Admin::getAuthenticatedUser()) {
            return NULL;
        }
        $this->user = $user;
        $this->loggedOut = false;
        $this->setSessionToken(false);
        $this->setClientId($client->id, true);
    }

    public function endAdminMasquerade()
    {
        if (!\WHMCS\User\Admin::getAuthenticatedUser()) {
            return NULL;
        }
        $this->clearUserDataFromStorage();
        $this->user = NULL;
        $this->client = NULL;
        $this->loggedOut = true;
    }

    public function verifySecondFactor()
    {
        $user = $this->twoFactorChallengeUser();
        if ($user) {
            $validChallengeResponse = (new \WHMCS\TwoFactorAuthentication())->setUser($user)->validateChallenge();
            $config = $user->getSecondFactorConfig();
            if ($validChallengeResponse) {
                unset($config["attempts"]);
                $user->setSecondFactorConfig($config);
                return $this->login($user);
            }
            if (empty($config["attempts"])) {
                $config["attempts"] = 0;
            }
            if (empty($config["attempts"])) {
                $config["attempts"] = 0;
            }
            $config["attempts"] += 1;
            $user->setSecondFactorConfig($config)->save();
            if (\WHMCS\User\User::TWOFA_ATTEMPTS <= $config["attempts"]) {
                $user->banIpAddress();
            }
        }
        throw new \WHMCS\Exception\Authentication\InvalidSecondFactor();
    }

    public function verifySecondFactorBackupCode($backupCode)
    {
        $user = $this->twoFactorChallengeUser();
        if ($user) {
            $config = $user->getSecondFactorConfig();
            $backupResponse = (new \WHMCS\TwoFactorAuthentication())->setUser($user)->verifyBackupCode($backupCode);
            if ($backupResponse) {
                unset($config["backup_attempts"]);
                $user->setSecondFactorConfig($config);
                return $this->login($user);
            }
            if (empty($config["backup_attempts"])) {
                $config["backup_attempts"] = 0;
            }
            if (empty($config["backup_attempts"])) {
                $config["backup_attempts"] = 0;
            }
            $config["backup_attempts"] += 1;
            $user->setSecondFactorConfig($config)->save();
            if (\WHMCS\User\User::TWOFA_BACKUP_ATTEMPTS <= $config["backup_attempts"]) {
                $user->banIpAddress();
            }
        }
        throw new \WHMCS\Exception\Authentication\InvalidSecondFactor();
    }

    public function twoFactorChallengeUser()
    {
        $userId = \WHMCS\Session::get(self::SESSION_TWOFACTOR_CLIENTID_NAME);
        if (0 < $userId) {
            return \WHMCS\User\User::find($userId);
        }
        return NULL;
    }

    public function attemptRemoteAuthLogin(Remote\AccountLink $accountLink)
    {
        $user = $accountLink->user;
        if ($user->hasTwoFactorAuthEnabled()) {
            $this->requireTwoFactorChallenge($user);
        }
        $this->login($user);
    }

    public function setClientId($clientId, $isAdminMasquerade = false)
    {
        $client = $this->user()->getClient($clientId);
        if ($client->status == \WHMCS\Utility\Status::CLOSED) {
            throw new \WHMCS\Exception\Authentication\InvalidClientStatus();
        }
        $this->client = $client;
        if (!$isAdminMasquerade) {
            $client->updateLastLogin();
            $relation = $client->pivot;
            $relation->updateLastLogin();
            $relation->save();
        }
        \WHMCS\Session::set(self::SESSION_CLIENTID_NAME, $client->id);
        $client->runPostLoginEvents();
    }

    public function logout()
    {
        $user = $this->user();
        if (!$user) {
            return NULL;
        }
        $this->clearUserDataFromStorage();
        $this->user = NULL;
        $this->client = NULL;
        $this->loggedOut = true;
        $user->runPostLogoutEvents();
    }

    public function requireLogin($autoRedirectToLoginPage = false, $routePath = NULL)
    {
        if ($this->user()) {
            return NULL;
        }
        if (!$autoRedirectToLoginPage) {
            throw new \WHMCS\Exception\Authentication\LoginRequired();
        }
        if ($routePath) {
            LoginHandler::setReturnUri(routePath($routePath));
        } else {
            LoginHandler::autoReturnUri();
        }
        LoginHandler::captureRequestParams();
        \App::redirectToRoutePath("login-index");
    }

    public function requireLoginAndClient($autoRedirectToLoginPage = false, $routePath = NULL)
    {
        $this->requireLogin($autoRedirectToLoginPage, $routePath);
        if ($this->client()) {
            return NULL;
        }
        if (!$autoRedirectToLoginPage) {
            throw new \WHMCS\Exception\Authentication\ClientRequired();
        }
        if ($routePath) {
            LoginHandler::setReturnUri(routePath($routePath));
        } else {
            LoginHandler::autoReturnUri();
        }
        \App::redirectToRoutePath("user-accounts");
    }

    public function forceSwitchClientIdOrFail($requiredClientId)
    {
        if ($this->client()->id == $requiredClientId) {
            return NULL;
        }
        $this->user->getClient($requiredClientId);
        \WHMCS\Session::set("requiredClientId", $requiredClientId);
        LoginHandler::autoReturnUri();
        \App::redirectToRoutePath("user-account-switch-forced");
    }

    public function setRememberCookie()
    {
        if (!$this->user) {
            return NULL;
        }
        \WHMCS\Cookie::set(self::TOKEN_NAME, base64_encode(encrypt($this->user()->cookieToken()->generate())), \WHMCS\Carbon::now()->addYear()->getTimestamp());
    }

    public function register($User, $firstName, $lastName, $email, $password = "", $language = false, $skipEmailVerification = 0, int $securityQuestion = "", $securityAnswer)
    {
        $user = \WHMCS\User\User::createUser($firstName, $lastName, $email, $password, $language, $skipEmailVerification);
        if (!empty($securityQuestion)) {
            try {
                $user->setSecurityQuestion($securityQuestion, $securityAnswer);
            } catch (\Throwable $e) {
            }
        }
        return $user;
    }

    public function registerAndLogin($User, $firstName, $lastName, $email, $password = "", $language = 0, int $securityQuestion = "", $securityAnswer)
    {
        $user = $this->register($firstName, $lastName, $email, $password, $language, false, $securityQuestion, $securityAnswer);
        $this->login($user);
        return $user;
    }

    protected function retrieveUserByUsername($username)
    {
        $user = \WHMCS\User\User::username($username)->first();
        if ($user) {
            return $user;
        }
        return NULL;
    }

    protected function retrieveUserByToken($token)
    {
        if ($token->validFormat()) {
            $user = \WHMCS\User\User::find($token->id());
            if ($user && $token->validateUser($user, $this->validateIp())) {
                return $user;
            }
        }
    }

    protected function requireTwoFactorChallenge(\WHMCS\User\User $user)
    {
        \WHMCS\Session::set(self::SESSION_TWOFACTOR_CLIENTID_NAME, $user->id);
        throw \WHMCS\Exception\Authentication\RequiresSecondFactor::createForUser($user);
    }

    public function setUser($user)
    {
        $this->user = $user;
        $this->loggedOut = false;
        if ($this->user->getNumberOfClients() === 1) {
            try {
                $this->setClientId($this->user->getClientIds()[0]);
            } catch (\Exception $e) {
            }
        }
    }

    public function login(\WHMCS\User\User $user)
    {
        $this->setUser($user);
        $this->user->updateLastLogin()->save();
        $this->setSessionToken();
        $user->runPostLoginEvents();
        return $this;
    }

    public function setSessionToken($rotateSession = true)
    {
        if (!$this->user) {
            return false;
        }
        if ($rotateSession) {
            \WHMCS\Session::rotate();
        }
        \WHMCS\Session::set(self::TOKEN_NAME, $this->user->sessionToken()->generate());
        return true;
    }

    protected function getSessionToken()
    {
        $token = \WHMCS\Session::get(self::TOKEN_NAME);
        return new SessionToken($token);
    }

    protected function getCookieToken()
    {
        $token = \WHMCS\Cookie::get(self::TOKEN_NAME);
        $token = base64_decode($token);
        $token = decrypt($token);
        return new CookieToken($token);
    }

    protected function validateIp()
    {
        return !\WHMCS\Config\Setting::getValue("DisableSessionIPCheck");
    }

    protected function clearUserDataFromStorage()
    {
        \WHMCS\Session::delete(self::TOKEN_NAME);
        \WHMCS\Session::delete(self::SESSION_CLIENTID_NAME);
        \WHMCS\Session::delete(self::SESSION_TWOFACTOR_CLIENTID_NAME);
        \WHMCS\Cookie::delete(self::TOKEN_NAME);
    }
}
