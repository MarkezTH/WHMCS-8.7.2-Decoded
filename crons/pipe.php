<?php

?>
#!/usr/local/bin/php
<?php 
require_once __DIR__ . DIRECTORY_SEPARATOR . "bootstrap.php";
require ROOTDIR . "/includes/adminfunctions.php";
require ROOTDIR . "/includes/ticketfunctions.php";
$fd = fopen("php://stdin", "r");
$input = "";
while (!feof($fd)) {
    $input .= fread($fd, 1024);
}
fclose($fd);
if ($input === "") {
    WHMCS\Terminus::getInstance()->doDie("This file cannot be accessed directly");
}
$mailParser = new ZBateson\MailMimeParser\MailMimeParser();
$message = $mailParser->parse($input);
$toHeader = $message->getHeader("to");
$ccHeader = $message->getHeader("cc");
$bccHeader = $message->getHeader("bcc");
$fromHeader = $message->getHeader("from");
$replyToHeader = $message->getHeader("reply-to");
$subjectHeader = $message->getHeader("subject");
$toEmails = [];
$ccEmails = [];
foreach ($toHeader->getAddresses() as $address) {
    $toEmails[] = $address->getEmail();
}
if (!is_null($ccHeader)) {
    foreach ($ccHeader->getAddresses() as $address) {
        $addressEmail = $address->getEmail();
        $toEmails[] = $addressEmail;
        $ccEmails[] = $addressEmail;
    }
}
if (!is_null($bccHeader)) {
    foreach ($bccHeader->getAddresses() as $address) {
        $toEmails[] = $address->getEmail();
    }
}
$toEmails = implode(",", $toEmails);
$ccEmails = array_slice($ccEmails, 0, 20);
$fromEmail = $fromHeader->getValue();
$fromName = $fromHeader->getPersonName();
$fromName = empty($fromName) ? $fromEmail : $fromName;
if (!is_null($replyToHeader) && $replyToHeader->getValue()) {
    $replyToEmail = $replyToHeader->getValue();
    $replyToName = $replyToHeader->getPersonName();
    $fromEmail = $replyToEmail;
    $fromName = empty($replyToName) ? $replyToEmail : $replyToName;
}
$messageSubject = $subjectHeader ? trim($subjectHeader->getValue()) : "";
$messageBody = "No message found.";
if (0 < $message->getTextPartCount()) {
    $messageBody = $message->getTextContent();
} else {
    if (0 < $message->getHtmlPartCount()) {
        $messageBody = strip_tags($message->getHtmlContent());
    }
}
$messageAttachments = [];
if (0 < $message->getAttachmentCount()) {
    $pipeAttachmentStorage = Storage::ticketAttachments();
    mt_srand(time());
    $fileNumber = 1;
    $defaultName = "attachment";
    $defaultExt = "unknown";
    foreach ($message->getAllAttachmentParts() as $attachment) {
        $filename = getAttachmentFilename($attachment, $defaultName . "_" . $fileNumber++, $defaultExt);
        if (checkTicketAttachmentExtension($filename)) {
            $filenameParts = explode(".", $filename);
            $extension = array_pop($filenameParts);
            $filename = implode($filenameParts);
            $filename = trim(preg_replace("/[^a-zA-Z0-9-_ ]/", "", $filename));
            if (empty($filename)) {
                $filename = $defaultName;
                do {
                } while (!($pipeAttachmentStorage->has($attachmentFilename) && $maxTries--));
                $messageBody = replaceAttachmentCidWithFilename($attachment, $messageBody, $filename . "." . $extension);
                $messageAttachments[] = $attachmentFilename;
                $pipeAttachmentStorage->write($attachmentFilename, getAttachmentContent($attachment));
            }
            $maxTries = 1000;
            $rand = mt_rand(100000, 999999);
            $attachmentFilename = $rand . "_" . $filename . "." . $extension;
        } else {
            $messageBody .= "\n\nAttachment " . $filename . " blocked - file type not allowed.";
        }
    }
}
$messageAttachments = implode("|", $messageAttachments);
processPipedTicket($toEmails, $fromName, $fromEmail, $messageSubject, $messageBody, $messageAttachments, new WHMCS\Mail\AutoSubmittedHeader($message), $ccEmails);
