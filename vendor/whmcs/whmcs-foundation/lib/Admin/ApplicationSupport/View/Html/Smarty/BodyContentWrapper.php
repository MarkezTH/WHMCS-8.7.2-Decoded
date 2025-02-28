<?php

namespace WHMCS\Admin\ApplicationSupport\View\Html\Smarty;

class BodyContentWrapper extends \WHMCS\Admin\ApplicationSupport\View\Html\AbstractTemplateEngine
{
    public function __construct($data = "", $status = 200, $headers = [])
    {
        parent::__construct($data, $status, $headers);
        $this->setBodyContent($data);
    }

    public function getFormattedBodyContent()
    {
        return $this->getBodyContent();
    }

    protected function factoryEngine()
    {
        return \DI::make("View\\Engine\\Smarty\\Admin");
    }

    public function getFormattedFooterContent()
    {
        $smarty = $this->getTemplateEngine();
        $footer_output = $smarty->fetch($this->getTemplateDirectory() . "/footer.tpl");
        $licenseBannerHtml = $this->getLicenseBannerHtml();
        if ($licenseBannerHtml) {
            $endBodyTagPosition = strpos($footer_output, "</body>");
            if ($endBodyTagPosition === false) {
                $footer_output = $footer_output . $licenseBannerHtml;
            } else {
                $footer_output = substr($footer_output, 0, $endBodyTagPosition) . $licenseBannerHtml . substr($footer_output, $endBodyTagPosition);
            }
        }
        return $footer_output;
    }

    public function getFormattedHeaderContent()
    {
        $smarty = $this->getTemplateEngine();
        $smarty->assign("globalAdminWarningMsg", $this->getGlobalWarningNotification());
        $smarty->assign("clientLimitNotification", $this->getClientLimitNotification());
        return $smarty->fetch($this->getTemplateDirectory() . "/header.tpl");
    }
}
