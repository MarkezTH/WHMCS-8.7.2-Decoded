<?php

namespace WHMCS;

class Smarty extends \SmartyBC
{
    public function __construct($admin = false, $policyName = NULL)
    {
        $this->setMbStringMode();
        $whmcs = \App::self();
        $config = \Config::self();
        parent::__construct();
        $this->setCaching(\Smarty::CACHING_OFF);
        $this->setTemplateDir(ROOTDIR . ($admin ? DIRECTORY_SEPARATOR . $whmcs->get_admin_folder_name() : "") . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR);
        $this->setCompileDir($config["templates_compiledir"]);
        $this->registerPlugin("modifier", "sprintf2", ["WHMCS\\Smarty", "sprintf2Modifier"]);
        $this->registerPlugin("modifier", "implode", ["WHMCS\\Smarty", "implodeModifier"]);
        $this->registerPlugin("function", "lang", ["WHMCS\\Smarty", "langFunction"]);
        $this->registerPlugin("function", "assetPath", ["WHMCS\\Smarty", "assetPath"]);
        $this->registerPlugin("block", "assetExists", ["WHMCS\\Smarty", "assetExists"]);
        $this->registerFilter("pre", ["WHMCS\\Smarty", "preFilterSmartyTemplateVariableScopeResolution"]);
        if (!$policyName) {
            $policyName = "system";
        }
        $policy = \DI::make("WHMCS\\Smarty\\Security\\Policy", [$this, $policyName]);
        $this->enableSecurity($policy);
        $this->default_template_handler_func = [$this, "templateHandler"];
    }

    protected function setMbStringMode()
    {
        self::$_MBSTRING = SMARTY_MBSTRING && function_exists("mb_split");
    }

    public function trigger_error($error_msg, $error_type = E_USER_WARNING)
    {
        if (function_exists("logActivity")) {
            logActivity("Smarty Error: " . $error_msg);
        } else {
            $error_msg = htmlentities($error_msg);
            trigger_error("Smarty error: " . $error_msg, $error_type);
        }
    }

    public function clearAllCaches()
    {
        $this->clearAllCache();
        $this->clearCompiledTemplate();
        $src = "<?php\nheader(\"Location: ../index.php\");";
        $whmcs = Application::getInstance();
        try {
            $compileDir = $this->getCompileDir();
            $file = new File($compileDir . DIRECTORY_SEPARATOR . "index.php");
            $file->create($src);
        } catch (\Exception $e) {
        }
    }

    public static function sprintf2Modifier($string, $arg1, $arg2 = "", $arg3 = "", $arg4 = "")
    {
        return sprintf($string, $arg1, $arg2, $arg3, $arg4);
    }

    public static function implodeModifier($value, $arg1 = "")
    {
        if (is_array($value)) {
            return implode($arg1 ?? "", $value);
        }
        if (is_array($arg1)) {
            return implode($value ?? "", $arg1);
        }
        throw new Exception("One of the arguments to Smarty implode must be an array");
    }

    public static function langFunction($params)
    {
        $translateKey = NULL;
        $forceAdmin = false;
        $returnValue = $defaultValue = "";
        foreach ($params as $key => $value) {
            if ($key == "key") {
                $translateKey = $value;
            } else {
                if ($key == "forceAdmin") {
                    $forceAdmin = true;
                } else {
                    if ($key == "defaultValue") {
                        $defaultValue = $value;
                    } else {
                        if (strpos($key, ":") !== 0) {
                            $params[":" . $key] = $value;
                        }
                    }
                }
            }
            unset($params[$key]);
        }
        if (\App::isAdminAreaRequest() || $forceAdmin) {
            $returnValue = \AdminLang::trans($translateKey, $params);
        } else {
            $returnValue = \Lang::trans($translateKey, $params);
        }
        if ($returnValue == $translateKey && $defaultValue) {
            $returnValue = $defaultValue;
        }
        return $returnValue;
    }

