<?php

namespace WHMCS\Module\Addon\Mailchimp;

class SettingsHelper
{
    public $vars = [];

    public function __construct($vars)
    {
        $this->vars = $vars;
    }

    public function request($key)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : NULL;
    }

    public function get($key)
    {
        return isset($this->vars[$key]) ? $this->vars[$key] : NULL;
    }

    public function set($key, $value)
    {
        $setting = \WHMCS\Module\Addon\Setting::firstOrNew(["module" => $this->vars["module"], "setting" => $key]);
        $setting->value = $value;
        $setting->save();
        $this->vars[$key] = $value;
    }
}
