<?php

namespace WHMCS;

class Auth
{
    private $inputusername = "";
    private $admindata = [];
    private $logincookie = "";
    private $hasPasswordHashField = true;

    private function getInfo($where, $resource = NULL, $restrictToEnabled = true)
    {
        if ($restrictToEnabled) {
            $where["disabled"] = "0";
        }
        $passwordHashField = "passwordhash,";
        $installedVersion = \DI::make("app")->getDBVersion();
        $lasVersionWithoutHashField = new Version\SemanticVersion("5.3.8-release.1");
        $schemaIsSane = Version\SemanticVersion::compare($installedVersion, $lasVersionWithoutHashField, ">");
        if (!$schemaIsSane) {
            $this->hasPasswordHashField = false;
            $passwordHashField = "";
        }
        $result = select_query("tbladmins", "id,roleid,username,password,email," . $passwordHashField . "template,language,authmodule,loginattempts,disabled", $where, "", "", "", "", $resource);
        $data = mysql_fetch_assoc($result);
        $this->admindata = $data;
        return is_array($this->admindata) && !empty($this->admindata["id"]) ? true : false;
    }

    public function getInfobyID($adminid, $resource = NULL, $restrictToEnabled = true)
    {
        if (!is_numeric($adminid)) {
            return false;
        }
        return $this->getInfo(["id" => (int) $adminid], $resource, $restrictToEnabled);
    }

    public function getInfobyUsername($username, $restrictToEnabled = true)
    {
        $this->inputusername = $username;
        return $this->getInfo(["username" => $username], NULL, $restrictToEnabled);
    }

    public function comparePasswordInputWithHook($userInput, $isApi = false)
    {
        $hookName = $isApi ? "AuthAdminApi" : "AuthAdmin";
        $admin = User\Admin::find($this->getAdminID());
        try {
            $hookMgr = \DI::make("HookManager");
            if ($isApi) {
                $hookResults = run_hook("AuthAdminApi", [$userInput, $admin], true);
                $expectedResults = count($hookMgr->getRegistered($hookName));
            } else {
                $hookResults = run_hook("AuthAdmin", [$userInput, $admin], true);
                $expectedResults = count($hookMgr->getRegistered($hookName));
            }
            if (count($hookResults) < $expectedResults) {
                return false;
            }
            if ($expectedResults < 1) {
                return false;
            }
            $oneHookResponseTrue = NULL;
            $oneHookResponseFalse = NULL;
            foreach ($hookResults as $result) {
                if ($result && is_null($oneHookResponseTrue)) {
                    $oneHookResponseTrue = true;
                } else {
                    if (!$result && is_null($oneHookResponseFalse)) {
                        $oneHookResponseFalse = true;
                    }
                }
            }
            if ($oneHookResponseTrue && is_null($oneHookResponseFalse)) {
                $result = true;
            } else {
                $result = false;
            }
        } catch (\Exception $e) {
            $result = false;
            logActivity($hookName . " Hook Exception: " . $e->getMessage());
        }
        return $result;
    }

    public function comparePassword($password)
    {
        $hookMgr = \DI::make("HookManager");
        $adminLoginHooks = $hookMgr->getRegistered("AuthAdmin");
        if (!empty($adminLoginHooks)) {
            return $this->comparePasswordInputWithHook($password, false);
        }
        $result = false;
        $password = trim($password);
        if ($password) {
            $hasher = new Security\Hash\Password();
            if ($this->isAdminPWHashSet()) {
                $storedSecret = $this->getAdminPWHash();
            } else {
                $storedSecret = $this->getLegacyAdminPW();
                $storedSecretInfo = $hasher->getInfo($storedSecret);
                if ($storedSecretInfo["algoName"] != Security\Hash\Password::HASH_MD5 && $storedSecretInfo["algoName"] != Security\Hash\Password::HASH_UNKNOWN) {
                    $password = md5($password);
                }
            }
            try {
                $result = $hasher->verify($password, $storedSecret);
            } catch (Exception $e) {
                logActivity("Failed to verify admin password hash: " . $e->getMessage());
            }
        }
        return $result;
    }

