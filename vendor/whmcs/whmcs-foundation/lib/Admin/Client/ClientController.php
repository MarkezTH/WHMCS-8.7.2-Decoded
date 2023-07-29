<?php

namespace WHMCS\Admin\Client;

class ClientController
{
    public function login(\WHMCS\Http\Message\ServerRequest $request)
    {
        $client = \WHMCS\User\Client::find($request->get("client_id"));
        $ownerUser = $client->users()->wherePivot("owner", 1)->first();
        if (!$ownerUser) {
            throw new \WHMCS\Exception("No owner found for client");
        }
        \Auth::adminMasquerade($ownerUser, $client);
        return \WHMCS\Http\RedirectResponse::legacyPath("clientarea.php");
    }

    public function export(\WHMCS\Http\Message\ServerRequest $request)
    {
        $client = \WHMCS\User\Client::find($request->get("client_id"));
        if (is_null($client)) {
            throw new \WHMCS\Exception("Client id not found");
        }
        $exportData = $request->get("exportdata");
        if (empty($exportData) || !is_array($exportData)) {
            $exportData = ["profile"];
        }
        $dataToExport = [];
        if (in_array("profile", $exportData)) {
            $profileData = $client->toArrayUsingColumnMapNames();
            unset($profileData["creditCardType"]);
            unset($profileData["creditCardLastFourDigits"]);
            unset($profileData["paymentGatewayToken"]);
            $dataToExport["profile"] = $profileData;
        }
        if (in_array("paymethods", $exportData)) {
            $payMethods = ["creditCards" => [], "bankAccounts" => []];
            if ($client->needsCardDetailsMigrated()) {
                if (!function_exists("getClientDefaultCardDetails")) {
                    require_once ROOTDIR . "/includes/ccfunctions.php";
                }
                $cardDetails = getClientDefaultCardDetails($client->id);
                if ($cardDetails["cardtype"]) {
                    $payMethods["creditCards"][] = ["name" => $cardDetails["cardtype"] . "-" . $cardDetails["cardlastfour"]];
                }
            }
            if ($client->needsBankDetailsMigrated()) {
                if (!function_exists("getClientDefaultBankDetails")) {
                    require_once ROOTDIR . "/includes/clientfunctions.php";
                }
                $bankDetails = getClientDefaultBankDetails($client->id);
                if ($bankDetails["banktype"]) {
                    $payMethods["bankAccounts"][] = ["name" => $bankDetails["banktype"] . "-" . substr($bankDetails["bankacct"], -4, 4)];
                }
            }
            foreach ($client->payMethods as $payMethod) {
                $reportType = "";
                if ($payMethod->isCreditCard()) {
                    $reportType = "creditCards";
                } else {
                    if ($payMethod->isBankAccount()) {
                        $reportType = "bankAccounts";
                    }
                }
                if ($reportType) {
                    $payMethods[$reportType][] = ["name" => $payMethod->payment->getDisplayName()];
                }
            }
            $dataToExport["payMethods"] = $payMethods;
        }
        if (in_array("contacts", $exportData)) {
            $dataToExport["contacts"] = [];
            foreach ($client->contacts()->get() as $contact) {
                $dataToExport["contacts"][] = $contact->toArrayUsingColumnMapNames();
            }
        }
        if (in_array("services", $exportData)) {
            $dataToExport["services"] = [];
            foreach ($client->services()->get() as $service) {
                $dataToExport["services"][] = $service->toArrayUsingColumnMapNames();
            }
        }
        if (in_array("domains", $exportData)) {
            $dataToExport["domains"] = [];
            foreach ($client->domains()->get() as $domain) {
                $dataToExport["domains"][] = $domain->toArrayUsingColumnMapNames();
            }
        }
        if (in_array("billableitems", $exportData)) {
            $dataToExport["billableitems"] = [];
            foreach (\WHMCS\Database\Capsule::table("tblbillableitems")->where("userid", $client->id)->orderBy("duedate", "asc")->get()->all() as $billableitem) {
                $dataToExport["billableitems"][] = $billableitem;
            }
        }
        if (in_array("invoices", $exportData)) {
            $dataToExport["invoices"] = [];
            foreach ($client->invoices()->with("items")->get() as $invoice) {
                $dataToExport["invoices"][] = $invoice->toArrayUsingColumnMapNames();
            }
        }
        if (in_array("quotes", $exportData)) {
            $dataToExport["quotes"] = [];
            foreach ($client->quotes()->get() as $quote) {
                $dataToExport["quotes"][] = $quote->toArrayUsingColumnMapNames();
            }
        }
        if (in_array("transactions", $exportData)) {
            $dataToExport["transactions"] = [];
            foreach ($client->transactions()->get() as $transaction) {
                $dataToExport["transactions"][] = $transaction->toArrayUsingColumnMapNames();
            }
        }
        if (in_array("tickets", $exportData)) {
            $dataToExport["tickets"] = [];
            foreach ($client->tickets()->with("replies")->get() as $ticket) {
                $dataToExport["tickets"][] = $ticket->toArrayUsingColumnMapNames();
            }
        }
        if (in_array("emails", $exportData)) {
            $dataToExport["emails"] = [];
            foreach (\WHMCS\Database\Capsule::table("tblemails")->where("userid", $client->id)->orderBy("date", "asc")->get()->all() as $email) {
                $dataToExport["emails"][] = $email;
            }
        }
        if (in_array("notes", $exportData)) {
            $dataToExport["notes"] = [];
            foreach (\WHMCS\Database\Capsule::table("tblnotes")->where("userid", $client->id)->orderBy("created", "asc")->get()->all() as $note) {
                $dataToExport["notes"][] = $note;
            }
        }
        if (in_array("consenthistory", $exportData)) {
            $dataToExport["consenthistory"] = [];
            foreach ($client->marketingConsent()->get() as $consent) {
                $dataToExport["consenthistory"][] = $consent;
            }
        }
        if (in_array("activitylog", $exportData)) {
            $dataToExport["activitylog"] = [];
            foreach (\WHMCS\Database\Capsule::table("tblactivitylog")->where("userid", $client->id)->orderBy("date", "asc")->get()->all() as $activity) {
                $dataToExport["activitylog"][] = $activity;
            }
        }
        $attachmentName = "Client Export - Client ID " . $client->id . ".json";
        return new \WHMCS\Http\Message\JsonAttachmentResponse(jsonPrettyPrint($dataToExport), $attachmentName);
    }

