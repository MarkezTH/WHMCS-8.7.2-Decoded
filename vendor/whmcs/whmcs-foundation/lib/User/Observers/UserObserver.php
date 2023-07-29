<?php

namespace WHMCS\User\Observers;

class UserObserver
{
    protected $updateLogging = ["First Name" => "first_name", "Last Name" => "last_name", "Email Address" => "email", "Language" => "language"];

    public function updated($user)
    {
        $changeList = $user->getChanges();
        $changes = [];
        foreach ($this->updateLogging as $friendly => $field) {
            if (count($changeList) === 0) {
                if (0 < count($changes)) {
                    logActivity("User Account Modified - " . implode(", ", $changes) . " - UserID: " . $user->id);
                }
            } else {
                if (array_key_exists($field, $changeList)) {
                    $changes[] = $friendly . " changed from '" . $user->getOriginal($field) . "'" . " to '" . $user->getAttribute($field) . "'";
                    unset($changeList[$field]);
                }
            }
        }
    }
}
