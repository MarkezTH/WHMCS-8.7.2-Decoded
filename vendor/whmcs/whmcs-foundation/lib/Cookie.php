<?php

namespace WHMCS;

class Cookie
{
    public static function get($name, $treatAsArray = false)
    {
        $val = array_key_exists("WHMCS" . $name, $_COOKIE) ? $_COOKIE["WHMCS" . $name] : "";
        if ($treatAsArray) {
            $val = json_decode(base64_decode($val), true);
            $val = is_array($val) ? htmlspecialchars_array($val) : [];
        }
        return $val;
    }

    public static function set($name, $value, $expires = 0, $secure = NULL)
    {
        if (is_array($value)) {
            $value = base64_encode(json_encode($value));
        } else {
            if (is_null($value)) {
                $value = "";
            }
        }
        if (!is_numeric($expires)) {
            if (substr($expires, -1) == "m") {
                $expires = time() + substr($expires, 0, -1) * 30 * 24 * 60 * 60;
            } else {
                $expires = 0;
            }
        }
        if (is_null($secure)) {
            $whmcs = \DI::make("app");
            $secure = (bool) $whmcs->isSSLAvailable();
        }
        return setcookie("WHMCS" . $name, $value, $expires, "/", "", $secure, true);
    }

    public static function delete($name)
    {
        unset($_COOKIE["WHMCS" . $name]);
        return self::set($name, NULL, -86400);
    }
}
