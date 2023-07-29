<?php

namespace WHMCS;

class FlashMessages
{
    public static function add($message, $status = "success")
    {
        $flashMessages = Session::get("flash");
        if (!is_array($flashMessages)) {
            $flashMessages = [];
        }
        if (defined("ROUTE_CONVERTED_LEGACY_ENDPOINT")) {
            $filename = Utility\Environment\WebHelper::getBaseUrl() . "/index.php";
        } else {
            $filename = \App::getPhpSelf();
        }
        $flashMessages[$filename] = ["type" => $status, "text" => $message];
        Session::set("flash", $flashMessages);
    }

    public static function get()
    {
        $phpSelf = \App::getPhpSelf();
        $flashMessages = Session::get("flash");
        if (isset($flashMessages[$phpSelf])) {
            $flashMessage = $flashMessages[$phpSelf];
            unset($flashMessages[$phpSelf]);
            Session::set("flash", $flashMessages);
            return $flashMessage;
        }
        return false;
    }
}