    public function compareApiPassword($password)
    {
        $hookMgr = \DI::make("HookManager");
        $adminLoginHooks = $hookMgr->getRegistered("AuthAdminApi");
        if (!empty($adminLoginHooks)) {
            return $this->comparePasswordInputWithHook($password, true);
        }
        $result = false;
        $password = trim($password);
        $storedHash = $this->getLegacyAdminPW();
        if ($password && $storedHash) {
            $hasher = new Security\Hash\Password();
            try {
                $info = $hasher->getInfo($storedHash);
                if ($info["algoName"] == Security\Hash\Password::HASH_MD5) {
                    $result = $hasher->assertBinarySameness($password, $this->getLegacyAdminPW());
                } else {
                    if ($info["algoName"] != Security\Hash\Password::HASH_UNKNOWN) {
                        $result = $hasher->verify($password, $storedHash);
                    }
                }
            } catch (Exception $e) {
                logActivity("Failed to verify API password hash: " . $e->getMessage());
            }
        }
        return $result;
    }

    public function isTwoFactor()
    {
        return $this->admindata["authmodule"] ? true : false;
    }

    public function getAdminID()
    {
        return $this->admindata["id"];
    }

    public function getAdminRoleId()
    {
        return (int) $this->admindata["roleid"];
    }

    public function getAdminUsername()
    {
        return $this->admindata["username"];
    }

    public function getAdminEmail()
    {
        return $this->admindata["email"];
    }

    public function getLegacyAdminPW()
    {
        return !empty($this->admindata["password"]) ? $this->admindata["password"] : "";
    }

    public function getAdminPWHash()
    {
        return !empty($this->admindata["passwordhash"]) ? $this->admindata["passwordhash"] : "";
    }

    public function isAdminPWHashSet()
    {
        $passwordHash = $this->getAdminPWHash();
        return empty($passwordHash) ? false : true;
    }

    public function generateNewPasswordHashAndStore($password)
    {
        $hasher = new Security\Hash\Password();
        $result = false;
        if ($this->hasPasswordHashField) {
            try {
                $hashedSecret = $hasher->hash($password);
                $result = update_query("tbladmins", ["passwordhash" => $hashedSecret], ["id" => $this->getAdminID()]);
                if ($result !== false) {
                    $this->admindata["passwordhash"] = $hashedSecret;
                }
            } catch (Exception $e) {
                logActivity("Failed to rehash admin password: " . $e->getMessage());
            }
        }
        return $result;
    }

    public function generateNewPasswordHashAndStoreForApi($password)
    {
        $hasher = new Security\Hash\Password();
        $result = false;
        if ($this->hasPasswordHashField) {
            try {
                $hashedSecret = $hasher->hash($password);
                $result = update_query("tbladmins", ["password" => $hashedSecret], ["id" => $this->getAdminID()]);
                if ($result !== false) {
                    $this->admindata["password"] = $hashedSecret;
                }
            } catch (Exception $e) {
                logActivity("Failed to rehash admin password: " . $e->getMessage());
            }
        }
        return $result;
    }

    public function getAdminTemplate()
    {
        return $this->admindata["template"];
    }

    public function getAdminLanguage()
    {
        return $this->admindata["language"];
    }

    public function getAdmin2FAModule()
    {
        return $this->admindata["authmodule"];
    }

    private function getAdminUserAgent()
    {
        return array_key_exists("HTTP_USER_AGENT", $_SERVER) ? $_SERVER["HTTP_USER_AGENT"] : "";
    }

    public function isActive()
    {
        return $this->admindata["disabled"] != 1;
    }

    public function generateAdminSessionHash($whmcsclass = false)
    {
        $whmcs = \DI::make("app");
        if ($whmcsclass) {
            $haship = $whmcsclass->get_config("DisableSessionIPCheck") ? "" : Utility\Environment\CurrentRequest::getIP();
            $cchash = $whmcsclass->get_hash();
        } else {
            $haship = $whmcs->get_config("DisableSessionIPCheck") ? "" : Utility\Environment\CurrentRequest::getIP();
            $cchash = $whmcs->get_hash();
        }
        $separator = "|";
        $hash = sha1($this->getAdminID() . $separator . $this->getAdminEmail() . $separator . $this->getAdminUserAgent() . $separator . $this->getAdminPWHash() . $separator . $haship . $separator . substr(sha1($cchash), 20));
        return $hash;
    }

    public function setSessionVars($whmcsclass = false)
    {
        $_SESSION["adminid"] = $this->getAdminID();
        $_SESSION["adminpw"] = $this->generateAdminSessionHash($whmcsclass);
        conditionally_set_token(genRandomVal());
    }

