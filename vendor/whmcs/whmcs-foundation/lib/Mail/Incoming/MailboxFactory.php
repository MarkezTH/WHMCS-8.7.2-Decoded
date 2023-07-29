<?php

namespace WHMCS\Mail\Incoming;

class MailboxFactory
{
    const PROPRIETARY_TRANSPORT_MAILBOX_CLASSES = NULL;

    public static function createForDepartment(\WHMCS\Support\Department $department = false, $isTest = false)
    {
        $mailProvider = $department->mailAuthConfig["service_provider"];
        $mailboxClass = self::PROPRIETARY_TRANSPORT_MAILBOX_CLASSES[$mailProvider] ?? "WHMCS\\Mail\\Incoming\\Mailbox";
        return $mailboxClass::createForDepartment($department, $isTest);
    }
}
