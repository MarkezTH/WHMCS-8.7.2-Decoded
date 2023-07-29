<?php

namespace WHMCS\MarketConnect\Services;

trait FtpServiceTrait
{
    public function getFtpDetailsForm($params, \Exception $exception = NULL)
    {
        $params = new ClientAreaOutputParameters($params);
        return (object) ["body" => $this->clientAreaAddonUpdateFtpForm($params, $exception), "title" => $this->cLang("updateFtp")];
    }

    public function clientAreaAddonUpdateFtpForm($params = NULL, \Exception $exception)
    {
        $ident = $this->getServiceIdent();
        $serviceId = $params->getServiceId();
        $addonId = $params->getAddonId();
        $token = generate_token();
        $ftpHostLabel = $this->cLang("ftpHost");
        $ftpUsernameLabel = $this->cLang("ftpUsername");
        $ftpPasswordLabel = $this->cLang("ftpPassword");
        $ftpPathLabel = $this->cLang("ftpPath");
        $ftpHost = \App::getFromRequest("ftpHost");
        $ftpUsername = \App::getFromRequest("ftpUsername");
        $ftpPassword = \App::getFromRequest("ftpPassword");
        $ftpPath = \App::getFromRequest("ftpPath");
        $errorMarkup = "";
        if ($exception instanceof \Exception) {
            $errorMarkup = "<div class=\"alert alert-danger update-feedback\" role=\"alert\">" . $exception->getMessage() . "</div>";
        }
        return "<form method=\"POST\" autocomplete=\"off\" id=\"" . $ident . "FtpUpdate\" class=\"form-horizontal\">\n    " . $token . "\n    <input type=\"hidden\" name=\"modop\" value=\"custom\" />\n    <input type=\"hidden\" name=\"a\" value=\"update_ftp_details\" />\n    <input type=\"hidden\" name=\"id\" value=\"" . $serviceId . "\" />\n    <input type=\"hidden\" name=\"addonId\" value=\"" . $addonId . "\" />\n    " . $errorMarkup . "\n    <div class=\"row form-group\">\n        <label for=\"ftpHost\" class=\"col-md-3 col-form-label control-label text-right\"\n            >" . $ftpHostLabel . "</label>\n        <div class=\"col-md-9\">\n            <input type=\"text\" name=\"ftpHost\" class=\"form-control\" placeholder=\"ftp.hostname.com\"\n                value=\"" . $ftpHost . "\"/>\n        </div>\n    </div>\n    <div class=\"row form-group\">\n        <label for=\"ftpUsername\" class=\"col-md-3 col-form-label control-label text-right\"\n            >" . $ftpUsernameLabel . "</label>\n        <div class=\"col-md-9\">\n            <input type=\"text\" name=\"ftpUsername\" class=\"form-control\" value=\"" . $ftpUsername . "\"\n                placeholder=\"user@ftp.hostname.com\" />\n        </div>\n    </div>\n    <div class=\"row form-group\">\n        <label for=\"ftpPassword\" class=\"col-md-3 col-form-label control-label text-right\"\n            >" . $ftpPasswordLabel . "</label>\n        <div class=\"col-md-9\">\n            <input type=\"password\" name=\"ftpPassword\" class=\"form-control\" placeholder=\"password\"\n                value=\"" . $ftpPassword . "\"/>\n        </div>\n    </div>\n    <div class=\"row form-group\">\n        <label for=\"ftpPath\" class=\"col-md-3 col-form-label control-label text-right\"\n            >" . $ftpPathLabel . "</label>\n        <div class=\"col-md-9\">\n            <input type=\"text\" name=\"ftpPath\" class=\"form-control\" placeholder=\"/\"\n                value=\"" . $ftpPath . "\"/>\n        </div>\n    </div>\n</form>";
    }

    protected function getFtpFormUrl($params)
    {
        $action = "update_ftp_details_form";
        return $this->getFormUrlForAction($action, $params);
    }
}