    public function processLogin($createAdminLogEntry = true)
    {
        $whmcs = \App::self();
        if ($createAdminLogEntry) {
            insert_query("tbladminlog", ["adminusername" => $this->getAdminUsername(), "logintime" => "now()", "lastvisit" => "now()", "ipaddress" => Utility\Environment\CurrentRequest::getIP(), "sessionid" => session_id()]);
        }
        update_query("tbladmins", ["loginattempts" => "0"], ["username" => $this->getAdminUsername()]);
        $resetTokenId = get_query_val("tbltransientdata", "id", ["data" => json_encode(["id" => $this->getAdminID(), "email" => $this->getAdminEmail()])]);
        if ($resetTokenId) {
            delete_query("tbltransientdata", ["id" => $resetTokenId]);
        }
        run_hook("AdminLogin", ["adminid" => $this->getAdminID(), "username" => $this->getAdminUsername()]);
    }

    public function getRememberMeCookie()
    {
        $remcookie = Cookie::get("AU");
        if (!$remcookie) {
            $remcookie = Cookie::get("AUser");
        }
        return $remcookie;
    }

    public function isValidRememberMeCookie($whmcsclass = false)
    {
        $whmcs = \DI::make("app");
        $cookiedata = $this->getRememberMeCookie();
        if ($cookiedata) {
            $cookiedata = explode(":", $cookiedata);
            $resource = $whmcsclass !== false ? $whmcsclass->getDatabaseObj()->getConnection() : $whmcs->getDatabaseObj()->getConnection();
            if ($this->getInfobyID($cookiedata[0], $resource)) {
                if ($whmcsclass) {
                    $hash = $whmcsclass->get_hash();
                } else {
                    $hash = $whmcs->get_hash();
                }
                $cookiehashcompare = sha1($this->generateAdminSessionHash($whmcsclass) . $hash);
                if ($cookiedata[1] == $cookiehashcompare && $this->isAdminPWHashSet()) {
                    return true;
                }
            }
        }
        return false;
    }

    public function setRememberMeCookie()
    {
        $whmcs = \DI::make("app");
        Cookie::set("AU", $this->getAdminID() . ":" . sha1($_SESSION["adminpw"] . $whmcs->get_hash()), "12m");
    }

    public function unsetRememberMeCookie()
    {
        Cookie::delete("AU");
    }

    private function getWhiteListedIPs()
    {
        $whmcs = \DI::make("app");
        $ips = [];
        $whitelistedips = (array) safe_unserialize($whmcs->get_config("WhitelistedIPs"));
        foreach ($whitelistedips as $whitelisted) {
            if (is_array($whitelisted) && !empty($whitelisted["ip"])) {
                $ips[] = $whitelisted["ip"];
            }
        }
        return $ips;
    }

    private function isWhitelistedIP($ip)
    {
        $whitelistedips = $this->getWhiteListedIPs();
        if (in_array($ip, $whitelistedips)) {
            return true;
        }
        $ipparts = explode(".", $ip);
        if (3 <= count($ipparts)) {
            $ip = $ipparts[0] . "." . $ipparts[1] . "." . $ipparts[2] . ".*";
            if (in_array($ip, $whitelistedips)) {
                return true;
            }
        }
        if (2 <= count($ipparts)) {
            $ip = $ipparts[0] . "." . $ipparts[1] . ".*.*";
            if (in_array($ip, $whitelistedips)) {
                return true;
            }
        }
        return false;
    }

    private function isBanEnabled()
    {
        return 0 < \DI::make("app")->get_config("InvalidLoginBanLength") ? true : false;
    }

    private function getLoginBanDate()
    {
        return date("Y-m-d H:i:s", mktime(date("H"), date("i") + \DI::make("app")->get_config("InvalidLoginBanLength"), date("s"), date("m"), date("d"), date("Y")));
    }

    protected function sendWhitelistedIPNotice()
    {
        return (bool) \App::self()->get_config("sendFailedLoginWhitelist");
    }

