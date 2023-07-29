<?php

namespace WHMCS\Mail\Incoming;

interface MailboxInterface
{
    public static function createForDepartment($department, $isTest = false);

    public function getMessageCount();

    public function getAllMessages($Iterator);

    public function getRfcMessage($messageIndex, $messageData);

    public function deleteMessage($messageIndex);

    public function close();
}
