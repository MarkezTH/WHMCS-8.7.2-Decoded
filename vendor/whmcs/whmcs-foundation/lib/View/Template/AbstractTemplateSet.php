<?php

namespace WHMCS\View\Template;

abstract class AbstractTemplateSet implements TemplateSetInterface
{
    protected $name = NULL;
    protected $config = NULL;
    protected $parent = NULL;
    protected static $allTemplates = [];

    public function __construct($name, \WHMCS\Config\Template $config)
    {
        $this->name = trim($name);
        $this->config = $config;
    }
    public static abstract function type();
    public static abstract function templateDirectory();
    public static abstract function defaultName();
    public static abstract function defaultSettingKey();
    public abstract function getTemplateConfigValues($AbstractConfigValues);

    public static function factory($systemTemplateName, $sessionTemplateName = NULL, $requestTemplateName = NULL)
    {
        if (is_null($systemTemplateName)) {
            $systemTemplateName = \WHMCS\Config\Setting::getValue(static::defaultSettingKey());
        }
        if (is_null($sessionTemplateName)) {
            $sessionTemplateName = \WHMCS\Session::get(static::defaultSettingKey());
        }
        $allTemplates = self::all();
        $availableTemplates = [];
        foreach ($allTemplates as $template) {
            $availableTemplates[] = $template->getName();
        }
        if (in_array($requestTemplateName, $availableTemplates)) {
            \WHMCS\Session::set(static::defaultSettingKey(), $requestTemplateName);
            return self::find($requestTemplateName);
        }
        if (in_array($sessionTemplateName, $availableTemplates)) {
            return self::find($sessionTemplateName);
        }
        if (in_array($systemTemplateName, $availableTemplates)) {
            return self::find($systemTemplateName);
        }
        if (in_array(static::defaultName(), $availableTemplates)) {
            return self::find(static::defaultName());
        }
        return self::find($availableTemplates[0]);
    }

    public static function find($name)
    {
        $class = get_called_class();
        $path = ROOTDIR . DIRECTORY_SEPARATOR . static::templateDirectory() . DIRECTORY_SEPARATOR . $name;
        try {
            $file = new \WHMCS\File($path);
        } catch (\Throwable $e) {
            return NULL;
        }
        $configFile = $path . DIRECTORY_SEPARATOR . "theme.yaml";
        try {
            if ($name != "" && $file->exists()) {
                return new $class($name, new \WHMCS\Config\Template(new \WHMCS\File($configFile)));
            }
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            $formattedMessage = "Error Parsing theme.yaml file '" . $configFile . "' - Error: " . $e->getMessage();
            throw new \WHMCS\Exception\Application\Configuration\YamlParseError($formattedMessage);
        }
    }

    protected static function ignoredTemplateDirectories()
    {
        return ["orderforms"];
    }

    public static function all()
    {
        $class = static::type();
        if (isset(static::$allTemplates[$class])) {
            return static::$allTemplates[$class];
        }
        $templates = [];
        $directoryIterator = new \DirectoryIterator(ROOTDIR . DIRECTORY_SEPARATOR . static::templateDirectory());
        foreach ($directoryIterator as $fileInfo) {
            if ($fileInfo->isDir() && !$fileInfo->isDot() && !in_array($fileInfo->getFilename(), static::ignoredTemplateDirectories())) {
                try {
                    $template = self::find($fileInfo->getFilename());
                } catch (\WHMCS\Exception\Application\Configuration\YamlParseError $e) {
                    if (\WHMCS\Auth::isLoggedIn()) {
                        logActivity($e->getMessage());
                    }
                    $template = NULL;
                }
                if (!is_null($template)) {
                    $templates[$fileInfo->getFilename()] = $template;
                }
            }
        }
        uasort($templates, function (TemplateSetInterface $a, TemplateSetInterface $b) {
            if ($a->getDisplayName() == $b->getDisplayName()) {
                return 0;
            }
            return $b->getDisplayName() < $a->getDisplayName() ? 1 : -1;
        });
        static::$allTemplates[$class] = new \Illuminate\Support\Collection($templates);
        return static::$allTemplates[$class];
    }

    public static function getDefault()
    {
        $default = self::find(\WHMCS\Config\Setting::getValue(static::defaultSettingKey()));
        if (!$default) {
            $default = self::find(static::defaultName());
        }
        return $default;
    }

    public static function setDefault($value)
    {
        if ($value instanceof TemplateSetInterface) {
            $value = $value->getName();
        }
        \WHMCS\Config\Setting::setValue(static::defaultSettingKey(), $value);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDisplayName()
    {
        $name = "";
        if ($this->config) {
            $name = $this->config->getName();
        }
        if (!$name || $name === "WHMCS Six Theme" && $this->name !== "six" || $name === "Six" && $this->name !== "six" || $name === "Twenty-One" && $this->name !== "twenty-one") {
            $name = titleCase(str_replace("_", " ", $this->name));
        }
        return $name;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function isDefault()
    {
        return $this->name == \WHMCS\Config\Setting::getValue(static::defaultSettingKey());
    }

    public function getParent()
    {
        if (is_null($this->parent)) {
            $this->buildParent();
        }
        return $this->parent;
    }

    public function isRoot()
    {
        return is_null($this->getParent());
    }

    public function getProvides()
    {
        $data = $this->getConfig()->getProvides();
        $parent = $this->getParent();
        if ($parent) {
            $data = array_merge($parent->getProvides(), $data);
        }
        return $data;
    }

    public function getDependencies()
    {
        $data = $this->getConfig()->getDependencies();
        $parent = $this->getParent();
        if ($parent) {
            $data = array_merge($parent->getDependencies(), $data);
        }
        return $data;
    }

    public function getProperties()
    {
        $data = $this->getConfig()->getProperties();
        $parent = $this->getParent();
        if ($parent) {
            $data = array_merge($parent->getProperties(), $data);
        }
        return $data;
    }

    public function getTemplatePath()
    {
        return ROOTDIR . DIRECTORY_SEPARATOR . static::templateDirectory() . DIRECTORY_SEPARATOR . $this->getName() . DIRECTORY_SEPARATOR;
    }

    public function resolveFilePath($basename)
    {
        $basename = ltrim($basename, DIRECTORY_SEPARATOR);
        $template = $this;
        while ($template) {
            $path = $template->getTemplatePath() . $basename;
            if (file_exists($path)) {
                return $path;
            }
            $template = $template->getParent();
        }
        return $this->getName() . DIRECTORY_SEPARATOR . $basename;
    }

    protected function buildParent()
    {
        $config = $this->getConfig()->getConfig();
        foreach (static::all() as $template) {
            if (isset($config["parent"]) && $config["parent"] !== $this->getName() && $template->getName() == $config["parent"]) {
                $this->parent = $template;
                return $this;
            }
        }
    }

    public function hasTemplate($template, $checkParent = true)
    {
        $parentTemplate = $this->getParent();
        $parentCheck = false;
        if ($parentTemplate && $checkParent) {
            $parentCheck = $this->getParent()->hasTemplate($template, false);
        }
        return file_exists($this->getTemplatePath() . $template . ".tpl") ?: $parentCheck;
    }

    public function getThumbnailWebPath()
    {
        if (file_exists($this->getTemplatePath() . "thumbnail.gif")) {
            return \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/" . static::templateDirectory() . "/" . $this->getName() . "/" . "thumbnail.gif";
        }
        return \WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/" . \App::get_admin_folder_name() . "/images/ordertplpreview.gif";
    }
}
