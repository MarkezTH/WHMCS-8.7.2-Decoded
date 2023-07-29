<?php

namespace WHMCS\MarketConnect\Services\Ssl\View;

class ViewManagerClient extends ViewManager
{
    public function getTranslator($AbstractLanguage)
    {
        return \Lang::self();
    }

    public function renderDomainControlValidation()
    {
        $method = $this->ssl->authenticationData;
        if (!$method instanceof \WHMCS\Service\Ssl\ValidationMethod) {
            return "";
        }
        return "<div class=\"row dcv\">\n    <div class=\"col-md-4\">" . $this->trans("ssl.dcv") . "</div>\n    <div class=\"col-md-4 dcv-method\">\n        " . $this->trans($method->translationKey($this->lang)) . "\n    </div>\n</div>\n" . $this->renderDomainControlValidationMethodPartial($method);
    }

    public function renderDomainControlValidationMethodPartial($method)
    {
        $partialFunction = "renderDomainControlValidationMethodPartial" . ucfirst($method->methodNameConstant());
        if (!method_exists($this, $partialFunction)) {
            return "";
        }
        $method->defaults();
        return $this->{$partialFunction}($method);
    }

    public function renderDomainControlValidationMethodPartialFileauth($method)
    {
        $contentInput = $this->renderCopyHelperInput("dcv-file-content", $method->contents);
        $domain = strtolower($this->trans("ssldomain"));
        return "<div class=\"row py-2 dcv-property\">\n    <div class=\"col-md-4 dcv-field\">" . $this->trans("ssl.url") . "</div>\n    <div class=\"col-md-8 dcv-value\">http://&lt;" . $domain . "&gt;/" . $method->filePath() . "</div>\n</div>\n<div class=\"row py-2 dcv-property\">\n    <div class=\"col-md-4 dcv-field\">" . $this->trans("ssl.value") . "</div>\n    <div class=\"col-md-8 dcv-value\">" . $contentInput . "</div>\n</div>";
    }

    public function renderDomainControlValidationMethodPartialEmailauth($method)
    {
        $email = $method->email ?: $this->trans("ssl.defaultcontacts");
        return "<div class=\"row py-2 dcv-property\">\n    <div class=\"col-md-4 dcv-field\">" . $this->trans("email") . "</div>\n    <div class=\"col-md-8 dcv-value\">" . $email . "</div>\n</div>";
    }

    public function renderDomainControlValidationMethodPartialDnsauth($method)
    {
        $hostInput = $this->renderCopyHelperInput("dcv-dns-host", $method->host);
        $valueInput = $this->renderCopyHelperInput("dcv-dns-value", $method->value);
        return "<div class=\"row py-2 dcv-property\">\n    <div class=\"col-md-4 dcv-field\">" . $this->trans("ssl.type") . "</div>\n    <div class=\"col-md-8 dcv-value\">" . $method->type . "</div>\n</div>\n<div class=\"row py-2 dcv-property\">\n    <div class=\"col-md-4 dcv-field\">" . $this->trans("ssl.host") . "</div>\n    <div class=\"col-md-8 dcv-value\">" . $hostInput . "</div>\n</div>\n<div class=\"row py-2 dcv-property\">\n    <div class=\"col-md-4 dcv-field\">" . $this->trans("ssl.value") . "</div>\n    <div class=\"col-md-8 dcv-value\">" . $valueInput . "</div>\n</div>";
    }

    protected function renderCopyHelperInput($id, $value)
    {
        $WEB_ROOT = \DI::make("asset")->getWebRoot();
        return "<div class=\"input-group\">\n    <input type=\"text\" class=\"form-control\" id=\"" . $id . "\" value=\"" . $value . "\" readonly/>\n    <div class=\"input-group-btn input-group-append\">\n        <button type=\"button\" class=\"btn btn-default copy-to-clipboard\"\n            data-clipboard-target=\"#" . $id . "\">\n            <img src=\"" . $WEB_ROOT . "/assets/img/clippy.svg\" alt=\"Copy to clipboard\" width=\"15\">\n        </button>\n    </div>\n</div>";
    }
}
