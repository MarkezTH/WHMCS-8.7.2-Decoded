<?php

namespace WHMCS\Admin\ApplicationSupport\View\Html\Php;

class TemplatePage extends \WHMCS\Admin\ApplicationSupport\View\Html\ContentWrapper
{
    use \WHMCS\Admin\ApplicationSupport\View\Traits\AdminHtmlViewTrait;
    use \WHMCS\Admin\ApplicationSupport\View\Traits\VersionTrait;

    public function __construct($templateName, $data = [], $status = 200, $headers = [])
    {
        $this->setTemplateName($templateName)->setTemplateVariables($data);
        parent::__construct("", $status, $headers);
    }

    public function getTemplateDirectory()
    {
        return "admin";
    }

    protected function factoryEngine()
    {
        $templateEngine = \DI::make("View\\Engine\\Php\\Admin");
        $baseDir = $templateEngine->getDirectory();
        $spaceDir = $baseDir . DIRECTORY_SEPARATOR . $this->getTemplateDirectory();
        $templateEngine->setDirectory($spaceDir);
        return $templateEngine;
    }

    public function getBodyContent()
    {
        $this->prepareVariableContent();
        if (!$this->bodyContent) {
            $this->bodyContent = "";
            if ($this->getTemplateName()) {
                $this->bodyContent = view($this->getTemplateName(), $this->getTemplateVariables(), $this->factoryEngine());
            }
        }
        return $this->bodyContent;
    }
}