    public function usersList($HtmlResponse, $request)
    {
        $clientId = $request->get("clientId");
        $aInt = new \WHMCS\Admin("View Account Users");
        $aInt->valUserID($clientId);
        $aInt->setClientSearchSubmitLocation(routePath("admin-client-search-submit-location", "users"));
        $aInt->setClientsProfilePresets($clientId);
        $aInt->assertClientBoundary($clientId);
        $aInt->setResponseType($aInt::RESPONSE_HTML_MESSAGE);
        $aInt->setHelpLink("Clients:Users Tab");
        try {
            $client = \WHMCS\User\Client::findOrFail($clientId);
            $params = ["client" => $client, "owner" => $client->owner(), "users" => $client->users()->orderBy("tblusers_clients.owner", "desc")->orderBy("tblusers_clients.id")->get(), "invites" => $client->invites()->pending()->get(), "allPermissions" => \WHMCS\User\Permissions::getAllPermissions(), "manageUserPermission" => checkPermission("Manage Users", true), "securityQuestionsEnabled" => 0 < \WHMCS\User\User\SecurityQuestion::all()->count()];
        } catch (\Exception $e) {
            $params = ["error" => $e->getMessage()];
        }
        $aInt->content = view("admin.client.users.list", $params);
        return $aInt->display();
    }

    public function associateUserModal($JsonResponse, $request)
    {
        return new \WHMCS\Http\Message\JsonResponse(["body" => view("admin.client.users.search", ["clientId" => $request->get("clientId"), "allPermissions" => \WHMCS\User\Permissions::getAllPermissions()])]);
    }

