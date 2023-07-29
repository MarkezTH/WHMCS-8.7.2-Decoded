<?php

namespace WHMCS\Http;

class RedirectResponse extends \Laminas\Diactoros\Response\RedirectResponse
{
    public static function legacyPath($path)
    {
        $redirectUri = \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/" . $path;
        return new self($redirectUri);
    }

    public function withSuccess($msg)
    {
        \WHMCS\FlashMessages::add($msg, "success");
        return $this;
    }

    public function withInfo($msg)
    {
        \WHMCS\FlashMessages::add($msg, "info");
        return $this;
    }

    public function withWarning($msg)
    {
        \WHMCS\FlashMessages::add($msg, "warning");
        return $this;
    }

    public function withError($msg)
    {
        \WHMCS\FlashMessages::add($msg, "error");
        return $this;
    }

    public function withInput()
    {
        \WHMCS\Session::set("lastInput", $_REQUEST);
        return $this;
    }
}
