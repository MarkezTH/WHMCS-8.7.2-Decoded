<?php

namespace WHMCS\View\Template;

abstract class AbstractConfigValues extends \Illuminate\Support\Collection
{
    private $template = NULL;
    private $parent = NULL;
    private $webRoot = "";
    private $templateUrlPath = "";
    private $assetPaths = [];
    private static $assetCache = [];

    public function __construct(TemplateSetInterface $template, $webRoot = NULL)
    {
        $this->template = $template;
        if (is_null($webRoot)) {
            $webRoot = \WHMCS\Utility\Environment\WebHelper::getBaseUrl();
        }
        $this->webRoot = $webRoot;
        $parent = $template->getParent();
        if ($parent) {
            $this->parent = new static($parent, $webRoot);
        }
        $this->templateUrlPath = implode("/", [$this->getWebRoot(), $template::templateDirectory(), $template->getName()]);
        parent::__construct($this->calculateValues());
    }
    protected abstract function defaultPathMap();
    protected abstract function calculateValues();

    public function getWebRoot()
    {
        return $this->webRoot;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function assetDirectory()
    {
        $template = $this->getTemplate();
        return ROOTDIR . DIRECTORY_SEPARATOR . $template::templateDirectory() . DIRECTORY_SEPARATOR . $template->getName();
    }

    public function templateUrlPath()
    {
        return $this->templateUrlPath;
    }

    public function getAssetPathDeclaration($key)
    {
        $declaredAssetPathConfig = "";
        $config = $this->getTemplate()->getConfig()->getConfig();
        $rootAssetPath = $config["assetPath"] ?? "";
        if (!empty($rootAssetPath) && !empty($rootAssetPath) && is_array($rootAssetPath) && !empty($rootAssetPath[$key])) {
            $declaredAssetPathConfig = $rootAssetPath[$key];
        }
        return $declaredAssetPathConfig;
    }

    protected function defaultValues()
    {
        $values = ["instance" => $this->getTemplate(), "path" => $this->templateUrlPath(), "assetPath" => $this->assetPaths()];
        return $values;
    }

    protected function defaultAssetPath($key)
    {
        $path = $this->templateUrlPath();
        $defaults = $this->defaultPathMap();
        if (isset($defaults[$key])) {
            $path .= $defaults[$key];
        } else {
            $path .= $key;
        }
        return $path;
    }

    public function assetPaths()
    {
        if (empty($this->assetPaths)) {
            $values = [];
            foreach (array_keys($this->defaultPathMap()) as $key) {
                $path = $this->getAssetPath($key);
                if (empty($path)) {
                    $path = $this->defaultAssetPath($key);
                }
                $values[$key] = $path;
            }
            $this->assetPaths = $values;
        }
        return $this->assetPaths;
    }

    public static function resetAssetCache()
    {
        self::$assetCache = [];
    }

    protected function cacheAsset($key, $value)
    {
        $name = $this->getTemplate()->getName();
        self::$assetCache[$name][$key] = $value;
    }

    protected function isCachedAsset($key)
    {
        $name = $this->getTemplate()->getName();
        return (bool) isset(self::$assetCache[$name][$key]);
    }

    protected function cachedAsset($key)
    {
        $name = $this->getTemplate()->getName();
        return self::$assetCache[$name][$key];
    }

    public function getAssetPath($key)
    {
        if ($this->isCachedAsset($key)) {
            return $this->cachedAsset($key);
        }
        $parentCheck = false;
        $path = "";
        $parent = $this->getParent();
        $declaredAssetPathConfig = $this->getAssetPathDeclaration($key);
        if (empty($declaredAssetPathConfig)) {
            $filePath = $this->assetDirectory() . DIRECTORY_SEPARATOR . $key;
            if (is_dir($filePath)) {
                $path = $this->defaultAssetPath($key);
            } else {
                if ($parent) {
                    $parentCheck = true;
                    $path = $parent->getAssetPath($key);
                }
            }
        } else {
            if (preg_match(\WHMCS\Config\Template::PATTERN_REFERENCE_PARENT, $declaredAssetPathConfig)) {
                $parentCheck = true;
                $path = $parent->getAssetPath($key);
            } else {
                if (preg_match(\WHMCS\Config\Template::PATTERN_REFERENCE_DEFAULT, $declaredAssetPathConfig)) {
                    $path = $this->defaultAssetPath($key);
                } else {
                    if (preg_match(\WHMCS\Config\Template::PATTERN_RELATIVE_SYSTEM_URL_PATH, $declaredAssetPathConfig)) {
                        $filePath = ROOTDIR . DIRECTORY_SEPARATOR . $declaredAssetPathConfig;
                        if (is_dir($filePath)) {
                            $path = $this->getWebRoot() . "/" . $declaredAssetPathConfig;
                        }
                    } else {
                        if (preg_match(\WHMCS\Config\Template::PATTERN_ABSOLUTE_URL_PATH, $declaredAssetPathConfig)) {
                            $path = $declaredAssetPathConfig;
                        } else {
                            $filePath = $this->assetDirectory() . DIRECTORY_SEPARATOR . $declaredAssetPathConfig;
                            if (is_dir($filePath)) {
                                $path = $this->defaultAssetPath($declaredAssetPathConfig);
                            }
                        }
                    }
                }
            }
        }
        if (!$path && $parentCheck === false && $parent) {
            $path = $parent->getAssetPath($key);
        }
        $this->cacheAsset($key, $path);
        return $path;
    }
}
