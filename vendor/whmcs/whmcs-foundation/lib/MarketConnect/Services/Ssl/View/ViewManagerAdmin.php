<?php

namespace WHMCS\MarketConnect\Services\Ssl\View;

class ViewManagerAdmin extends ViewManager
{
    public function getTranslator($AbstractLanguage)
    {
        return \AdminLang::self();
    }

    public function renderDomainControlValidation()
    {
        $method = $this->ssl->authenticationData;
        return "<style type=\"text/css\">\ntable#view-domain-control-validation tr td {\n    padding: 8px;\n}\n</style>\n\n<table id=\"view-domain-control-validation\" class=\"table\">\n    <tbody>\n        <tr>\n            <th scope=\"row\">" . $this->trans("global.method") . "</th>\n            <td>" . $this->trans($method->translationKey($this->lang)) . "</td>\n        </tr>\n        " . $this->renderDomainControlValidationMethodPartial($method) . "\n    </tbody>\n</table>";
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
        $domain = strtolower($this->trans("fields.domain"));
        return "<tr>\n    <th scope=\"row\">" . $this->trans("wizard.ssl.url") . "</th>\n    <td>http://&lt;" . $domain . "&gt;/" . $method->filePath() . "</td>\n</tr>\n<tr>\n    <th scope=\"row\">" . $this->trans("wizard.ssl.value") . "</th>\n    <td>" . $contentInput . "</td>\n</tr>";
    }

    public function renderDomainControlValidationMethodPartialEmailauth($method)
    {
        $email = $method->email ?: $this->trans("ssl.defaultcontacts");
        return "<tr>\n    <th scope=\"row\">" . $this->trans("fields.email") . "</th>\n    <td>" . $email . "</td>\n</tr>";
    }

    public function renderDomainControlValidationMethodPartialDnsauth($method)
    {
        $hostInput = $this->renderCopyHelperInput("dcv-dns-host", $method->host);
        $valueInput = $this->renderCopyHelperInput("dcv-dns-value", $method->value);
        return "<tr>\n    <th scope=\"row\">" . $this->trans("wizard.ssl.type") . "</th>\n    <td>" . $method->type . "</td>\n</tr>\n<tr>\n    <th scope=\"row\">" . $this->trans("wizard.ssl.host") . "</th>\n    <td>" . $hostInput . "</td>\n</tr>\n<tr>\n    <th scope=\"row\">" . $this->trans("wizard.ssl.value") . "</th>\n    <td>" . $valueInput . "</td>\n</tr>";
    }

    protected function renderCopyHelperInput($id, $value)
    {
        $WEB_ROOT = \DI::make("asset")->getWebRoot();
        return "<div class=\"input-group\">\n    <input type=\"text\" class=\"form-control\" id=\"" . $id . "\" value=\"" . $value . "\" readonly/>\n    <div class=\"input-group-btn input-group-append\">\n        <button type=\"button\" class=\"btn btn-default copy-to-clipboard\"\n         data-clipboard-target=\"#" . $id . "\">\n            <img src=\"" . $WEB_ROOT . "/assets/img/clippy.svg\" alt=\"Copy to clipboard\" width=\"15\">\n        </button>\n    </div>\n</div>";
    }
}
