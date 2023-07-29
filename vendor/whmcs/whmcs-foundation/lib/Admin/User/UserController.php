<?php

namespace WHMCS\Admin\User;

class UserController
{
    public function list($ResponseInterface, $request = [], $criteria)
    {
        $admin = $request->getAttribute("authenticatedUser");
        $view = (new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\BodyContentWrapper())->setTitle(\AdminLang::trans("user.manageUsers"))->setSidebarName("clients")->setFavicon("clients")->setHelpLink("Users");
        $name = "user-list";
        $orderby = "id";
        $sort = "DESC";
        $pageObj = new \WHMCS\Pagination($name, $orderby, $sort);
        $pageObj->digestCookieData();
        $securityQuestions = \WHMCS\User\User\SecurityQuestion::all()->count();
        $tbl = new \WHMCS\ListTable($pageObj);
        $tbl->setTableBgClass("table-bg-overflow-visible");
        $columns = [["id", \AdminLang::trans("fields.id")], ["first_name", \AdminLang::trans("fields.firstname")], ["last_name", \AdminLang::trans("fields.lastname")], ["email", \AdminLang::trans("fields.email")], \AdminLang::trans("fields.2fa")];
        if ($securityQuestions) {
            $columns[] = \AdminLang::trans("fields.securityquestion");
        }
        $columns[] = ["last_login", \AdminLang::trans("fields.lastLoginTime")];
        $tbl->setColumns($columns);
        if ($admin->hasPermission("Manage Users")) {
            $tbl->addColumn(["", \AdminLang::trans("global.actions"), "240px"]);
        }
        $data = new UsersFilter($pageObj);
        $data->execute($criteria);
        $users = $pageObj->getData();
        $editText = \AdminLang::trans("user.manageUser");
        $saveText = \AdminLang::trans("global.save");
        $resetPassword = \AdminLang::trans("user.passwordReset");
        $securityQuestionLang = \AdminLang::trans("fields.securityquestion");
        foreach ($users as $user) {
            $twoFAClasses = ["fas", "fa-shield"];
            $sqClasses = ["far", "fa-question"];
            $evLang = "";
            if ($user->isEmailVerificationEnabled() && $user->emailVerified()) {
                $evLang = "clients.emailVerified";
            } else {
                if ($user->isEmailVerificationEnabled()) {
                    $evLang = "clients.emailUnverified";
                }
            }
            if ($user->hasTwoFactorAuthEnabled()) {
                $twoFAClasses[] = "text-success";
                $twoFALang = "user.2faEnabled";
            } else {
                $twoFALang = "user.2faDisabled";
            }
            if ($user->hasSecurityQuestion()) {
                $sqClasses[] = "text-success";
                $sqLang = "user.securityQuestionSet";
            } else {
                $sqLang = "user.securityQuestionNotSet";
            }
            if ($evLang) {
                $evLang = \AdminLang::trans($evLang);
            }
            $twoFALang = \AdminLang::trans($twoFALang);
            $sqLang = \AdminLang::trans($sqLang);
            $twoFAClasses = implode(" ", $twoFAClasses);
            $sqClasses = implode(" ", $sqClasses);
            $manageTitle = \AdminLang::trans("user.manageUserEmail", [":email" => $user->email]);
            $manageUrl = routePath("admin-user-manage", $user->id);
            $securityQuestion = "";
            if ($securityQuestions) {
                $class = "";
                if (!$user->hasSecurityQuestion()) {
                    $class = "disabled";
                }
                $route = routePath("admin-user-security-question", $user->id);
                $securityQuestion = "<li class=\"" . $class . "\">\n    <a class=\"open-modal " . $class . "\"\n       data-modal-title=\"" . $securityQuestionLang . "\"\n       href=\"" . $route . "\"\n       " . $class . "\n    >\n        " . $securityQuestionLang . "\n    </a>\n</li>";
            }
            $actions = "<div class=\"btn-group\">\n    <button type=\"button\"\n            class=\"btn btn-default btn-sm open-modal manage-user\"\n            href=\"" . $manageUrl . "\"\n            data-modal-size=\"modal-lg\"\n            data-btn-submit-label=\"" . $saveText . "\"\n            data-btn-submit-id=\"btnUpdateUser\"\n            data-modal-title=\"" . $manageTitle . "\"\n    >\n        " . $editText . "\n    </button>\n    <button type=\"button\"\n            class=\"btn btn-default btn-sm dropdown-toggle\"\n            data-toggle=\"dropdown\"\n            aria-haspopup=\"true\"\n            aria-expanded=\"false\"\n    >\n        <span class=\"caret\"></span>\n        <span class=\"sr-only\">Toggle Dropdown</span>\n    </button>\n    <ul class=\"dropdown-menu\">\n        <li>\n            <a class=\"btn-reset\"\n               data-user-id=\"" . $user->id . "\"\n               href=\"\"\n            >\n                " . $resetPassword . "\n            </a>\n        </li>\n        " . $securityQuestion . "\n    </ul>\n</div>";
            $twoFA = "<i class=\"" . $twoFAClasses . "\"\n   aria-hidden=\"true\"\n></i>\n" . $twoFALang;
            $sq = "<i class=\"" . $sqClasses . "\"\n   aria-hidden=\"true\"\n></i>\n" . $sqLang;
            if ($user->lastLogin && $user->lastLogin instanceof \WHMCS\Carbon && 0 < $user->lastLogin->year) {
                $lastLogin = "<span title=\"" . $user->lastLogin->diffForHumans() . "\" data-toggle=\"tooltip\" data-placement=\"auto top\">\n    " . $user->lastLogin->toAdminDateTimeFormat() . "\n</span>";
            } else {
                $lastLogin = \AdminLang::trans("global.never");
            }
            $row = ["<a href=\"#\" class=\"manage-user\">" . $user->id . "</a>", "<a href=\"#\" data-id=\"" . $user->id . "\" class=\"first manage-user\">" . $user->first_name . "</a>", "<a href=\"#\" data-id=\"" . $user->id . "\" class=\"last manage-user\">" . $user->last_name . "</a>", "<a href=\"#\" data-id=\"" . $user->id . "\" class=\"email manage-user\">" . $user->email . "</a> <span class=\"badge email-verified-badge\">" . $evLang . "</span>", (string) $twoFA];
            if ($securityQuestions) {
                $row[] = $sq;
            }
            $row[] = $lastLogin;
            $row[] = $actions;
            $tbl->addRow($row);
        }
        if (0 < $users->count()) {
            $hiddenRow = ["trAttributes" => ["id" => "rowNoResults", "class" => "hidden"], "output" => ["tdAttributes" => ["colspan" => 7, "class" => "text-center"], \AdminLang::trans("global.norecordsfound")]];
            $tbl->addRow([$hiddenRow]);
        }
        $pageObj->setBasePath(routePath("admin-user-list"));
        $content = view("admin.user.list", ["searchCriteria" => $criteria, "searchActive" => 0 < count($criteria), "tableOutput" => $tbl->output()]);
        $view->setBodyContent($content);
        return $view;
    }

