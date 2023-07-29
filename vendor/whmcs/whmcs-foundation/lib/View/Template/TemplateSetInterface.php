<?php

namespace WHMCS\View\Template;

interface TemplateSetInterface
{
    public static function type();

    public static function find($name);

    public static function all();

    public static function defaultName();

    public static function defaultSettingKey();

    public function getName();

    public function getDisplayName();

    public static function getDefault();

    public static function setDefault($value);

    public function getConfig();

    public function isDefault();

    public function getParent();

    public function isRoot();

    public function getProvides();

    public function getDependencies();

    public function getProperties();

    public function getTemplatePath();

    public static function templateDirectory();

    public function resolveFilePath($basename);

    public function getTemplateConfigValues($AbstractConfigValues);
}