    public function failedLogin()
    {
        $whmcs = \DI::make("app");
        if (!$this->isBanEnabled()) {
            return false;
        }
        $remote_ip = Utility\Environment\CurrentRequest::getIP();
        if ($this->isWhitelistedIP($remote_ip)) {
            if ($this->sendWhitelistedIPNotice()) {
                if (isset($this->admindata["username"])) {
                    $username = $this->admindata["username"];
                    sendAdminNotification("system", "WHMCS Admin Failed Login Attempt", "<p>A recent login attempt failed.  Details of the attempt are below.</p><p>Date/Time: " . date("d/m/Y H:i:s") . "<br>" . "Username: " . $username . "<br>" . "IP Address: " . $remote_ip . "<br>" . "Hostname: " . gethostbyaddr($remote_ip) . "</p>");
                } else {
                    sendAdminNotification("system", "WHMCS Admin Failed Login Attempt", "<p>A recent login attempt failed.  Details of the attempt are below.</p><p>Date/Time: " . date("d/m/Y H:i:s") . "<br>" . "Username: " . $this->inputusername . "<br>" . "IP Address: " . $remote_ip . "<br>" . "Hostname: " . gethostbyaddr($remote_ip) . "</p>");
                }
            }
            return false;
        }
        $loginfailures = safe_unserialize($whmcs->get_config("LoginFailures")) ?: [];
        if (!array_key_exists($remote_ip, $loginfailures) || !is_array($loginfailures[$remote_ip])) {
            $loginfailures[$remote_ip] = [];
        }
        if (!isset($loginfailures[$remote_ip]["count"]) || isset($loginfailures[$remote_ip]["expires"]) && $loginfailures[$remote_ip]["expires"] < time()) {
            $loginfailures[$remote_ip]["count"] = 0;
        }
        $loginfailures[$remote_ip]["count"]++;
        $loginfailures[$remote_ip]["expires"] = time() + 1800;
        if (3 <= $loginfailures[$remote_ip]["count"]) {
            unset($loginfailures[$remote_ip]);
            insert_query("tblbannedips", ["ip" => $remote_ip, "reason" => "3 Invalid Login Attempts", "expires" => $this->getLoginBanDate()]);
        }
        $whmcs->set_config("LoginFailures", safe_serialize($loginfailures));
        if (isset($this->admindata["username"])) {
            $username = $this->admindata["username"];
            sendAdminNotification("system", "WHMCS Admin Failed Login Attempt", "<p>A recent login attempt failed.  Details of the attempt are below.</p><p>Date/Time: " . date("d/m/Y H:i:s") . "<br>Username: " . $username . "<br>IP Address: " . $remote_ip . "<br>Hostname: " . gethostbyaddr($remote_ip) . "</p>");
            logActivity("Failed Admin Login Attempt - Username: " . $username);
        } else {
            sendAdminNotification("system", "WHMCS Admin Failed Login Attempt", "<p>A recent login attempt failed.  Details of the attempt are below.</p><p>Date/Time: " . date("d/m/Y H:i:s") . "<br>Username: " . $this->inputusername . "<br>IP Address: " . $remote_ip . "<br>Hostname: " . gethostbyaddr($remote_ip) . "</p>");
            logActivity("Failed Admin Login Attempt - IP: " . $remote_ip);
        }
    }

    public static function getID()
    {
        return Auth::isLoggedIn() ? (int) $_SESSION["adminid"] : 0;
    }

    public static function isLoggedIn()
    {
        return isset($_SESSION["adminid"]);
    }

    public function logout()
    {
        if ($this->isLoggedIn()) {
            update_query("tbladminlog", ["logouttime" => "now()"], ["sessionid" => session_id()]);
            $adminid = $_SESSION["adminid"];
            session_unset();
            session_destroy();
            $this->unsetRememberMeCookie();
            run_hook("AdminLogout", ["adminid" => $adminid]);
            return true;
        }
        return false;
    }

    public function isSessionPWHashValid($whmcsclass = false)
    {
        if (isset($_SESSION["adminpw"]) && $this->isAdminPWHashSet() && $_SESSION["adminpw"] == $this->generateAdminSessionHash($whmcsclass)) {
            return true;
        }
        return false;
    }

    public function updateAdminLog()
    {
        if (!$this->isLoggedIn()) {
            return false;
        }
        $loginExpiryTime = date("Y-m-d H:i:s", time() - 900);
        $adminlogid = get_query_val("tbladminlog", "id", "sessionid = '" . db_escape_string(session_id()) . "' AND " . "lastvisit >= '" . $loginExpiryTime . "' AND " . "logouttime = '0000-00-00 00:00:00'", "id", "DESC");
        if ($adminlogid) {
            update_query("tbladminlog", ["lastvisit" => "now()"], ["id" => $adminlogid]);
        } else {
            full_query("UPDATE tbladminlog SET logouttime = lastvisit WHERE adminusername='" . db_escape_string($this->getAdminUsername()) . "' AND " . "lastvisit < '" . $loginExpiryTime . "' AND " . "logouttime='0000-00-00 00:00:00'");
            insert_query("tbladminlog", ["adminusername" => $this->getAdminUsername(), "logintime" => "now()", "lastvisit" => "now()", "ipaddress" => Utility\Environment\CurrentRequest::getIP(), "sessionid" => session_id()]);
        }
        return true;
    }

