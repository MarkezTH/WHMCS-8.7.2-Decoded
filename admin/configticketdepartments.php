<?php

define("ADMINAREA", true);
require "../init.php";
$adminInterface = new WHMCS\Admin("Configure Support Departments");
$adminInterface->title = AdminLang::trans("supportticketdepts.supportticketdeptstitle");
$adminInterface->sidebar = "config";
$adminInterface->icon = "logs";
$adminInterface->helplink = "Support Departments";
$adminInterface->requireAuthConfirmation();
$sub = App::getFromRequest("sub");
$action = App::getFromRequest("action");
$id = (int) App::getFromRequest("id");
$email = App::getFromRequest("email");
$name = (string) App::getFromRequest("name");
$description = (string) App::getFromRequest("description");
$clientsonly = App::getFromRequest("clientsonly");
$piperepliesonly = App::getFromRequest("piperepliesonly");
$noautoresponder = App::getFromRequest("noautoresponder");
$hidden = App::getFromRequest("hidden");
$host = App::getFromRequest("host");
$port = (int) App::getFromRequest("port");
$login = App::getFromRequest("login");
$password = App::getFromRequest("password");
$admins = App::getFromRequest("admins") ?: [];
$feedbackRequest = (int) (bool) App::getFromRequest("feedbackrequest");
$serviceProvider = App::getFromRequest("service_provider");
$authType = App::getFromRequest("auth_type");
$oauth2ClientId = App::getFromRequest("oauth2_client_id");
$oauth2ClientSecret = App::getFromRequest("oauth2_client_secret");
$oauth2RefreshToken = App::getFromRequest("oauth2_refresh_token");
if ($sub == "add") {
    check_token("WHMCS.admin.default");
    if ($email == "") {
        infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.emailreqdfordept"));
        $action = "add";
    }
    if ($name == "") {
        infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.namereqdfordept"));
        $action = "add";
    }
    if (0 < WHMCS\User\Admin::whereEmail($email)->whereDisabled(0)->count()) {
        infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.emailCannotBeAdmin"));
        $action = "add";
    }
    if (!$infobox) {
        $order = WHMCS\Support\Department::orderBy("order", "DESC")->value("order") ?? 0;
        $order++;
        $newDepartment = new WHMCS\Support\Department();
        $newDepartment->name = $name;
        $newDepartment->description = $description;
        $newDepartment->email = trim($email);
        $newDepartment->clientsOnly = $clientsonly;
        $newDepartment->pipeRepliesOnly = $piperepliesonly;
        $newDepartment->noAutoResponder = $noautoresponder;
        $newDepartment->hidden = $hidden;
        $newDepartment->order = $order;
        $newDepartment->host = trim($host);
        $newDepartment->port = trim($port);
        $newDepartment->login = trim($login);
        $newDepartment->password = trim(WHMCS\Input\Sanitize::decode($password));
        $newDepartment->feedbackRequest = $feedbackRequest;
        $newDepartment->mailAuthConfig = ["service_provider" => $serviceProvider, "auth_type" => $authType, "oauth2_client_id" => $oauth2ClientId, "oauth2_client_secret" => $oauth2ClientSecret, "oauth2_refresh_token" => $oauth2RefreshToken];
        if (!empty($newDepartment->login) && !empty($newDepartment->port)) {
            try {
                $mailbox = WHMCS\Mail\Incoming\Mailbox::createForDepartment($newDepartment);
                $mailCount = $mailbox->getMessageCount();
            } catch (Throwable $e) {
                infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.pop3testconnectionerror") . ": " . $e->getMessage());
                $action = "add";
            }
        }
    }
    if (!$infobox) {
        $newDepartment->save();
        $id = $newDepartment->id;
        if (WHMCS\Config\Setting::getValue("EnableTranslations")) {
            WHMCS\Language\DynamicTranslation::saveNewTranslations($id, ["ticket_department.{id}.name", "ticket_department.{id}.description"]);
        }
        foreach (WHMCS\User\Admin::where("disabled", 0)->get() as $adminUser) {
            $newDepartment->configureAdmins($adminUser, $admins);
            $adminUser->save();
        }
        logAdminActivity("Support Department Created: '" . $name . "' - Support Department ID: " . $id);
        redir("createsuccess=1");
    }
}
if ($sub == "save") {
    check_token("WHMCS.admin.default");
    if ($email == "") {
        infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.emailreqdfordept"));
        $action = "edit";
    }
    if ($name == "") {
        infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.namereqdfordept"));
        $action = "edit";
    }
    if (0 < WHMCS\User\Admin::whereEmail($email)->whereDisabled(0)->count()) {
        infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.emailCannotBeAdmin"));
        $action = "edit";
    }
    if (!$infobox) {
        $supportDepartment = WHMCS\Support\Department::find($id);
        if (!$supportDepartment) {
            throw new WHMCS\Exception\ProgramExit("Invalid Department Id");
        }
        $supportDepartment->name = $name;
        $supportDepartment->description = $description;
        $supportDepartment->email = $email;
        $supportDepartment->clientsOnly = $clientsonly;
        $supportDepartment->pipeRepliesOnly = $piperepliesonly;
        $supportDepartment->noAutoResponder = $noautoresponder;
        $supportDepartment->hidden = $hidden;
        $supportDepartment->host = $host;
        $supportDepartment->port = $port;
        $supportDepartment->login = $login;
        $supportDepartment->feedbackRequest = $feedbackRequest;
        if ($supportDepartment->isDirty("name")) {
            logAdminActivity("Support Department Modified: " . "Name Changed: '" . $supportDepartment->getOriginal("name") . "' to '" . $supportDepartment->name . "' - Support Department ID: " . $id);
        }
        $authDataChanged = array_merge($supportDepartment->mailAuthConfig, ["service_provider" => $serviceProvider, "auth_type" => $authType]);
        if ($authType === WHMCS\Mail\MailAuthHandler::AUTH_TYPE_PLAIN) {
            $newPassword = trim(WHMCS\Input\Sanitize::decode(App::getFromRequest("password")));
            $originalPassword = $supportDepartment->password;
            if (interpretMaskedPasswordChangeForStorage($newPassword, $originalPassword) !== false) {
                $supportDepartment->password = $newPassword;
            }
            $authDataChanged["oauth2_client_id"] = "";
            $authDataChanged["oauth2_client_secret"] = "";
            $authDataChanged["oauth2_refresh_token"] = "";
        } else {
            if ($authType === WHMCS\Mail\MailAuthHandler::AUTH_TYPE_OAUTH2) {
                $authDataChanged["oauth2_client_id"] = $oauth2ClientId;
                foreach (["oauth2_client_secret", "oauth2_refresh_token"] as $mailAuthData) {
                    $newPassword = trim(WHMCS\Input\Sanitize::decode(App::getFromRequest($mailAuthData)));
                    $originalPassword = $supportDepartment->mailAuthConfig[$mailAuthData];
                    if (interpretMaskedPasswordChangeForStorage($newPassword, $originalPassword)) {
                        $authDataChanged[$mailAuthData] = $newPassword;
                    }
                }
                $handler = new WHMCS\Mail\MailAuthHandler();
                $provider = $handler->createProvider($serviceProvider, $oauth2ClientId, $oauth2ClientSecret, WHMCS\Mail\MailAuthHandler::CONTEXT_SUPPORT_DEPARTMENT);
                $provider->clearOpposingAuthData($supportDepartment);
            }
        }
        if (array_diff_assoc($supportDepartment->mailAuthConfig, $authDataChanged)) {
            $supportDepartment->mailAuthConfig = array_merge($supportDepartment->mailAuthConfig, $authDataChanged);
        }
        if (!empty($supportDepartment->host) && !empty($supportDepartment->port) && !empty($supportDepartment->login)) {
            try {
                $mailbox = WHMCS\Mail\Incoming\Mailbox::createForDepartment($supportDepartment);
                $mailCount = $mailbox->getMessageCount();
            } catch (Throwable $e) {
                infoBox(AdminLang::trans("global.validationerror"), AdminLang::trans("supportticketdepts.pop3testconnectionerror") . ": " . $e->getMessage());
                $action = "edit";
            }
        }
    }
    if (!$infobox) {
        if ($supportDepartment->isDirty()) {
            logAdminActivity("Support Department Modified: '" . $name . "' - Configuration Modified - Support Department ID: " . $id);
            $supportDepartment->save();
        }
        foreach (WHMCS\User\Admin::where("disabled", 0)->get() as $adminUser) {
            $supportDepartment->configureAdmins($adminUser, $admins);
            $adminUser->save();
        }
        $customfieldname = App::getFromRequest("customfieldname") ?: [];
        $customfieldtype = App::getFromRequest("customfieldtype") ?: [];
        $customfielddesc = App::getFromRequest("customfielddesc") ?: [];
        $customfieldoptions = App::getFromRequest("customfieldoptions") ?: [];
        $customfieldregexpr = App::getFromRequest("customfieldregexpr") ?: [];
        $customadminonly = self::getFromRequest("customadminonly") ?: [];
        $customrequired = App::getFromRequest("customrequired") ?: [];
        $customshoworder = App::getFromRequest("customshoworder") ?: [];
        $customsortorder = App::getFromRequest("customsortorder") ?: [];
        if ($customfieldname) {
            foreach ($customfieldname as $fieldId => $value) {
                $customField = WHMCS\CustomField::find($fieldId);
                if ($customField->fieldName != $value) {
                    logAdminActivity("Support Department Modified: " . "Custom Field Modified: Name Changed: '" . $customField->fieldname . "' to '" . $value . "'" . " - Support Department ID: " . $id);
                }
                if ($customField->fieldType != $customfieldtype[$fieldId] || $customField->description != $customfielddesc[$fieldId] || $customField->fieldOptions != $customfieldoptions[$fieldId] || $customField->regularExpression != $customfieldregexpr[$fieldId] || $customField->adminOnly != $customadminonly[$fieldId] || $customField->required != $customrequired[$fieldId] || $customField->showOnOrderForm != $customshoworder[$fieldId] || $customField->sortOrder != $customsortorder[$fieldId]) {
                    logAdminActivity("Support Department Modified: Custom Field Modified: '" . $value . "' - Support Department ID: " . $id);
                }
                $customField->fieldName = $value;
                $customField->fieldType = $customfieldtype[$fieldId];
                $customField->description = $customfielddesc[$fieldId];
                $customField->fieldOptions = !empty($customfieldoptions[$fieldId]) ? explode(",", $customfieldoptions[$fieldId]) : [];
                $customField->regularExpression = WHMCS\Input\Sanitize::decode($customfieldregexpr[$fieldId]);
                $customField->adminOnly = $customadminonly[$fieldId];
                $customField->required = $customrequired[$fieldId];
                $customField->showOnOrderForm = $customshoworder[$fieldId];
                $customField->sortOrder = $customsortorder[$fieldId];
                $customField->save();
            }
        }
        $addfieldname = App::getFromRequest("addfieldname");
        $addfieldtype = App::getFromRequest("addfieldtype");
        $addcfdesc = App::getFromRequest("addcfdesc");
        $addfieldoptions = explode(",", App::getFromRequest("addfieldoptions"));
        $addregexpr = App::getFromRequest("addregexpr");
        $addadminonly = App::getFromRequest("addadminonly");
        $addrequired = App::getFromRequest("addrequired");
        $addshoworder = App::getFromRequest("addshoworder");
        $addsortorder = (int) App::getFromRequest("addsortorder");
        if ($addfieldname) {
            $newCustomField = new WHMCS\CustomField();
            $newCustomField->type = WHMCS\CustomField::TYPE_SUPPORT;
            $newCustomField->relatedId = $id;
            $newCustomField->fieldName = $addfieldname;
            $newCustomField->fieldType = $addfieldtype;
            $newCustomField->description = $addcfdesc;
            $newCustomField->fieldOptions = !empty($addfieldoptions) ? $addfieldoptions : [];
            $newCustomField->regularExpression = WHMCS\Input\Sanitize::decode($addregexpr);
            $newCustomField->adminOnly = $addadminonly;
            $newCustomField->required = $addrequired;
            $newCustomField->showOnOrderForm = $addshoworder;
            $newCustomField->sortOrder = $addsortorder;
            $newCustomField->save();
            $newCustomFieldId = $newCustomField->id;
            if (WHMCS\Config\Setting::getValue("EnableTranslations")) {
                WHMCS\Language\DynamicTranslation::saveNewTranslations($newCustomFieldId, ["custom_field.{id}.name", "custom_field.{id}.description"]);
            }
            logAdminActivity("Support Department Modified: '" . $name . "'" . " - Custom Field Created: '" . $addfieldname . "' - Support Department ID: " . $id);
        }
        redir("savesuccess=1");
    }
}
if ($sub == "delete") {
    check_token("WHMCS.admin.default");
    $deptToDelete = WHMCS\Support\Department::findOrFail($id);
    $deletedDeptName = $deptToDelete->name;
    $deletedDeptOrder = $deptToDelete->order;
    $deptToDelete->delete();
    logAdminActivity("Support Department Deleted: '" . $deletedDeptName . "' - Support Department ID: " . $id);
    $deptToUpdateTo = WHMCS\Support\Department::orderBy("id")->first("id");
    WHMCS\Support\Ticket::query()->where("did", $id)->update(["did" => $deptToUpdateTo->id]);
    WHMCS\CustomField::where("type", WHMCS\CustomField::TYPE_SUPPORT)->where("relid", "=", $id)->delete();
    WHMCS\Support\Department::query()->where("order", ">", $deletedDeptOrder)->decrement("order", 1);
    redir("delsuccess=1");
}
if ($sub == "deletecustomfield") {
    check_token("WHMCS.admin.default");
    $customField = WHMCS\CustomField::where("type", WHMCS\CustomField::TYPE_SUPPORT)->where("id", $id)->first();
    if ($customField) {
        $customFieldRelId = $customField->relatedId;
        $customFieldName = $customField->fieldName;
        $customField->delete();
        $supportDepartment = WHMCS\Support\Department::find($customFieldRelId);
        logAdminActivity("Support Department Modified: '" . $supportDepartment->name . "'" . " - Custom Field Deleted: '" . $customFieldName . "'" . " - Support Department ID: " . $supportDepartment->id);
    }
    redir("savesuccess=1");
}
if ($sub == "moveup") {
    check_token("WHMCS.admin.default");
    $deptOrder = (int) App::getFromRequest("order");
    WHMCS\Support\Department::setDepartmentOrder($deptOrder, 1);
    redir();
}
if ($sub == "movedown") {
    check_token("WHMCS.admin.default");
    $deptOrder = (int) App::getFromRequest("order");
    WHMCS\Support\Department::setDepartmentOrder($deptOrder, -1);
    redir();
}
if (WHMCS\Config\Setting::getValue("EnableTranslations")) {
    WHMCS\Language\DynamicTranslation::whereIn("related_type", ["custom_field.{id}.name", "custom_field.{id}.description"])->where("related_id", "=", 0)->delete();
}
ob_start();
if (isset($createsuccess) && $createsuccess) {
    infoBox(AdminLang::trans("supportticketdepts.deptaddsuccess"), AdminLang::trans("supportticketdepts.deptaddsuccessdesc", [":icon" => "<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>"]));
}
if (isset($savesuccess) && $savesuccess) {
    infoBox(AdminLang::trans("supportticketdepts.changessavesuccess"), AdminLang::trans("supportticketdepts.changessavesuccessdesc"));
}
if (isset($delsuccess) && $delsuccess) {
    infoBox(AdminLang::trans("global.success"), "The selected support department was deleted successfully");
}
echo $infobox;
if ($action == "") {
    $adminInterface->deleteJSConfirm("doDelete", "supportticketdepts", "delsuredept", "?sub=delete&id=");
    $cronFolder = App::getCronDirectory();
    echo "\n    <p>";
    echo AdminLang::trans("supportticketdepts.supportticketdeptsconfigheredesc");
    echo "</p>\n\n    <div class=\"alert alert-warning text-center\">\n        <div class=\"input-group\">\n            <span class=\"input-group-addon\" id=\"emailPipe\">";
    echo AdminLang::trans("supportticketdepts.ticketimportusingef");
    echo "</span>\n            <input type=\"text\" id=\"emailPipe\" value=\" | ";
    echo WHMCS\Environment\Php::getPreferredCliBinary();
    echo " -q ";
    echo $cronFolder;
    echo "/pipe.php\" class=\"form-control\" onfocus=\"this.select()\" onmouseup=\"return false;\" />\n        </div>\n        <strong>";
    echo AdminLang::trans("global.or");
    echo "</strong><br />\n        <div class=\"input-group\">\n            <span class=\"input-group-addon\" id=\"emailPop\">";
    echo AdminLang::trans("supportticketdepts.ticketimportusingpop3imap");
    echo "</span>\n            <input type=\"text\" id=\"emailPop\" value=\"*/5 * * * * ";
    echo WHMCS\Environment\Php::getPreferredCliBinary();
    echo " -q ";
    echo $cronFolder;
    echo "/pop.php\" class=\"form-control\" onfocus=\"this.select()\" onmouseup=\"return false;\" />\n        </div>\n    </div>\n\n    <p><a id=\"addNewDepartment\" href=\"";
    echo App::getPhpSelf();
    echo "?action=add\" class=\"btn btn-default\"><i class=\"fas fa-plus-square\"></i> ";
    echo AdminLang::trans("supportticketdepts.addnewdept");
    echo "</a></p>\n\n    ";
    $adminInterface->sortableTableInit("nopagination");
    $departments = WHMCS\Support\Department::orderBy("order")->get();
    $lastOrder = $departments->isNotEmpty() ? $departments->last()->order : NULL;
    foreach ($departments as $department) {
        $hidden = AdminLang::trans("global.no");
        if ($department->hidden == "on") {
            $hidden = AdminLang::trans("global.yes");
        }
        $moveup = "";
        if ($department->order != "1") {
            $moveup = "<a href=\"?sub=moveup&order=" . $department->order . generate_token("link") . "\">\n                <img src=\"images/moveup.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("products.navmoveup") . "\">\n            </a>";
        }
        $movedown = "";
        if ($department->order != $lastOrder) {
            $movedown = "<a href=\"?sub=movedown&order=" . $department->order . generate_token("link") . "\">\n                <img src=\"images/movedown.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("products.navmovedown") . "\">\n            </a>";
        }
        $tabledata[] = [$department->name, $department->description, $department->email, $hidden, $moveup, $movedown, "<a href=\"?action=edit&id=" . $department->id . "\">\n                <img src=\"images/edit.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("global.edit") . "\">\n            </a>", "<a href=\"#\" onClick=\"doDelete(" . $department->id . ");return false\">\n                <img src=\"images/delete.gif\" width=\"16\" height=\"16\" border=\"0\" alt=\"" . AdminLang::trans("global.delete") . "\">\n            </a>"];
    }
    echo $adminInterface->sortableTable([AdminLang::trans("supportticketdepts.deptname"), AdminLang::trans("fields.description"), AdminLang::trans("supportticketdepts.deptemail"), AdminLang::trans("global.hidden"), "", "", "", ""], $tabledata);
} else {
    if ($action == "edit") {
        if (!$infobox) {
            $department = WHMCS\Support\Department::find($id);
            if (!$department) {
                throw new WHMCS\Exception\ProgramExit("Invalid Department Id");
            }
            $name = $department->getRawAttribute("name");
            $description = $department->getRawAttribute("description");
            $email = $department->email;
            $clientsonly = $department->clientsOnly;
            $piperepliesonly = $department->pipeRepliesOnly;
            $noautoresponder = $department->noAutoResponder;
            $hidden = $department->hidden;
            $host = $department->host;
            $port = $department->port;
            $login = $department->login;
            $password = $department->password;
            $feedbackRequest = $department->feedbackRequest;
            $serviceProvider = $department->mailAuthConfig["service_provider"];
            $authType = $department->mailAuthConfig["auth_type"];
            $oauth2ClientId = $department->mailAuthConfig["oauth2_client_id"];
            $oauth2ClientSecret = $department->mailAuthConfig["oauth2_client_secret"];
            $oauth2RefreshToken = $department->mailAuthConfig["oauth2_refresh_token"];
        }
        $adminInterface->deleteJSConfirm("deleteField", "supportticketdepts", "delsurefielddata", "?sub=deletecustomfield&id=");
        echo "\n    <h2>";
        echo AdminLang::trans("supportticketdepts.editdept");
        echo "</h2>\n\n    <form method=\"post\" action=\"";
        echo App::getPhpSelf();
        echo "?sub=save\" id=\"frmDepartmentConfiguration\">\n    <input type=\"hidden\" name=\"id\" value=\"";
        echo $id;
        echo "\">\n\n    ";
        echo $adminInterface->beginAdminTabs(["Details", "Custom Fields"], true);
        echo "\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td width=\"175px\" class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("supportticketdepts.deptname");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"name\" value=\"";
        echo $name;
        echo "\" class=\"form-control input-inline input-300\">\n            ";
        echo $adminInterface->getTranslationLink("ticket_department.name", $id);
        echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("fields.description");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"description\" value=\"";
        echo $description;
        echo "\" class=\"form-control input-inline input-80percent\">\n            ";
        echo $adminInterface->getTranslationLink("ticket_department.description", $id);
        echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("supportticketdepts.deptemail");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"email\" value=\"";
        echo $email;
        echo "\" class=\"form-control input-500\">\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("supportticketdepts.assignedadmins");
        echo "        </td>\n        <td class=\"fieldarea\">\n    ";
        foreach (WHMCS\User\Admin::orderBy("username")->get() as $admin) {
            echo "<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"admins[]\" value=\"" . $admin->id . "\"";
            if (in_array($id, $admin->supportDepartmentIds)) {
                echo " checked";
            }
            echo " />";
            if ($admin->isDisabled) {
                echo "<span class=\"disabledtext\">";
            }
            echo $admin->username . " (" . trim($admin->fullName) . ")";
            if ($admin->isDisabled) {
                echo " - " . AdminLang::trans("global.disabled") . "</span>";
            }
            echo "</label><br />";
        }
        echo "    </td></tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("supportticketdepts.clientsonly");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"clientsonly\"";
        if ($clientsonly == "on") {
            echo " checked";
        }
        echo "> ";
        echo AdminLang::trans("supportticketdepts.clientsonlydesc");
        echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("supportticketdepts.piperepliesonly");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"piperepliesonly\"";
        if ($piperepliesonly == "on") {
            echo " checked";
        }
        echo "> ";
        echo AdminLang::trans("supportticketdepts.ticketsclientareaonlydesc");
        echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("supportticketdepts.noautoresponder");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"noautoresponder\"";
        if ($noautoresponder == "on") {
            echo " checked";
        }
        echo "> ";
        echo AdminLang::trans("supportticketdepts.noautoresponderdesc");
        echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("supportticketdepts.feedbackRequest");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"feedbackrequest\"";
        if ($feedbackRequest) {
            echo " checked";
        }
        echo "> ";
        echo AdminLang::trans("supportticketdepts.feedbackRequestDescription");
        echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("global.hidden");
        echo "?\n        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"hidden\"";
        if ($hidden == "on") {
            echo " checked";
        }
        echo "> ";
        echo AdminLang::trans("supportticketdepts.hiddendesc");
        echo "            </label>\n        </td>\n    </tr>\n    </table>\n\n    ";
        echo view("admin.setup.support.pop3-setup", ["data" => ["departmentId" => $id, "host" => $host, "port" => $port, "login" => $login, "password" => $password, "service_provider" => $serviceProvider, "auth_type" => $authType, "oauth2_client_id" => $oauth2ClientId, "oauth2_client_secret" => $oauth2ClientSecret, "oauth2_refresh_token" => $oauth2RefreshToken]]);
        echo "\n    ";
        echo $adminInterface->nextAdminTab();
        echo "\n    ";
        $customFields = WHMCS\CustomField::where("type", WHMCS\CustomField::TYPE_SUPPORT)->where("relid", $id)->orderBy("sortorder")->orderBy("id")->get();
        foreach ($customFields as $customFieldData) {
            $fieldId = $customFieldData->id;
            $fieldName = $customFieldData->fieldName;
            $fieldType = $customFieldData->fieldType;
            $fieldDesc = $customFieldData->description;
            $fieldOptions = implode(", ", $customFieldData->fieldOptions);
            $fieldRegEx = $customFieldData->regularExpression;
            $adminOnly = $customFieldData->adminOnly;
            $required = $customFieldData->required;
            $sortOrder = $customFieldData->sortOrder;
            echo "    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
            echo AdminLang::trans("customfields.fieldname");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"customfieldname[";
            echo $fieldId;
            echo "]\" value=\"";
            echo $fieldName;
            echo "\" class=\"form-control input-400 input-inline\">\n            ";
            echo $adminInterface->getTranslationLink("custom_field.name", $fieldId, WHMCS\CustomField::TYPE_SUPPORT);
            echo "            <div class=\"pull-right\">\n                ";
            echo AdminLang::trans("customfields.order");
            echo "                <input type=\"text\" name=\"customsortorder[";
            echo $fieldId;
            echo "]\" value=\"";
            echo $sortOrder;
            echo "\" class=\"form-control input-100 input-inline text-center\">\n            </div>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
            echo AdminLang::trans("customfields.fieldtype");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <select name=\"customfieldtype[";
            echo $fieldId;
            echo "]\" class=\"form-control select-inline\">\n                <option value=\"text\"";
            if ($fieldType == "text") {
                echo " selected";
            }
            echo ">";
            echo AdminLang::trans("customfields.typetextbox");
            echo "</option>\n                <option value=\"link\"";
            if ($fieldType == "link") {
                echo " selected";
            }
            echo ">";
            echo AdminLang::trans("customfields.typelink");
            echo "</option>\n                <option value=\"password\"";
            if ($fieldType == "password") {
                echo " selected";
            }
            echo ">";
            echo AdminLang::trans("customfields.typepassword");
            echo "</option>\n                <option value=\"dropdown\"";
            if ($fieldType == "dropdown") {
                echo " selected";
            }
            echo ">";
            echo AdminLang::trans("customfields.typedropdown");
            echo "</option>\n                <option value=\"tickbox\"";
            if ($fieldType == "tickbox") {
                echo " selected";
            }
            echo ">";
            echo AdminLang::trans("customfields.typetickbox");
            echo "</option>\n                <option value=\"textarea\"";
            if ($fieldType == "textarea") {
                echo " selected";
            }
            echo ">";
            echo AdminLang::trans("customfields.typetextarea");
            echo "</option>\n            </select>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
            echo AdminLang::trans("fields.description");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"customfielddesc[";
            echo $fieldId;
            echo "]\" value=\"";
            echo $fieldDesc;
            echo "\" class=\"form-control input-500 input-inline\">\n            ";
            echo $adminInterface->getTranslationLink("custom_field.description", $fieldId, WHMCS\CustomField::TYPE_SUPPORT);
            echo "            ";
            echo AdminLang::trans("customfields.descriptioninfo");
            echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
            echo AdminLang::trans("customfields.validation");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"customfieldregexpr[";
            echo $fieldId;
            echo "]\" value=\"";
            echo WHMCS\Input\Sanitize::encode($fieldRegEx);
            echo "\" class=\"form-control input-500 input-inline\"> ";
            echo AdminLang::trans("customfields.validationinfo");
            echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
            echo AdminLang::trans("customfields.selectoptions");
            echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"customfieldoptions[";
            echo $fieldId;
            echo "]\" value=\"";
            echo $fieldOptions;
            echo "\" class=\"form-control input-500 input-inline\"> ";
            echo AdminLang::trans("customfields.selectoptionsinfo");
            echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\"></td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"customadminonly[";
            echo $fieldId;
            echo "]\"";
            if ($adminOnly == "on") {
                echo " checked";
            }
            echo ">\n                ";
            echo AdminLang::trans("customfields.adminonly");
            echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"customrequired[";
            echo $fieldId;
            echo "]\"";
            if ($required == "on") {
                echo " checked";
            }
            echo ">\n                ";
            echo AdminLang::trans("customfields.requiredfield");
            echo "            </label>\n            <div class=\"pull-right\">\n                <a href=\"#\" onClick=\"deleteField('";
            echo $fieldId;
            echo "');return false\" class=\"btn btn-danger btn-xs\">";
            echo AdminLang::trans("customfields.deletefield");
            echo "</a>\n            </div>\n        </td>\n    </tr>\n    </table><br>\n    ";
        }
        echo "    <b>";
        echo AdminLang::trans("customfields.addfield");
        echo "</b><br><br>\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("customfields.fieldname");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"addfieldname\" class=\"form-control input-400 input-inline\">\n            ";
        echo $adminInterface->getTranslationLink("custom_field.name", 0, "support");
        echo "            <div class=\"pull-right\">\n                ";
        echo AdminLang::trans("customfields.order");
        echo "                <input type=\"text\" name=\"addsortorder\" class=\"form-control input-100 input-inline text-center\" value=\"0\" />\n            </div>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("customfields.fieldtype");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <select name=\"addfieldtype\" class=\"form-control select-inline\">\n                <option value=\"text\">";
        echo AdminLang::trans("customfields.typetextbox");
        echo "</option>\n                <option value=\"link\">";
        echo AdminLang::trans("customfields.typelink");
        echo "</option>\n                <option value=\"password\">";
        echo AdminLang::trans("customfields.typepassword");
        echo "</option>\n                <option value=\"dropdown\">";
        echo AdminLang::trans("customfields.typedropdown");
        echo "</option>\n                <option value=\"tickbox\">";
        echo AdminLang::trans("customfields.typetickbox");
        echo "</option>\n                <option value=\"textarea\">";
        echo AdminLang::trans("customfields.typetextarea");
        echo "</option>\n            </select>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("fields.description");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"addcfdesc\" class=\"form-control input-500 input-inline\">\n            ";
        echo $adminInterface->getTranslationLink("custom_field.description", 0, "support");
        echo "            ";
        echo AdminLang::trans("customfields.descriptioninfo");
        echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
        echo AdminLang::trans("customfields.validation");
        echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"addregexpr\" class=\"form-control input-500 input-inline\"> ";
        echo AdminLang::trans("customfields.validationinfo");
        echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">Select Options</td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"addfieldoptions\" class=\"form-control input-500 input-inline\"> ";
        echo AdminLang::trans("customfields.selectoptionsinfo");
        echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\"></td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"addadminonly\">\n                ";
        echo AdminLang::trans("customfields.adminonly");
        echo "            </label>\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"addrequired\">\n                ";
        echo AdminLang::trans("customfields.requiredfield");
        echo "            </label>\n        </td>\n    </tr>\n    </table>\n\n    ";
        echo $adminInterface->endAdminTabs();
        echo "\n    <div class=\"btn-container\">\n        <input type=\"submit\" value=\"";
        echo AdminLang::trans("global.savechanges");
        echo "\" class=\"btn btn-primary\">\n        <input type=\"button\" value=\"";
        echo AdminLang::trans("global.cancel");
        echo "\" onClick=\"window.location='";
        echo App::getPhpSelf();
        echo "'\" class=\"btn btn-default\">\n    </div>\n\n    </form>\n\n    ";
    }
}
if ($action == "add") {
    if (empty($port)) {
        $port = 995;
    }
    echo "\n    <h2>";
    echo AdminLang::trans("supportticketdepts.addnewdept");
    echo "</h2>\n\n    <form method=\"post\" action=\"";
    echo App::getPhpSelf();
    echo "?sub=add\" autocomplete=\"off\" id=\"frmDepartmentConfiguration\">\n\n    <table class=\"form\" width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n    <tr>\n        <td width=\"175px\" class=\"fieldlabel\">";
    echo AdminLang::trans("supportticketdepts.deptname");
    echo "</td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"name\" value=\"";
    echo $name;
    echo "\" class=\"form-control input-300 input-inline\">\n            ";
    echo $adminInterface->getTranslationLink("ticket_department.name", 0);
    echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">";
    echo AdminLang::trans("fields.description");
    echo "</td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"description\" value=\"";
    echo $description;
    echo "\" class=\"form-control input-80percent input-inline\">\n            ";
    echo $adminInterface->getTranslationLink("ticket_department.description", 0);
    echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("supportticketdepts.deptemail");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <input type=\"text\" name=\"email\" value=\"";
    echo $email;
    echo "\" class=\"form-control input-500\">\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("supportticketdepts.assignedadmins");
    echo "        </td>\n        <td class=\"fieldarea\">\n            ";
    foreach (WHMCS\User\Admin::orderBy("username")->get() as $admin) {
        echo "<label class=\"checkbox-inline\"><input type=\"checkbox\" name=\"admins[]\" value=\"" . $admin->id . "\"";
        if (in_array($id, $admin->supportDepartmentIds)) {
            echo " checked";
        }
        echo " />";
        if ($admin->isDisabled) {
            echo "<span class=\"disabledtext\">";
        }
        echo $admin->username . " (" . trim($admin->fullName) . ")";
        if ($admin->isDisabled) {
            echo " - " . AdminLang::trans("global.disabled") . "</span>";
        }
        echo "</label><br />";
    }
    echo "        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("supportticketdepts.clientsonly");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"clientsonly\"";
    if ($clientsonly == "on") {
        echo " checked";
    }
    echo "> ";
    echo AdminLang::trans("supportticketdepts.clientsonlydesc");
    echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("supportticketdepts.piperepliesonly");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"piperepliesonly\"";
    if ($piperepliesonly == "on") {
        echo " checked";
    }
    echo "> ";
    echo AdminLang::trans("supportticketdepts.ticketsclientareaonlydesc");
    echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("supportticketdepts.noautoresponder");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"noautoresponder\"";
    if ($noautoresponder == "on") {
        echo " checked";
    }
    echo "> ";
    echo AdminLang::trans("supportticketdepts.noautoresponderdesc");
    echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("supportticketdepts.feedbackRequest");
    echo "        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"feedbackrequest\"";
    if ($feedbackRequest) {
        echo " checked";
    }
    echo " value=\"1\"> ";
    echo AdminLang::trans("supportticketdepts.feedbackRequestDescription");
    echo "            </label>\n        </td>\n    </tr>\n    <tr>\n        <td class=\"fieldlabel\">\n            ";
    echo AdminLang::trans("global.hidden");
    echo "?\n        </td>\n        <td class=\"fieldarea\">\n            <label class=\"checkbox-inline\">\n                <input type=\"checkbox\" name=\"hidden\"";
    if ($hidden == "on") {
        echo " checked";
    }
    echo "> ";
    echo AdminLang::trans("supportticketdepts.hiddendesc");
    echo "            </label>\n        </td>\n    </tr>\n    </table>\n\n    ";
    echo view("admin.setup.support.pop3-setup", ["data" => ["departmentId" => 0, "host" => $host, "port" => $port, "login" => $login, "password" => $password, "service_provider" => $serviceProvider, "auth_type" => $authType, "oauth2_client_id" => $oauth2ClientId, "oauth2_client_secret" => $oauth2ClientSecret, "oauth2_refresh_token" => $oauth2RefreshToken]]);
    echo "\n    <div class=\"btn-container\">\n        <input type=\"submit\" value=\"";
    echo AdminLang::trans("supportticketdepts.addnewdept");
    echo "\" class=\"btn btn-primary\">\n        <input type=\"button\" value=\"";
    echo AdminLang::trans("global.cancel");
    echo "\" onClick=\"window.location='";
    echo App::getPhpSelf();
    echo "'\" class=\"btn btn-default\" />\n    </div>\n\n    </form>\n\n    ";
}
$content = ob_get_contents();
ob_end_clean();
$adminInterface->content = $content;
$adminInterface->jquerycode = $jquerycode;
$adminInterface->jscode = $jscode;
$adminInterface->display();