    protected static function getTemplateInstance(\Smarty_Internal_Template $smartyInternal)
    {
        $template = NULL;
        $instances = ["theme" => NULL, "orderform" => NULL];
        foreach ($instances as $name => $value) {
            $templateData = $smartyInternal->getTemplateVars($name);
            if (is_array($templateData) && !empty($templateData["instance"]) && $templateData["instance"] instanceof View\Template\TemplateSetInterface) {
                $instances[$name] = $templateData["instance"];
            }
        }
        if ($smartyInternal->source instanceof \Smarty_Template_Source && $smartyInternal->source->type == "file") {
            $orderformDirs = ["orderforms/", rtrim(ROOTDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . View\Template\OrderForm::templateDirectory()];
            $source = $smartyInternal->source;
            $name = $source->name;
            $name = str_replace(["\\", "/"], DIRECTORY_SEPARATOR, $name);
            foreach ($orderformDirs as $dir) {
                $dir = str_replace(["\\", "/"], DIRECTORY_SEPARATOR, $dir);
                if (strpos($name, $dir) === 0) {
                    $template = $instances["orderform"];
                    if (!$template) {
                        $template = $instances["theme"];
                    }
                }
            }
        }
        return $template;
    }

    public static function assetExists($params, $content, \Smarty_Internal_Template $smartyInternal, &$repeat)
    {
        if (empty($params["assign"])) {
            $assignmentKey = "__assetPath__";
        } else {
            $assignmentKey = $params["assign"];
        }
        if (!$repeat) {
            $smartyInternal->clearAssign($assignmentKey);
            return $content;
        }
        if (empty($params["file"])) {
            $repeat = false;
        } else {
            $basename = $params["file"];
            if (empty($params["ns"])) {
                $namespace = pathinfo($basename, PATHINFO_EXTENSION);
            } else {
                $namespace = $params["ns"];
            }
            $template = self::getTemplateInstance($smartyInternal);
            if (!$template) {
                $repeat = false;
            } else {
                $assetUtil = new View\Template\AssetUtil($template);
                $path = $assetUtil->assetExists($basename, $namespace);
                if ($path) {
                    $smartyInternal->assign($assignmentKey, $path);
                } else {
                    $repeat = false;
                }
            }
        }
    }

    public static function assetPath($params, \Smarty_Internal_Template $smartyInternal)
    {
        $path = "";
        if (empty($params["file"])) {
            return $path;
        }
        $basename = $params["file"];
        if (empty($params["ns"])) {
            $namespace = pathinfo($basename, PATHINFO_EXTENSION);
        } else {
            $namespace = $params["ns"];
        }
        $template = self::getTemplateInstance($smartyInternal);
        if (!$template) {
            return $namespace . "/" . $basename;
        }
        $assetUtil = new View\Template\AssetUtil($template);
        $path = $assetUtil->assetUrl($basename, $namespace);
        return $path;
    }

    public function fetch($template = NULL, $cache_id = NULL, $compile_id = NULL, $parent = NULL, $display = false, $merge_tpl_vars = true, $no_output_filter = false)
    {
        try {
            return parent::fetch($template, $cache_id, $compile_id, $parent, $display, $merge_tpl_vars, $no_output_filter);
        } catch (\Exception $e) {
            $this->trigger_error($e->getMessage());
        }
    }

    public function setMailMessage(Mail\Message $message)
    {
        $this->unregisterResource("mailMessage");
        $this->registerResource("mailMessage", new Smarty\Resource\MailMessage($message));
    }

    public static function preFilterSmartyTemplateVariableScopeResolution($source, \Smarty_Internal_Template $internal_Template)
    {
        $policy = $internal_Template->smarty->security_policy;
        $tags = $policy->disabled_tags;
        if (!is_array($tags) || in_array(Smarty\Security\Policy::TAG_COMPILER_PHP, $tags)) {
            return $source;
        }
        if (!in_array("template_object", $policy->enabled_special_smarty_vars)) {
            return $source;
        }
        $source = "{php}\$template = \$_smarty_tpl;\n{/php}" . $source;
        return $source;
    }

    public function templateHandler($type, $name, &$content, &$modified, Smarty $smarty)
    {
        $parts = explode("/", $name);
        $parts = array_filter($parts);
        $possiblyTemplateName = array_shift($parts);
        $prefix = "";
        if ($possiblyTemplateName === "orderforms") {
            $prefix = "orderforms";
            $possiblyTemplateName = array_shift($parts);
            $orderform = $smarty->tpl_vars["orderform"];
            if (!$orderform || !is_array($orderform->value)) {
                return false;
            }
            $templateName = $templateName = $smarty->tpl_vars["carttpl"];
            $templateValues = $smarty->tpl_vars["orderform"];
        } else {
            $templateName = $smarty->tpl_vars["template"];
            $templateValues = $smarty->tpl_vars["theme"];
        }
        if (!$templateName || empty($templateName->value)) {
            return false;
        }
        $templateName = $templateName->value;
        if (!$templateValues || !is_array($templateValues->value)) {
            return false;
        }
        $templateValues = $templateValues->value;
        if (!isset($templateValues["instance"]) || !$templateValues["instance"] instanceof View\Template\TemplateSetInterface) {
            return false;
        }
        $template = $templateValues["instance"];
        if ($possiblyTemplateName === $templateName && $template->getName() === $templateName) {
            $missingPath = implode("/", $parts);
            $proposed = $template->resolveFilePath($missingPath);
            if ($proposed !== $possiblyTemplateName . "/" . $missingPath) {
                return $proposed;
            }
        }
        return false;
    }
}