    public function search($BodyContentWrapper, $request)
    {
        $filter = (new \WHMCS\Filter())->setAllowedVars(["criteria"]);
        $filter = $filter->store()->getFilterCriteria();
        return $this->list($request, $filter);
    }

    public function manage(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $userId = (int) $request->get("userId");
            $user = \WHMCS\User\User::findOrFail($userId);
            $owner = \AdminLang::trans("user.accountOwner");
            $ownerFA = "<i class=\"far fa-check text-success\" aria-hidden=\"true\" title=\"" . $owner . "\"></i>\n<span class=\"sr-only\">" . $owner . "</span>";
            $response = ["body" => view("admin.user.manage", ["user" => $user, "clientLanguages" => \WHMCS\Language\ClientLanguage::getLanguages(), "ownerFA" => $ownerFA, "assetHelper" => \DI::make("asset")])];
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $response = ["body" => \WHMCS\View\Helper::alert(\AdminLang::trans("user.notFound"), "danger")];
        } catch (\Exception $e) {
            $response = ["body" => \WHMCS\View\Helper::alert($e->getMessage(), "danger")];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function save(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            $userId = (int) $request->get("userId");
            $firstName = $request->get("first_name");
            $lastName = $request->get("last_name");
            $email = $request->get("email");
            $language = $request->get("language");
            $user = \WHMCS\User\User::findOrFail($userId);
            $oldUserDetails = $user->getDetails();
            $twoFactor = (bool) $request->get("twoFactor", $user->hasTwoFactorAuthEnabled());
            $disableSecurityQuestion = (bool) $request->get("disableSecurityQuestion", false);
            if (!$email || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \WHMCS\Exception\Validation\InvalidValue("Invalid Email Address");
            }
            if (\WHMCS\User\User::where("email", $email)->where("id", "!=", $userId)->count()) {
                throw new \WHMCS\Exception\User\EmailAlreadyExists(\AdminLang::trans("user.addressExists", [":email" => $email]));
            }
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->email = $email;
            $user->language = $language;
            if ($user->hasTwoFactorAuthEnabled() && !$twoFactor) {
                $user->disableTwoFactorAuthentication();
            }
            if ($disableSecurityQuestion) {
                $user->disableSecurityQuestion();
            }
            $user->save();
            run_hook("UserEdit", array_merge($user->getDetails(), ["olddata" => $oldUserDetails]));
            $response = ["successMsgTitle" => "", "successMsg" => \AdminLang::trans("user.updated"), "dismiss" => true, "body" => "<script>\n    jQuery(document).ready(function() {\n        jQuery('a.first[data-id=\"" . $userId . "\"]').text('" . $firstName . "');\n        jQuery('a.last[data-id=\"" . $userId . "\"]').text('" . $lastName . "');\n        jQuery('a.email[data-id=\"" . $userId . "\"]').text('" . $email . "');\n    });\n</script>"];
        } catch (\Exception $e) {
            $response = ["errorMsg" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function securityQuestion($JsonResponse, $request)
    {
        try {
            $userId = $request->get("userId", 0);
            $user = \WHMCS\User\User::findOrFail($userId);
            $response = ["body" => view("admin.user.security-question", ["user" => $user, "securityQuestions" => \WHMCS\User\User\SecurityQuestion::all()])];
        } catch (\Exception $e) {
            $response = ["errorMsg" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function passwordReset($JsonResponse, $request)
    {
        try {
            $userId = $request->get("userId");
            $user = \WHMCS\User\User::find($userId);
            if (!$user) {
                throw new \WHMCS\Exception\User\NoSuchUserException(\AdminLang::trans("user.notFound"));
            }
            $user->sendPasswordResetEmail();
            $response = ["success" => true];
        } catch (\Exception $e) {
            $response = ["warning" => $e->getMessage()];
            logActivity("Password Reset Request Failed: " . $e->getMessage() . " - UserID: " . $user->id);
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function doDelete($JsonResponse, $request)
    {
        try {
            $userId = $request->get("userId");
            $user = \WHMCS\User\User::find($userId);
            if (!$user) {
                throw new \WHMCS\Exception\User\NoSuchUserException(\AdminLang::trans("user.notFound"));
            }
            if (0 < $user->clients()->count()) {
                throw new \WHMCS\Exception\User\UserBelongsToClient(\AdminLang::trans("user.associatedWithClient"));
            }
            $user->delete();
            $response = ["success" => true];
        } catch (\Exception $e) {
            $response = ["warning" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }
}
