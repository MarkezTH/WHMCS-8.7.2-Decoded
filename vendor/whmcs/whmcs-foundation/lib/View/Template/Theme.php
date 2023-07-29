<?php

namespace WHMCS\View\Template;

class Theme extends AbstractTemplateSet
{
    public static function factory($systemTemplateName = NULL, $sessionTemplateName = NULL, $requestTemplateName = NULL)
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

    public static function type()
    {
        return "theme";
    }

    public static function defaultName()
    {
        return "twenty-one";
    }

    public static function defaultSettingKey()
    {
        return "Template";
    }

    public static function templateDirectory()
    {
        return "templates";
    }

    public function getTemplateConfigValues($AbstractConfigValues)
    {
        return new ThemeValues($this);
    }
}
