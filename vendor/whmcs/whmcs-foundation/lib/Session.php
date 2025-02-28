<?php

namespace WHMCS;

class Session
{
    private $last_session_data = [];

    public static function getSessionServiceProviderClass(Config\Application $appConfig)
    {
        $serviceProvider = NULL;
        $sessionConfig = $appConfig->session_handling;
        if ($sessionConfig === "database") {
            $serviceProvider = "WHMCS\\Session\\Database\\ServiceProvider";
        } else {
            if (is_array($sessionConfig) && !empty($sessionConfig["serviceProvider"])) {
                $serviceProvider = $sessionConfig["serviceProvider"];
            }
        }
        return $serviceProvider;
    }

    public static function initializeHandler(Config\Application $appConfig)
    {
        $serviceProviderClass = static::getSessionServiceProviderClass($appConfig);
        if ($serviceProviderClass && class_exists($serviceProviderClass)) {
            \DI::getFacadeRoot()->register($serviceProviderClass);
        }
    }

    protected function startSession()
    {
        if (session_start()) {
            return session_id();
        }
        return "";
    }

    public static function start()
    {
        session_start();
    }

    protected function getSessionName($instanceid)
    {
        $instanceid = "WHMCS" . $instanceid;
        return $instanceid;
    }

    public function create($instanceid)
    {
        $isSslAvailable = substr(Config\Setting::getValue("SystemURL"), 0, 5) == "https";
        session_name($this->getSessionName($instanceid));
        session_set_cookie_params(0, "/", NULL, $isSslAvailable, true);
        return $this->startSession();
    }

    public static function get($key, $default = "")
    {
        return isset($_SESSION) && array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
        return true;
    }

    public static function delete($key)
    {
        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
            return true;
        }
        return false;
    }

    public static function getAndDelete($key)
    {
        $value = self::get($key);
        self::delete($key);
        return $value;
    }

    public static function startGetDeleteAndRelease($key)
    {
        self::start();
        $value = self::get($key);
        self::delete($key);
        self::release();
        return $value;
    }

    public static function rotate()
    {
        return session_regenerate_id();
    }

    public static function destroy()
    {
        session_unset();
        session_destroy();
    }

    public function nullify()
    {
        $this->last_session_data = $_SESSION;
        $_SESSION = [];
    }

    public function restore()
    {
        $_SESSION = $this->last_session_data;
    }

    public static function release()
    {
        session_write_close();
    }

    public static function setAndRelease($key, $value)
    {
        self::start();
        self::set($key, $value);
        self::release();
    }

    public static function exists($key)
    {
        return isset($_SESSION[$key]);
    }

    public static function keys()
    {
        return array_keys($_SESSION);
    }
}
