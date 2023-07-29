<?php

namespace WHMCS\Smarty\Security\Settings;

class MailPolicy extends SystemPolicy
{
    protected function getDefaultEnabledSpecialSmartyVars()
    {
        return ["now"];
    }

    protected function getDefaultPolicySettings()
    {
        $defaults = parent::getDefaultPolicySettings();
        $defaults["php_modifiers"] = ["escape", "count", "urlencode", "ucfirst", "date_format", "nl2br"];
        $defaults["php_functions"] = ["isset", "empty", "count", "sizeof", "in_array", "is_array", "time", "nl2br"];
        $defaults["static_classes"] = NULL;
        $defaults["trusted_static_methods"] = NULL;
        $defaults["trusted_static_properties"] = NULL;
        $defaults["streams"] = NULL;
        $defaults["allow_super_globals"] = false;
        $defaults["disabled_tags"] = array_merge($defaults["disabled_tags"] ?: [], ["include", "block", "function"]);
        return $defaults;
    }
}
