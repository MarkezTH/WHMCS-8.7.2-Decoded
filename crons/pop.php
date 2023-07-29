<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . "bootstrap.php";
require ROOTDIR . "/includes/adminfunctions.php";
require ROOTDIR . "/includes/ticketfunctions.php";
define("IN_CRON", true);
$transientData = WHMCS\TransientData::getInstance();
$transientData->delete("popCronComplete");
$whmcs = App::self();
$whmcsAppConfig = $whmcs->getApplicationConfig();
$cronOutput = [];
if (defined("PROXY_FILE")) {
    $cronOutput[] = WHMCS\Cron::getLegacyCronMessage();
}
$cronOutput[] = "<b>POP Import Log</b><br>Date: " . date("d/m/Y H:i:s") . "<hr>";
/**
 * @var WHMCS\Support\Department[] $ticketDepartments
 */
$ticketDepartments = WHMCS\Support\Department::query()->orderBy("order")->get()->filter(function (WHMCS\Support\Department $department) {
    return $department->isSetUpForMailImport();
});
$connectionErrors = [];
$echoDepartment = function ($dept) {
    if ($dept->mailAuthConfig["auth_type"] === WHMCS\Mail\MailAuthHandler::AUTH_TYPE_OAUTH2) {
        $authOutput = $dept->mailAuthConfig["service_provider"] . " OAuth";
    } else {
        $authOutput = sprintf("%s@%s", $dept->login, $dept->host);
    }
    return sprintf("Department: %s | %s (via %s)<br>", $dept->name, $dept->email, $authOutput);
};
foreach ($ticketDepartments as $ticketDepartment) {
    ob_start();
    $cronOutput[] = $echoDepartment($ticketDepartment);
    try {
        $mailbox = WHMCS\Mail\Incoming\MailboxFactory::createForDepartment($ticketDepartment);
        $mailCount = $mailbox->getMessageCount();
        if (!$mailCount) {
            $cronOutput[] = "Mailbox is empty<br>";
        } else {
            $cronOutput[] = "Email Count: " . $mailCount . "<br>";
        }
        $mailParser = new ZBateson\MailMimeParser\MailMimeParser();
        foreach ($mailbox->getAllMessages() as $messageIndex => $messageData) {
            $toEmails = [];
            $processedCcEmails = [];
            $fromName = $fromEmail = "";
            $subject = "";
            $messageBody = "";
            $attachmentList = "";
            try {
                $message = $mailParser->parse($mailbox->getRfcMessage($messageIndex, $messageData));
                $fromAddress = $message->getHeader("reply-to") ?? $message->getHeader("from");
                if ($fromAddress) {
                    $fromName = $fromAddress->getPersonName() ?? "";
                    $fromEmail = $fromAddress->getEmail() ?? "";
                    if (strlen($fromName) == 0) {
                        $fromName = $fromEmail;
                    }
                }
                $toHeader = $message->getHeader("to");
                if ($toHeader instanceof ZBateson\MailMimeParser\Header\AddressHeader) {
                    foreach ($toHeader->getAddresses() as $toEmail) {
                        $toEmails[] = $toEmail->getEmail();
                    }
                }
                $toEmails[] = $ticketDepartment->email;
                $ccHeader = $message->getHeader("cc");
                if ($ccHeader instanceof ZBateson\MailMimeParser\Header\AddressHeader) {
                    foreach ($ccHeader->getAddresses() as $ccEmail) {
                        $processedCcEmails[] = $ccEmail->getEmail();
                    }
                }
                $processedCcEmails = array_slice($processedCcEmails, 0, 20);
                $subjectHeader = $message->getHeader("subject");
                if ($subjectHeader) {
                    $subject = trim(str_replace(["{", "}"], ["[", "]"], $subjectHeader->getValue()));
                }
                if (0 < $message->getTextPartCount()) {
                    $messageBody = $message->getTextContent();
                } else {
                    if (0 < $message->getHtmlPartCount()) {
                        $messageBody = strip_tags($message->getHtmlContent());
                    } else {
                        $messageBody = "No message found.";
                    }
                }
                $messageBody = str_replace("&nbsp;", " ", $messageBody);
                $ticketAttachments = [];
                $popAttachmentStorage = Storage::ticketAttachments();
                $fileNumber = 1;
                $defaultName = "attachment";
                $defaultExt = "unknown";
                foreach ($message->getAllAttachmentParts() as $attachment) {
                    $filename = "";
                    try {
                        $filename = getAttachmentFilename($attachment, $defaultName . "_" . $fileNumber++, $defaultExt);
                        if (checkTicketAttachmentExtension($filename)) {
                            $filenameParts = explode(".", $filename);
                            $extension = end($filenameParts);
                            $filename = implode(array_slice($filenameParts, 0, -1));
                            $filename = trim(preg_replace("/[^a-zA-Z0-9-_ ]/", "", $filename));
                            do {
                                $filename = empty($filename) ? $defaultName : $filename . "." . $extension;
                                mt_srand(time());
                                $rand = random_int(100000, 999999);
                                $attachmentFilename = $rand . "_" . $filename;
                            } while (!$popAttachmentStorage->has($attachmentFilename));
                            $ticketAttachments[] = $attachmentFilename;
                            $popAttachmentStorage->write($attachmentFilename, getAttachmentContent($attachment));
                            $messageBody = replaceAttachmentCidWithFilename($attachment, $messageBody, $filename);
                        } else {
                            $messageBody .= "\n\nAttachment " . $filename . " blocked - file type not allowed.";
                        }
                    } catch (Throwable $e) {
                        $messageBody .= "\n\nAttachment " . $filename . " could not be saved.";
                    }
                }
                $attachmentList = implode("|", $ticketAttachments);
                processPoppedTicket(implode(",", $toEmails), $fromName, $fromEmail, $subject, $messageBody, $attachmentList, new WHMCS\Mail\AutoSubmittedHeader($message), $processedCcEmails);
                $mailbox->deleteMessage($messageIndex);
            } catch (Throwable $e) {
                WHMCS\Database\Capsule::table("tblticketmaillog")->insert(["date" => WHMCS\Carbon::now()->toDateTimeString(), "to" => implode(",", $toEmails), "cc" => implode(",", $processedCcEmails), "name" => $fromName, "email" => $fromEmail, "subject" => $subject, "message" => $messageBody, "status" => $e->getMessage(), "attachment" => $attachmentList]);
            }
        }
        $mailbox->close();
        $cronOutput[] = "<hr>";
    } catch (Exception $e) {
        $connectionErrors[] = ["department" => $ticketDepartment, "error" => $e->getMessage()];
        $cronOutput[] = $e->getMessage() . "<hr>";
    }
    $content = ob_get_clean();
    $cronOutput[] = $content;
}
if (0 < count($connectionErrors)) {
    $connectionErrorsString = "";
    foreach ($connectionErrors as $connectionError) {
        $connectionErrorsString .= "<br>" . $connectionError["department"]->name;
        $connectionErrorsString .= " &lt;" . $connectionError["department"]->email . "&gt;<br>";
        $connectionErrorsString .= "Error: " . $connectionError["error"] . "<br>";
        $connectionErrorsString .= "-----";
    }
    $failureMessage = "<p>One or more POP3 connections failed:<br><br>-----" . $connectionErrorsString . "<br></p>";
    try {
        sendAdminNotification("system", "POP3 Connection Error", $failureMessage);
    } catch (Exception $e) {
    }
}
if (WHMCS\Environment\Php::isCli() || DI::make("config")->pop_cron_debug) {
    $output = implode("", $cronOutput);
    if (WHMCS\Environment\Php::isCli()) {
        $output = strip_tags(str_replace(["<br>", "<hr>"], ["\n", "\n---\n"], $output));
    }
    echo $output;
}
$transientData->store("popCronComplete", "true", 3600);
run_hook("PopEmailCollectionCronCompleted", ["connectionErrors" => $connectionErrors]);
