<?php

$colspan = $manageUserPermission ? 3 : 2;
if (!empty($error)) {
    echo WHMCS\View\Helper::alert($error, "danger");
} else {
    echo "    ";
    if ($manageUserPermission) {
        echo "        <div class=\"context-btn-container\">\n            <div class=\"text-left\">\n                <a id=\"btnAddUser\"\n                   href=\"";
        echo routePath("admin-client-users-associate-start", $client->id);
        echo "\"\n                   data-modal-size=\"modal-lg\"\n                   data-modal-title=\"";
        echo AdminLang::trans("user.associateInviteUser");
        echo "\"\n                   data-btn-submit-id=\"btnAssociateUser\"\n                   data-btn-submit-label=\"";
        echo AdminLang::trans("user.inviteUser");
        echo "\"\n                   class=\"open-modal btn btn-default btn-sm\"\n                >\n                    <i class=\"fas fa-plus fa-fw\"></i>\n                    ";
        echo AdminLang::trans("user.associateUser");
        echo "                </a>\n            </div>\n        </div>\n    ";
    }
    echo "\n    <table class=\"hidden\">\n        <tr id=\"emptyRow\" class=\"empty-row\">\n            <td class=\"name\"><span class=\"name\"></span><br><span class=\"email\"></span></td>\n            <td class=\"last-login-time text-center\"></td>\n            ";
    if ($manageUserPermission || $securityQuestionsEnabled) {
        echo "                <td class=\"remove text-center\">\n                    <div class=\"btn-group\" style=\"margin-left:10px;\">\n                        <button type=\"button\"\n                                class=\"btn btn-default btn-permissions open-modal";
        echo $manageUserPermission ? "" : " disabled";
        echo "\"\n                                data-modal-title=\"";
        echo AdminLang::trans("user.manageUserEmail", [":email" => ""]);
        echo "\"\n                                href=\"\"\n                                data-modal-size=\"modal-lg\"\n                                data-btn-submit-label=\"";
        echo AdminLang::trans("global.save");
        echo "\"\n                                data-btn-submit-id=\"btnSetPermissions\"\n                        ";
        echo $manageUserPermission ? "" : "disabled=\"disabled\"";
        echo "\"\n                        >\n                        ";
        echo AdminLang::trans("user.manageUser");
        echo "                        </button>\n                        ";
        if ($manageUserPermission) {
            echo "                            <button type=\"button\"\n                                    class=\"btn btn-danger btn-remove\"\n                                    data-user-id=\"\"\n                            >\n                                ";
            echo AdminLang::trans("global.remove");
            echo "                            </button>\n                        ";
        }
        echo "                        <button type=\"button\"\n                                class=\"btn btn-default dropdown-toggle\"\n                                data-toggle=\"dropdown\"\n                                aria-haspopup=\"true\"\n                                aria-expanded=\"false\"\n                        >\n                            <span class=\"caret\"></span>\n                            <span class=\"sr-only\">Toggle Dropdown</span>\n                        </button>\n                        <ul class=\"dropdown-menu dropdown-menu-right\">\n                            <li>\n                                <a class=\"btn-reset";
        echo $manageUserPermission ? "" : " disabled";
        echo "\"\n                                   data-user-id=\"\"\n                                   href=\"\"\n                                    ";
        echo $manageUserPermission ? "" : "disabled=\"disabled\"";
        echo "                                >\n                                    ";
        echo AdminLang::trans("user.passwordReset");
        echo "                                </a>\n                            </li>\n                            ";
        if ($securityQuestionsEnabled) {
            echo "                                <li class=\"\">\n                                    <a class=\"open-modal\"\n                                       data-modal-title=\"";
            echo AdminLang::trans("fields.securityquestion");
            echo "\"\n                                       href=\"\"\n                                    >\n                                        ";
            echo AdminLang::trans("fields.securityquestion");
            echo "                                    </a>\n                                </li>\n                            ";
        }
        echo "                        </ul>\n                    </div>\n                </td>\n            ";
    }
    echo "        </tr>\n        <tr id=\"emptyInviteRow\" class=\"empty-row invite-item\">\n            <td class=\"name text-left\">\n                <span class=\"email\"></span>\n                <br>\n                <small>\n                    ";
    echo AdminLang::trans("user.invites.sent");
    echo ":\n                    <span class=\"invite-sent\"></span>\n                </small>\n            </td>\n            <td class=\"last-login-time text-center\">\n                ";
    echo AdminLang::trans("global.na");
    echo "            </td>\n            ";
    if ($manageUserPermission) {
        echo "                <td class=\"remove text-center btn-resend\">\n                    <button class=\"btn btn-default btn-sm btn-resend\"\n                            data-invite-id=\"\"\n                    >\n                        ";
        echo AdminLang::trans("user.invites.resend");
        echo "                    </button>\n                    <button class=\"btn btn-default btn-sm btn-cancel\"\n                            data-invite-id=\"\"\n                    >\n                        ";
        echo AdminLang::trans("user.invites.cancel");
        echo "                    </button>\n                </td>\n            ";
    }
    echo "        </tr>\n    </table>\n    <table id=\"userTable\" class=\"datatable\" width=\"100%\" data-lang-empty-table=\"";
    echo AdminLang::trans("global.norecordsfound");
    echo "\" data-searching=\"true\" data-responsive=\"true\" border=\"0\" cellspacing=\"2\" cellpadding=\"3\">\n        <thead>\n            <tr>\n                <th>\n                    ";
    echo AdminLang::trans("fields.name");
    echo "                    /\n                    ";
    echo AdminLang::trans("fields.email");
    echo "                </th>\n                <th>";
    echo AdminLang::trans("fields.lastLoginTime");
    echo "</th>\n                ";
    if ($manageUserPermission || $securityQuestionsEnabled) {
        echo "                    <th>";
        echo AdminLang::trans("fields.actions");
        echo "</th>\n                ";
    }
    echo "            </tr>\n        </thead>\n        <tbody>\n            ";
    foreach ($users as $user) {
        echo "                <tr class=\"user-item\">\n                    <td class=\"name\">\n                        <span class=\"name\" data-user-id=\"";
        echo $user->id;
        echo "\">";
        echo $user->fullName;
        echo "</span>\n                        ";
        if ($user->isOwner($client)) {
            echo "                            <span class=\"label label-default\">Owner</span>\n                        ";
        }
        echo "                        <br>\n                        <span class=\"email\" data-user-id=\"";
        echo $user->id;
        echo "\">";
        echo $user->email;
        echo "</span>\n                    </td>\n                    <td class=\"last-login-time text-center\">\n                        ";
        if (!is_null($user->pivot->last_login)) {
            echo "                            ";
            echo $user->pivot->last_login->toAdminDateTimeFormat();
            echo "                        ";
        } else {
            echo "                            ";
            echo AdminLang::trans("global.na");
            echo "                        ";
        }
        echo "                    </td>\n                    ";
        if ($manageUserPermission || $securityQuestionsEnabled) {
            echo "                        <td class=\"remove text-center\">\n                            <div class=\"btn-group\" style=\"margin-left:10px;\">\n                                <button type=\"button\"\n                                        data-user-id=\"";
            echo $user->id;
            echo "\"\n                                        class=\"btn btn-default btn-permissions open-modal";
            echo $manageUserPermission ? "" : " disabled";
            echo "\"\n                                        data-modal-title=\"";
            echo AdminLang::trans("user.manageUserEmail", [":email" => $user->email]);
            echo "\"\n                                        href=\"";
            echo routePath("admin-client-user-manage", $client->id, $user->id);
            echo "\"\n                                        data-modal-size=\"modal-lg\"\n                                        data-btn-submit-label=\"";
            echo AdminLang::trans("global.save");
            echo "\"\n                                        data-btn-submit-id=\"btnSetPermissions\"\n                                        ";
            echo $manageUserPermission ? "" : "disabled=\"disabled\"";
            echo "\"\n                                >\n                                    ";
            echo AdminLang::trans("user.manageUser");
            echo "                                </button>\n                                ";
            if ($manageUserPermission) {
                echo "                                    <button type=\"button\"\n                                            class=\"btn btn-danger btn-remove";
                echo $user->id === $owner->id ? " disabled" : "";
                echo "\"\n                                            data-user-id=\"";
                echo $user->id;
                echo "\"\n                                        ";
                echo $user->id === $owner->id ? "disabled=\"disabled\"" : "";
                echo "                                    >\n                                        ";
                echo AdminLang::trans("global.remove");
                echo "                                    </button>\n                                ";
            }
            echo "                                <button type=\"button\"\n                                        class=\"btn btn-default dropdown-toggle\"\n                                        data-toggle=\"dropdown\"\n                                        aria-haspopup=\"true\"\n                                        aria-expanded=\"false\"\n                                >\n                                    <span class=\"caret\"></span>\n                                    <span class=\"sr-only\">Toggle Dropdown</span>\n                                </button>\n                                <ul class=\"dropdown-menu dropdown-menu-right\">\n                                    <li>\n                                        <a class=\"btn-reset";
            echo $manageUserPermission ? "" : " disabled";
            echo "\"\n                                           data-user-id=\"";
            echo $user->id;
            echo "\"\n                                           href=\"\"\n                                           ";
            echo $manageUserPermission ? "" : "disabled=\"disabled\"";
            echo "                                        >\n                                            ";
            echo AdminLang::trans("user.passwordReset");
            echo "                                        </a>\n                                    </li>\n                                    ";
            if ($securityQuestionsEnabled) {
                echo "                                        <li class=\"";
                echo $user->securityQuestionId ? "" : "disabled";
                echo "\">\n                                            <a class=\"open-modal\"\n                                               data-modal-title=\"";
                echo AdminLang::trans("fields.securityquestion");
                echo "\"\n                                               href=\"";
                echo routePath("admin-user-security-question", $user->id);
                echo "\"\n                                                ";
                echo $user->securityQuestionId ? "" : "disabled=\"disabled\"";
                echo "                                            >\n                                                ";
                echo AdminLang::trans("fields.securityquestion");
                echo "                                            </a>\n                                        </li>\n                                    ";
            }
            echo "                                </ul>\n                            </div>\n                        </td>\n                    ";
        }
        echo "                </tr>\n            ";
    }
    echo "            <tr id=\"rowPendingInvites\"";
    echo $invites->count() === 0 ? " class=\"hidden\"" : "";
    echo ">\n                <td colspan=\"";
    echo $colspan;
    echo "\" style=\"background-color:#eee;font-weight:bold;\">\n                    ";
    echo AdminLang::trans("user.invites.pending");
    echo "                </td>\n            </tr>\n            ";
    foreach ($invites as $invite) {
        echo "                <tr class=\"invite-item\">\n                    <td class=\"name text-left\">\n                        <span class=\"email\">";
        echo $invite->email;
        echo "</span>\n                        <br>\n                        <small>\n                            ";
        echo AdminLang::trans("user.invites.sent");
        echo ":\n                            <span class=\"invite-sent\">";
        echo $invite->createdAt->diffForHumans();
        echo "</span>\n                        </small>\n                    </td>\n                    <td class=\"last-login-time text-center\">\n                        ";
        echo AdminLang::trans("global.na");
        echo "                    </td>\n                    ";
        if ($manageUserPermission) {
            echo "                        <td class=\"remove text-center\">\n                            <button class=\"btn btn-default btn-sm btn-resend\"\n                                    data-invite-id=\"";
            echo $invite->id;
            echo "\"\n                            >\n                                ";
            echo AdminLang::trans("user.invites.resend");
            echo "                            </button>\n                            <button class=\"btn btn-default btn-sm btn-cancel\"\n                                    data-invite-id=\"";
            echo $invite->id;
            echo "\"\n                            >\n                                ";
            echo AdminLang::trans("user.invites.cancel");
            echo "                            </button>\n                        </td>\n                    ";
        }
        echo "                </tr>\n            ";
    }
    echo "        </tbody>\n    </table>\n    ";
}
if ($manageUserPermission) {
    $this->insert("user/partials/confirmation-modals");
}
