<?php

namespace WHMCS\User\Observers;

class UserClientObserver
{
    protected $updateLogging = ["Account Ownership" => "owner", "Permissions" => "permissions"];

    public function created($userClient)
    {
        if (!$userClient->owner) {
            $permissions = $userClient->getPermissions()->get();
            sort($permissions);
            $permissions = implode(", ", $permissions);
            logActivity("User Account Relationship Created - " . " '" . $userClient->user->fullName . "' granted access with" . " permissions: " . $permissions . " - Client ID: " . $userClient->client_id . " - UserID: " . $userClient->auth_user_id, $userClient->client_id);
        }
    }

    public function deleted($userClient)
    {
        logActivity("User Account Relationship Removed", $userClient->client_id, ["addUserId" => $userClient->auth_user_id, "withClientId" => true]);
    }

    public function updated($userClient)
    {
        $changeList = $userClient->getChanges();
        $changes = [];
        foreach ($this->updateLogging as $friendly => $field) {
            if (count($changeList) === 0) {
                if (0 < count($changes)) {
                    logActivity("User Account Relationship Modified - " . implode(", ", $changes) . " - Client ID: " . $userClient->client_id . " - UserID: " . $userClient->auth_user_id, $userClient->client_id);
                }
            } else {
                if (array_key_exists($field, $changeList)) {
                    $original = $userClient->getOriginal($field);
                    $value = $userClient->getAttribute($field);
                    if ($field === "permissions") {
                        $original = explode(",", $original);
                        $value = explode(",", $value);
                        sort($original);
                        sort($value);
                        $original = implode(", ", $original);
                        $value = implode(", ", $value);
                        $changes[] = $friendly . " changed from '" . $original . "' to '" . $value . "'";
                    } else {
                        if ($field === "owner") {
                            if ($value) {
                                $changes[] = $friendly . " Granted";
                            } else {
                                $changes[] = $friendly . " Removed";
                            }
                        }
                    }
                    unset($changeList[$field]);
                }
            }
        }
    }
}
