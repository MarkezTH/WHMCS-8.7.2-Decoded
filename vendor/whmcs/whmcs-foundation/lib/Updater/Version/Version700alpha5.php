<?php

namespace WHMCS\Updater\Version;

class Version700alpha5 extends IncrementalVersion
{
    protected $updateActions = ["updateEnomDomainLookupProviderSetting", "removeToggleInfoPopup", "disableSetupWizardForUpgrades"];

    public function updateEnomDomainLookupProviderSetting()
    {
        if (strtolower(\WHMCS\Config\Setting::getValue("domainLookupProvider")) == "enom") {
            \WHMCS\Config\Setting::setValue("domainLookupProvider", "Registrar");
            \WHMCS\Config\Setting::setValue("domainLookupRegistrar", "enom");
        }
    }

    public function removeToggleInfoPopup()
    {
        $setting = \WHMCS\Config\Setting::find("ToggleInfoPopup");
        if ($setting) {
            $setting->delete();
        }
    }

    public function disableSetupWizardForUpgrades()
    {
        $companyName = \WHMCS\Config\Setting::getValue("CompanyName");
        $email = \WHMCS\Config\Setting::getValue("Email");
        $domain = \WHMCS\Config\Setting::getValue("Domain");
        $systemFrom = \WHMCS\Config\Setting::getValue("SystemEmailsFromEmail");
        if ($companyName != "Company Name" || $email != "changeme@changeme.com" || $domain != "http://www.yourdomain.com/" || $systemFrom != "noreply@yourdomain.com") {
            \WHMCS\Config\Setting::setValue("DisableSetupWizard", 1);
        }
    }
}
