<?php

namespace WHMCS\Smarty\Security;

class Policy extends \Smarty_Security
{
    public $enabled_special_smarty_vars = [];
    const TAG_COMPILER_PHP = "private_php";

    public function __construct(\Smarty $smarty, Settings\BasePolicy $policySettings)
    {
        \Smarty_Internal_TemplateCompilerBase::$_tag_objects = [];
        $this->loadPolicySettings($policySettings);
        parent::__construct($smarty);
    }

    public function loadPolicySettings(Settings\BasePolicy $settings)
    {
        $this->php_functions = $settings->getPhpFunctions();
        $this->php_modifiers = $settings->getPhpModifiers();
        $this->allowed_modifiers = $settings->getAllowedModifiers();
        $this->disabled_modifiers = $settings->getDisabledModifiers();
        $this->allowed_tags = $settings->getAllowedTags();
        $this->disabled_tags = $settings->getDisabledTags();
        $this->static_classes = $settings->getStaticClasses();
        $this->trusted_static_methods = $settings->getTrustedStaticMethods();
        $this->trusted_static_properties = $settings->getTrustedStaticProperties();
        $this->disabled_special_smarty_vars = $settings->getDisabledSpecialSmartyVars();
        $this->enabled_special_smarty_vars = $settings->getEnabledSpecialSmartyVars();
        $this->streams = $settings->getStreams();
        $this->allow_super_globals = $settings->isAllowSuperGlobals();
        $this->allow_constants = $settings->isAllowConstants();
        $this->secure_dir = $settings->getSecureDir();
        $this->trusted_dir = $settings->getTrustedDir();
        return $this;
    }

    public function isTrustedSpecialSmartyVar($var_name, $compiler)
    {
        if (in_array($var_name, $this->enabled_special_smarty_vars)) {
            return true;
        }
        $compiler->trigger_template_error("special variable '\$smarty." . $var_name . "' not allowed by security setting", NULL, true);
        return false;
    }

    public function isTrustedTag($tag_name, $compiler)
    {
        $lex = $compiler->parser->lex;
        $lex->taglineno = (int) $lex->taglineno;
        $lex->line = (int) $lex->line;
        return parent::isTrustedTag($tag_name, $compiler);
    }
}
