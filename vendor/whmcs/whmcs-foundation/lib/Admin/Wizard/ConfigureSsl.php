<?php

namespace WHMCS\Admin\Wizard;

class ConfigureSsl extends Wizard
{
    protected $wizardName = "ConfigureSsl";

    public function __construct()
    {
        $this->steps = [["name" => "Csr", "stepName" => \AdminLang::trans("wizard.ssl.provideCsr"), "stepDescription" => \AdminLang::trans("wizard.ssl.certificateSigningRequest")], ["name" => "Contacts", "stepName" => \AdminLang::trans("wizard.ssl.contactInformation"), "stepDescription" => \AdminLang::trans("wizard.ssl.contactInformationDescription")], ["name" => "Approval", "stepName" => \AdminLang::trans("wizard.ssl.approvalMethod"), "stepDescription" => \AdminLang::trans("wizard.ssl.approvalMethodDescription")], ["name" => "Complete", "hidden" => true]];
    }

    public function hasRequiredAdminPermissions()
    {
        return \WHMCS\User\Admin\Permission::currentAdminHasPermissionName("Perform Server Operations");
    }
}
