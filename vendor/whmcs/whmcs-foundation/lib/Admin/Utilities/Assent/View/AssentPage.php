<?php

namespace WHMCS\Admin\Utilities\Assent\View;

class AssentPage extends \WHMCS\Admin\ApplicationSupport\View\Html\Php\TemplatePage
{
    public function getTemplateDirectory()
    {
        return parent::getTemplateDirectory() . DIRECTORY_SEPARATOR . "assent";
    }
}