    public function destroySession()
    {
        session_unset();
        @session_destroy();
        return true;
    }

    public static function persistAdminSession()
    {
        $app = \DI::make("app");
        $auth = new self();
        if ($auth->isLoggedIn()) {
            $auth->getInfobyID($_SESSION["adminid"], $app->getDatabaseObj()->getConnection());
            if (!$auth->isSessionPWHashValid($app)) {
                $auth->destroySession();
            }
        } else {
            if ($auth->isValidRememberMeCookie($app)) {
                $auth->setSessionVars($app);
            }
        }
    }

    public function cleanRedirectUri($uri)
    {
        $uri = trim(html_entity_decode($uri));
        $baseUrl = $this->getBaseUrl();
        if (strpos($uri, "?") !== false) {
            $uriSegments = explode("?", $uri, 2);
            if (preg_match("/rp=([^&]+)/", $uriSegments[1], $matches)) {
                $routePath = $matches[1];
                $uri = $baseUrl . "/index.php?rp=" . $routePath;
                return $uri;
            }
        }
        if ($uri !== $baseUrl && $uri !== $baseUrl . "/") {
            $uri = preg_replace("/^http[s]?\\:[\\/\\/]*/i", "", $uri);
            $uri = preg_replace("~/+~", "/", $uri);
            $uri = ltrim(preg_replace("/^" . preg_quote($baseUrl, "/") . "/i", "", $uri), "/");
        }
        if ($uri === "index.php") {
            $uri = "";
        }
        return $uri;
    }

    public function sanitizeUri($uri)
    {
        return trim(html_entity_decode(filter_var($uri, FILTER_SANITIZE_URL)));
    }

    protected function getBaseUrl()
    {
        return \DI::make("app")->isAdminAreaRequest() ? Utility\Environment\WebHelper::getAdminBaseUrl() : Utility\Environment\WebHelper::getBaseUrl();
    }

    public function redirectToLogin()
    {
        $requestUri = $this->cleanRedirectUri($_SERVER["REQUEST_URI"]);
        $redirectString = $requestUri ? "redirect=" . urlencode($requestUri) : "";
        redir($redirectString, Utility\Environment\WebHelper::getAdminBaseUrl() . "/login.php");
    }

    public function routableRedirectToLogin(Http\Message\ServerRequest $request)
    {
        $rp = $request->getUri()->getPath();
        $checkLocation = ROOTDIR . $rp;
        if (file_exists($checkLocation)) {
            $redirectString = "redirect=" . urlencode(Utility\Environment\WebHelper::getBaseUrl() . $rp);
        } else {
            $redirectString = "redirect=" . urlencode(Utility\Environment\WebHelper::getAdminBaseUrl() . "/index.php?rp=" . $rp);
        }
        $location = Utility\Environment\WebHelper::getAdminBaseUrl() . "/login.php";
        redir($redirectString, $location);
    }

    public function redirectPostLogin($redirectUri)
    {
        $redirectUri = $this->cleanRedirectUri($redirectUri);
        $urlparts = explode("?", $redirectUri, 2);
        $filename = !empty($urlparts[0]) ? $urlparts[0] : Utility\Environment\WebHelper::getAdminBaseUrl() . "/index.php";
        $qry_string = !empty($urlparts[1]) ? $urlparts[1] : "";
        redir($qry_string, $filename);
    }

    public function redirect($redirectUri, $queryString = "")
    {
        $redirectUri = $this->cleanRedirectUri($redirectUri);
        $redirectQueryString = $redirectUri ? "redirect=" . urlencode($redirectUri) : "";
        if ($queryString) {
            if ($redirectQueryString) {
                $redirectQueryString .= "&";
            }
            $redirectQueryString .= $queryString;
        }
        redir($redirectQueryString, Utility\Environment\WebHelper::getAdminBaseUrl() . "/login.php");
    }
}
