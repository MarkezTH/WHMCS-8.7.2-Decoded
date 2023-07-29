<?php

namespace WHMCS\ClientArea\Account;

class AccountController
{
    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        return new \WHMCS\Http\RedirectResponse(\WHMCS\Utility\Environment\WebHelper::getBaseUrl() . "/clientarea.php?action=details");
    }

    public function users(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true);
        if (!\Auth::client()->authedUserIsOwner()) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        if (\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaPageUserManagement");
        $view->setPageTitle(\Lang::trans("userManagement.title"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("clientarea.php", \Lang::trans("clientareatitle"));
        $view->addToBreadCrumb("clientarea.php?action=details", \Lang::trans("clientareanavdetails"));
        $view->addToBreadCrumb(routePath("account-users"), \Lang::trans("userManagement.title"));
        $sidebarName = "clientView";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        return $view->setTemplate("account-user-management")->setTemplateVariables(["users" => \Auth::client()->users()->get(), "invites" => \Auth::client()->invites()->pending()->get(), "permissions" => $this->getPermissionDefintions(), "formdata" => ["inviteemail" => getLastInput("inviteemail")]]);
    }

    public function userPermissions(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true);
        if (!\Auth::client()->authedUserIsOwner()) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        if (\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $userId = $request->attributes()->get("userid");
        $user = \Auth::client()->getAuthUserById($userId);
        if (!$user || $user->pivot->owner) {
            return new \WHMCS\Http\RedirectResponse(routePath("account-users"));
        }
        $view = new \WHMCS\ClientArea();
        $view->setPageTitle(\Lang::trans("userManagement.title"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("clientarea.php", \Lang::trans("clientareatitle"));
        $view->addToBreadCrumb("clientarea.php?action=details", \Lang::trans("clientareanavdetails"));
        $view->addToBreadCrumb(routePath("account-users"), \Lang::trans("userManagement.title"));
        $view->addToBreadCrumb("#", \Lang::trans("userManagement.permissions"));
        $sidebarName = "clientView";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        return $view->setTemplate("account-user-permissions")->setTemplateVariables(["user" => $user, "userPermissions" => $user->pivot->getPermissions(), "permissions" => $this->getPermissionDefintions()]);
    }

    public function saveUserPermissions(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-users");
        if (!\Auth::client()->authedUserIsOwner()) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        if (\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $userId = $request->attributes()->get("userid");
        $requestPerms = $request->request()->get("perms");
        $user = \Auth::client()->getAuthUserById($userId);
        if (!$user || $user->pivot->owner) {
            return new \WHMCS\Http\RedirectResponse(routePath("account-users"));
        }
        if (empty($requestPerms)) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users-permissions", $userId)))->withWarning(\Lang::trans("userManagement.noPermissionsSelected"))->withInput();
        }
        $permissions = \WHMCS\User\Permissions::set(array_keys($requestPerms));
        $pivotRelation = $user->pivot;
        $pivotRelation->setPermissions($permissions)->save();
        return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withSuccess(\Lang::trans("userManagement.permissionsUpdateSuccess"));
    }

    public function removeUser(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-users");
        if (!\Auth::client()->authedUserIsOwner()) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        if (\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $userId = $request->request()->get("userid");
        $user = \Auth::client()->getAuthUserById($userId);
        if (!$user || $user->pivot->owner) {
            return new \WHMCS\Http\RedirectResponse(routePath("account-users"));
        }
        try {
            $user->pivot->delete();
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withSuccess(\Lang::trans("userManagement.userRemoveSuccess"));
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function invite(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-users");
        if (!\Auth::client()->authedUserIsOwner()) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        if (\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $email = $request->request()->get("inviteemail");
        $permissions = $request->request()->get("permissions");
        $requestPerms = $request->request()->get("perms");
        try {
            $user = \WHMCS\User\User::username($email)->first();
            if ($user && \Auth::client()->users()->where("auth_user_id", $user->id)->exists()) {
                throw new \WHMCS\Exception\Authentication\InvalidClientRequested();
            }
            if (\Auth::client()->invites()->pending()->where("email", $email)->count()) {
                throw new \WHMCS\Exception\User\EmailAlreadyExists();
            }
            $validate = new \WHMCS\Validate();
            if (!$user && !$validate->validate("banneddomain", "email", "clientareaerrorbannedemail", "", $email)) {
                throw new \WHMCS\Exception\User\BannedEmail();
            }
            if ($permissions == "all") {
                $permissions = \WHMCS\User\Permissions::all();
            } else {
                if (empty($requestPerms)) {
                    throw new \WHMCS\Exception\User\PermissionsRequired();
                }
                $permissions = \WHMCS\User\Permissions::set(array_keys($requestPerms));
            }
            \WHMCS\User\User\UserInvite::new($email, $permissions, \Auth::client()->id);
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withSuccess(\Lang::trans("userManagement.inviteSentSuccess"));
        } catch (\WHMCS\Exception\Authentication\InvalidClientRequested $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withWarning(\Lang::trans("userManagement.alreadyLinked"));
        } catch (\WHMCS\Exception\User\EmailAlreadyExists $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withWarning(\Lang::trans("userManagement.alreadyInvited"));
        } catch (\WHMCS\Exception\User\BannedEmail $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withWarning(\Lang::trans("clientareaerrorbannedemail"));
        } catch (\WHMCS\Exception\User\PermissionsRequired $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withWarning(\Lang::trans("userManagement.noPermissionsSelected"))->withInput();
        } catch (\WHMCS\Exception\Validation\Required $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withError($e->getMessage())->withInput();
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withError(\Lang::trans("errorButTryAgain"))->withInput();
        }
    }

    public function inviteResend(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-users");
        if (!\Auth::client()->authedUserIsOwner()) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        if (\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $inviteId = $request->request()->get("inviteid");
        try {
            $invite = \Auth::client()->invites()->where("id", $inviteId)->first();
            if (!$invite) {
                throw new \WHMCS\Exception("Invalid invite id requested");
            }
            $invite->notify();
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withSuccess(\Lang::trans("userManagement.inviteResendSuccess"));
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function inviteCancel(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-users");
        if (!\Auth::client()->authedUserIsOwner()) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        if (\WHMCS\Config\Setting::getValue("DisableClientAreaUserMgmt")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $inviteId = $request->request()->get("inviteid");
        try {
            $invite = \Auth::client()->invites()->where("id", $inviteId)->first();
            if (!$invite) {
                throw new \WHMCS\Exception("Invalid invite id requested");
            }
            $invite->cancel();
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withInfo(\Lang::trans("userManagement.inviteCancelled"));
        } catch (\Exception $e) {
            return (new \WHMCS\Http\RedirectResponse(routePath("account-users")))->withError(\Lang::trans("errorButTryAgain"));
        }
    }

    public function contacts(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true);
        if (!\Auth::hasPermission("contacts")) {
            return new \WHMCS\Http\RedirectResponse(routePath("user-permission-denied"));
        }
        $contactId = $request->get("contactid");
        $view = new \WHMCS\ClientArea();
        $view->addOutputHookFunction("ClientAreaPageContacts");
        $view->setPageTitle(\Lang::trans("clientareatitle"));
        $view->setDisplayTitle(\Lang::trans("clientareanavcontacts"));
        $view->addToBreadCrumb("index.php", \Lang::trans("globalsystemname"));
        $view->addToBreadCrumb("clientarea.php", \Lang::trans("clientareatitle"));
        $view->addToBreadCrumb("clientarea.php?action=details", \Lang::trans("clientareanavdetails"));
        $view->addToBreadCrumb(routePath("account-contacts"), \Lang::trans("clientareanavcontacts"));
        $sidebarName = "clientView";
        \Menu::primarySidebar($sidebarName);
        \Menu::secondarySidebar($sidebarName);
        $legacyClient = new \WHMCS\Client(\Auth::client());
        $contacts = $legacyClient->getContacts();
        $contactData = NULL;
        if ($contactId == "new") {
            $template = "account-contacts-new";
            $contactId = "";
            $contactData = [];
        } else {
            if ($contactId) {
                $template = "account-contacts-manage";
                $contactData = $legacyClient->getContact($contactId);
            }
        }
        if (!is_array($contactData)) {
            $contactId = $contacts[0]["id"];
            if ($contactId) {
                $template = "account-contacts-manage";
                $contactData = $legacyClient->getContact($contactId);
            } else {
                $template = "account-contacts-new";
                $contactId = "";
                $contactData = [];
            }
        }
        $emailLastInput = getLastInput("email_preferences");
        $emailPreferences = [];
        foreach (\WHMCS\Mail\Emailer::CLIENT_EMAILS as $emailType) {
            if ($emailType !== \WHMCS\Mail\Emailer::EMAIL_TYPE_AFFILIATE) {
                $emailPreferences[$emailType] = $emailLastInput ? $emailLastInput[$emailType] : $contactData[$emailType . "emails"];
            }
        }
        require_once ROOTDIR . "/includes/clientfunctions.php";
        $country = getLastInput("country") ?: $contactData["country"];
        return $view->setTemplate($template)->setTemplateVariables(["contacts" => $legacyClient->getContacts(), "contactid" => $contactId, "contactData" => $contactData, "countriesdropdown" => getCountriesDropDown($country, "", "", false), "errorMessageHtml" => \WHMCS\Session::getAndDelete("contactErrorFeedback"), "formdata" => ["firstname" => getLastInput("firstname") ?: $contactData["firstname"], "lastname" => getLastInput("lastname") ?: $contactData["lastname"], "companyname" => getLastInput("companyname") ?: $contactData["companyname"], "email" => getLastInput("email") ?: $contactData["email"], "phonenumber" => getLastInput("phonenumber") ?: $contactData["phonenumber"], "address1" => getLastInput("address1") ?: $contactData["address1"], "address2" => getLastInput("address2") ?: $contactData["address2"], "city" => getLastInput("city") ?: $contactData["city"], "state" => getLastInput("state") ?: $contactData["state"], "postcode" => getLastInput("postcode") ?: $contactData["postcode"], "country" => $country, "tax_id" => getLastInput("tax_id") ?: $contactData["tax_id"], "emailPreferences" => $emailPreferences], "taxIdLabel" => \WHMCS\Billing\Tax\Vat::getLabel(), "showTaxIdField" => \WHMCS\Billing\Tax\Vat::isUsingNativeField(true)]);
    }

    public function contactSave(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-contacts");
        $contactId = (int) $request->request()->get("contactid", 0);
        $legacyClient = new \WHMCS\Client(\Auth::client());
        require_once ROOTDIR . "/includes/clientfunctions.php";
        $validate = validateContactDetails($contactId);
        if ($validate->hasErrors()) {
            $errors = $validate->getHTMLErrorOutput();
            \WHMCS\Session::set("contactErrorFeedback", $errors);
            return (new \WHMCS\Http\RedirectResponse(routePathWithQuery("account-contacts", [], ["contactid" => $contactId])))->withInput();
        }
        $oldcontactdata = get_query_vals("tblcontacts", "", ["userid" => $legacyClient->getID(), "id" => $id]);
        $array = db_build_update_array(["firstname", "lastname", "companyname", "email", "address1", "address2", "city", "state", "postcode", "country", "phonenumber", "tax_id"], "implode");
        $emailPreferences = $request->request()->get("email_preferences");
        foreach (\WHMCS\Mail\Emailer::CLIENT_EMAILS as $emailType) {
            if ($emailType !== \WHMCS\Mail\Emailer::EMAIL_TYPE_AFFILIATE) {
                $array[$emailType . "emails"] = $emailPreferences[$emailType];
            }
        }
        if ($array["phonenumber"]) {
            $array["phonenumber"] = $phonenumber = \App::formatPostedPhoneNumber();
        }
        update_query("tblcontacts", $array, ["userid" => $legacyClient->getID(), "id" => $contactId]);
        run_hook("ContactEdit", array_merge(["userid" => $legacyClient->getID(), "contactid" => $contactId, "olddata" => $oldcontactdata], $array));
        logActivity("Client Contact Modified - User ID: " . $legacyClient->getID() . " - Contact ID: " . $contactId);
        clearLastInput();
        return (new \WHMCS\Http\RedirectResponse(routePathWithQuery("account-contacts", [], ["contactid" => $contactId])))->withSuccess(\Lang::trans("contactUpdated"));
    }

    public function contactNew(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-contacts");
        require_once ROOTDIR . "/includes/clientfunctions.php";
        $validate = validateContactDetails(0);
        if ($validate->hasErrors()) {
            $errors = $validate->getHTMLErrorOutput();
            \WHMCS\Session::set("contactErrorFeedback", $errors);
            return (new \WHMCS\Http\RedirectResponse(routePathWithQuery("account-contacts", [], ["contactid" => "new"])))->withInput();
        }
        $emailPreferences = $request->request()->get("email_preferences");
        $newContactId = addContact(\Auth::client()->id, $request->request()->get("firstname"), $request->request()->get("lastname"), $request->request()->get("companyname"), $request->request()->get("email"), $request->request()->get("address1"), $request->request()->get("address2"), $request->request()->get("city"), $request->request()->get("state"), $request->request()->get("postcode"), $request->request()->get("country"), $request->request()->get("phonenumber"), $emailPreferences["general"], $emailPreferences["product"], $emailPreferences["domain"], $emailPreferences["invoice"], $emailPreferences["support"], "", $request->request()->get("tax_id"));
        clearLastInput();
        return (new \WHMCS\Http\RedirectResponse(routePathWithQuery("account-contacts", [], ["contactid" => $newContactId])))->withSuccess(\Lang::trans("contactCreated"));
    }

    public function contactDelete(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::requireLoginAndClient(true, "account-contacts");
        $contactId = $request->request()->get("contactid");
        $legacyClient = new \WHMCS\Client(\Auth::client());
        $legacyClient->deleteContact($contactId);
        return (new \WHMCS\Http\RedirectResponse(routePath("account-contacts")))->withSuccess(\Lang::trans("contactDeleted"));
    }

    protected function getPermissionDefintions()
    {
        $permissionDefinitions = [];
        foreach (\WHMCS\User\Permissions::getAllPermissions() as $permission) {
            $permissionDefinitions[] = ["key" => $permission, "title" => \Lang::trans("subaccountperms" . $permission), "description" => \Lang::trans("permissions.descriptions." . $permission)];
        }
        return $permissionDefinitions;
    }
}
