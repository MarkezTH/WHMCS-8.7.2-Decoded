<?php

namespace WHMCS\View\Template;

class ThemeValues extends AbstractConfigValues
{
    protected function defaultPathMap()
    {
        return ["css" => "/css", "fonts" => "/fonts", "img" => "/img", "js" => "/js"];
    }

    protected function calculateValues()
    {
        $theme = $this->getTemplate();
        return ["template" => $theme->getName(), "webroot" => $this->getWebRoot(), "theme" => $this->defaultValues()];
    }
}
