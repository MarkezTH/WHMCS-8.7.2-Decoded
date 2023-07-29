<?php

namespace WHMCS\View\Template;

class OrderFormValues extends AbstractConfigValues
{
    protected function defaultPathMap()
    {
        return ["css" => "/css", "img" => "/img", "js" => "/js"];
    }

    protected function calculateValues()
    {
        return ["orderform" => $this->defaultValues()];
    }
}