    public function associateUser($JsonResponse, $request)
    {
        try {
            $userId = $request->get("user");
            if (substr($userId, 0, 7) === "invite-") {
                return $this->inviteNewUser($request);
            }
            $clientId = $request->get("clientId");
            $permissions = $request->get("permission");
            $client = \WHMCS\User\Client::findOrFail($clientId);
            $invite = $request->get("invite");
            if ($client->users()->where("tblusers.id", $userId)->count()) {
                throw new \WHMCS\Exception\User\EmailAlreadyExists(\AdminLang::trans("user.alreadyAssociated"));
            }
            $user = \WHMCS\User\User::where("id", $userId)->first();
            if (!$user) {
                throw new \WHMCS\Exception\User\NoSuchUserException(\AdminLang::trans("user.notFound"));
            }
            if ($invite) {
                return $this->doInvite($request, $client, $user->email);
            }
            if (!is_array($permissions)) {
                $permissions = explode(",", $permissions);
            }
            $user->clients()->attach($clientId, ["permissions" => implode(",", $permissions)]);
            if ($user->securityQuestionId) {
                $securityQuestionUrl = routePath("admin-user-security-question", $user->id);
            } else {
                $securityQuestionUrl = "";
            }
            $na = \AdminLang::trans("global.na");
            $response = ["message" => \AdminLang::trans("user.associated"), "userId" => $user->id, "name" => $user->fullName, "email" => $user->email, "lastLoginTime" => !is_null($user->getRawAttribute("last_login")) ? $user->lastLogin->toAdminDateTimeFormat() : $na, "lastLoginIp" => $user->lastIp ?: $na, "lastLoginHostname" => $user->lastHostname ?: $na, "manageUserUrl" => routePath("admin-client-user-manage", $client->id, $user->id), "manageUserTitle" => \AdminLang::trans("user.manageUserEmail", [":email" => $user->email]), "securityQuestionUrl" => $securityQuestionUrl, "dismiss" => true];
        } catch (\Exception $e) {
            $response = ["errorMsg" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    protected function inviteNewUser($JsonResponse, $request)
    {
        $inviteEmail = substr($request->get("user"), 7);
        $clientId = $request->get("clientId");
        $client = \WHMCS\User\Client::findOrFail($clientId);
        if ($client->users()->where("tblusers.email", $inviteEmail)->count()) {
            throw new \WHMCS\Exception\User\EmailAlreadyExists(\AdminLang::trans("user.alreadyAssociated"));
        }
        return $this->doInvite($request, $client, $inviteEmail);
    }

    protected function doInvite($JsonResponse, $request, \WHMCS\User\Client $client, $email)
    {
        $clientId = $request->get("clientId");
        if ($client->invites()->pending()->where("email", $email)->count()) {
            throw new \WHMCS\Exception\User\EmailAlreadyExists(\AdminLang::trans("user.alreadyInvited", [":email" => $email]));
        }
        $permissions = $request->get("permission");
        $permissions = new \WHMCS\User\Permissions($permissions);
        $invite = \WHMCS\User\User\UserInvite::new($email, $permissions, $clientId);
        return new \WHMCS\Http\Message\JsonResponse(["message" => \AdminLang::trans("user.invited"), "invite" => true, "inviteId" => $invite->id, "inviteSent" => $invite->createdAt->diffForHumans(), "email" => $email, "dismiss" => true]);
    }

    public function removeUser($JsonResponse, $request)
    {
        try {
            $clientId = $request->get("clientId");
            $userId = $request->get("id");
            $client = \WHMCS\User\Client::findOrFail($clientId);
            if (!$client->users()->where("tblusers.id", $userId)->count()) {
                throw new \WHMCS\Exception\User\NoSuchUserException(\AdminLang::trans("user.notFound"));
            }
            $client->users()->detach($userId);
            $response = ["success" => true];
        } catch (\Exception $e) {
            $response = ["warning" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function cancelInvite($JsonResponse, $request)
    {
        try {
            $inviteId = (int) $request->get("id");
            $clientId = (int) $request->get("clientId");
            \WHMCS\User\User\UserInvite::ofAccount($clientId)->pending()->findOrFail($inviteId)->cancel();
            $response = ["success" => true];
        } catch (\Exception $e) {
            $response = ["warning" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function resendInvite($JsonResponse, $request)
    {
        try {
            $inviteId = (int) $request->get("id");
            $clientId = (int) $request->get("clientId");
            \WHMCS\User\User\UserInvite::ofAccount($clientId)->pending()->findOrFail($inviteId)->notify();
            $response = ["success" => true];
        } catch (\Exception $e) {
            $response = ["warning" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function manageUser($JsonResponse, $request)
    {
        try {
            $clientId = (int) $request->get("clientId");
            $userId = (int) $request->get("userId");
            $client = \WHMCS\User\Client::findOrFail($clientId);
            $user = $client->users()->findOrFail($userId);
            $response = ["body" => view("admin.client.users.manage", ["client" => $client, "user" => $user, "clientLanguages" => \WHMCS\Language\ClientLanguage::getLanguages(), "allPermissions" => \WHMCS\User\Permissions::getAllPermissions()])];
        } catch (\Exception $e) {
            $response = ["warning" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function saveUser($JsonResponse, $request)
    {
        try {
            $clientId = (int) $request->get("clientId");
            $userId = (int) $request->get("userId");
            $firstName = $request->get("first_name");
            $lastName = $request->get("last_name");
            $email = trim($request->get("email"));
            $language = $request->get("language");
            $permissions = $request->get("permission");
            $setOwner = $request->get("make_owner");
            $permissions = new \WHMCS\User\Permissions($permissions);
            $client = \WHMCS\User\Client::findOrFail($clientId);
            $user = $client->users()->findOrFail($userId);
            $oldUserDetails = $user->getDetails();
            $twoFactor = (bool) $request->get("twoFactor", $user->hasTwoFactorAuthEnabled());
            $disableSecurityQuestion = (bool) $request->get("disableSecurityQuestion", false);
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->language = $language;
            if ($user->hasTwoFactorAuthEnabled() && !$twoFactor) {
                $user->disableTwoFactorAuthentication();
            }
            if ($disableSecurityQuestion) {
                $user->disableSecurityQuestion();
            }
            if ($user->email !== $email) {
                try {
                    $user->changeEmail($email);
                } catch (\WHMCS\Exception\Validation\InvalidValue $e) {
                    throw new \WHMCS\Exception(\AdminLang::trans("clients.erroremailinvalid"));
                } catch (\WHMCS\Exception\User\EmailAlreadyExists $e) {
                    throw new \WHMCS\Exception(\AdminLang::trans("user.addressExists", [":email" => $email]));
                }
            }
            $user->save();
            run_hook("UserEdit", array_merge($user->getDetails(), ["olddata" => $oldUserDetails]));
            $changedOwner = false;
            if (!$user->isOwner($client)) {
                if ($setOwner) {
                    $owner = $client->owner();
                    $owner->pivot->owner = false;
                    $owner->pivot->setPermissions(\WHMCS\User\Permissions::all())->save();
                    $user->pivot->owner = true;
                    $changedOwner = true;
                }
                $user->pivot->setPermissions($permissions)->save();
            }
            $modalTitle = \AdminLang::trans("user.manageUserEmail", [":email" => $user->email]);
            $response = ["successMsgTitle" => "", "successMsg" => \AdminLang::trans("user.updated"), "dismiss" => true, "body" => "<script>\n    jQuery(document).ready(function() {\n        jQuery('span.name[data-user-id=\"" . $userId . "\"]').text('" . $firstName . " " . $lastName . "');\n        jQuery('span.email[data-user-id=\"" . $userId . "\"]').text('" . $email . "');\n        jQuery('button[data-user-id=\"" . $userId . "\"]').attr('data-modal-title', '" . $modalTitle . "')\n            .data('modal-title', '" . $modalTitle . "');\n    });\n</script>"];
            if ($changedOwner) {
                $response["reloadPage"] = true;
            }
        } catch (\Exception $e) {
            $response = ["errorMsg" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function submitRedirect(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clientId = $request->get("userid", 0);
        if (!$clientId) {
            \App::redirectToRoutePath("admin-homepage");
        }
        $location = $request->get("location", \App::getPhpSelf());
        switch ($location) {
            case "tickets":
                \App::redirectToRoutePath("admin-client-tickets", [$clientId]);
                break;
            case "users":
                \App::redirectToRoutePath("admin-client-users", [$clientId]);
                break;
            default:
                \App::redirect(\WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl() . "/" . $location . ".php", ["userid" => $clientId]);
        }
    }

    public function doDelete(\WHMCS\Http\Message\ServerRequest $request)
    {
        $userId = $request->get("userId");
        $deleteUsers = (bool) $request->get("deleteUsers");
        $deleteTransactions = (bool) $request->get("deleteTransactions");
        $deleteUsersCheck = $deleteUsers && checkPermission("Delete Users", true);
        $deleteTransactionsCheck = $deleteTransactions && checkPermission("Delete Transaction", true);
        try {
            $client = \WHMCS\User\Client::findOrFail($userId);
            if ($deleteUsersCheck) {
                $client->deleteUsersWithNoOtherClientAccounts();
            }
            if ($deleteTransactionsCheck) {
                $client->deleteTransactions();
            } else {
                if (0 < $client->transactions()->count()) {
                    $client->disassociateTransactions();
                }
            }
            $client->deleteEntireClient();
            $response = ["success" => true, "redirectUrl" => \WHMCS\Utility\Environment\WebHelper::getAdminBaseUrl() . "/clients.php"];
        } catch (\Exception $e) {
            $response = ["errorMsg" => $e->getMessage()];
        }
        return new \WHMCS\Http\Message\JsonResponse($response);
    }

    public function summaryFilter(\WHMCS\Http\Message\ServerRequest $request)
    {
        $filters = $request->get("filters", []);
        $admin = (new \WHMCS\Authentication\CurrentUser())->admin();
        if (!$admin) {
            return new \WHMCS\Http\Message\JsonResponse(["warning" => "Invalid Access Attempt"]);
        }
        $currentPreferences = $admin->userPreferences;
        if (empty($currentPreferences) || !is_array($currentPreferences)) {
            $currentPreferences = [];
        }
        if (empty($currentPreferences["filters"])) {
            $currentPreferences["filters"] = [];
        }
        $currentPreferences["filters"]["summary"] = $filters;
        $admin->userPreferences = $currentPreferences;
        $admin->save();
        return new \WHMCS\Http\Message\JsonResponse(["success" => true]);
    }
}
