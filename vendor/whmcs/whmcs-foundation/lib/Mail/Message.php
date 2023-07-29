<?php

namespace WHMCS\Mail;

class Message
{
    protected $type = "general";
    protected $templateName = "";
    protected $from = [];
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $replyTo = [];
    protected $subject = "";
    protected $body = "";
    protected $bodyPlainText = "";
    protected $attachments = [];
    protected $headers = [];
    protected $defaultEmailHeadersByType = NULL;
    private $logId = 0;
    const HEADER_MARKER = "<!-- message header end -->";
    const FOOTER_MARKER = "<!-- message footer start -->";
    const RFC3834_HEADERS = ["Auto-Submitted", "X-Auto-Response-Suppress"];
    const RECIPIENT_TYPES = ["to", "cc", "bcc"];

    public function __construct()
    {
        $this->setFromName(\WHMCS\Config\Setting::getValue("CompanyName"))->setFromEmail(\WHMCS\Config\Setting::getValue("Email"))->setGlobalBCCRecipients();
    }

    public function setEmailLogId($id)
    {
        $this->logId = $id;
        return $this;
    }

    public static function createFromTemplate(Template $template)
    {
        $message = new self();
        $message->setType($template->type);
        $message->setEmailHeadersForType($template->type);
        $message->setTemplateName($template->name);
        if ($template->fromName) {
            $message->setFromName($template->fromName);
        }
        if ($template->fromEmail) {
            $message->setFromEmail($template->fromEmail);
        }
        $message->setSubject($template->subject);
        if ($template->plaintext) {
            $message->setPlainText($template->message);
        } else {
            $message->setBodyAndPlainText($template->message);
        }
        if (is_array($template->copyTo)) {
            foreach ($template->copyTo as $copyto) {
                $message->addRecipient("cc", $copyto);
            }
        }
        if (is_array($template->blindCopyTo)) {
            foreach ($template->blindCopyTo as $bcc) {
                $message->addRecipient("bcc", $bcc);
            }
        }
        if (is_array($template->attachments)) {
            try {
                $storage = \Storage::emailTemplateAttachments();
                foreach ($template->attachments as $attachment) {
                    $displayname = substr($attachment, 7);
                    $message->addStringAttachment($displayname, $storage->read($attachment));
                }
            } catch (\League\Flysystem\FileNotFoundException $e) {
                $message = "Could not access file: " . $attachment;
                logActivity("Email Sending Failed - " . $message . " (Subject: " . $template->subject . ")", "none");
                throw new \WHMCS\Exception\Mail\InvalidTemplate("Could not access file: " . $attachment);
            } catch (\Exception $e) {
                logActivity("Email Sending Failed - The system encountered an error while attempting to access email attachment storage: " . $e->getMessage());
                throw new \WHMCS\Exception\Mail\InvalidTemplate("Unable to access email attachment storage.");
            }
        }
        return $message;
    }

    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setTemplateName($templateName)
    {
        $this->templateName = $templateName;
        return $this;
    }

    public function getTemplateName()
    {
        return $this->templateName;
    }

    public function addRecipient($type, $email, $name = "")
    {
        $this->assertRecipientType($type);
        $email = mb_strtolower($email);
        $name = \WHMCS\Input\Sanitize::decode($name);
        if (!$this->hasRecipientByEmail($email)) {
            $this->{$type}[$email] = [$email, $name];
        }
        return $this;
    }

    public function getAllRecipients()
    {
        return array_merge($this->to, $this->cc, $this->bcc);
    }

    public function hasRecipientByEmail($email)
    {
        return isset($this->getAllRecipients()[$email]);
    }

    protected function assertRecipientType($type)
    {
        if (in_array($type, self::RECIPIENT_TYPES)) {
            return true;
        }
        throw new \WHMCS\Exception\Mail\InvalidAddressType("Email address type is not valid: " . $type);
    }

    public function clearRecipients($type)
    {
        if ($this->assertRecipientType($type)) {
            $this->{$type} = [];
        }
        return $this;
    }

    public function setFromName($name)
    {
        $this->from["name"] = $name;
        return $this;
    }

    public function getFromName()
    {
        return $this->from["name"];
    }

    public function setFromEmail($email)
    {
        $this->from["email"] = $email;
        return $this;
    }

    public function getFromEmail()
    {
        return $this->from["email"];
    }

    public function getFormattedFrom()
    {
        $from = $this->getFromEmail();
        if ($this->getFromName()) {
            $from = $this->getFromName() . "<" . $from . ">";
        }
        return $from;
    }

    public function setReplyTo($email, $name = "")
    {
        $this->replyTo = ["name" => $name, "email" => $email];
        return $this;
    }

    public function getReplyTo()
    {
        return $this->replyTo;
    }

    public function getReplyToName()
    {
        return $this->replyTo["name"];
    }

    public function getReplyToEmail()
    {
        return $this->replyTo["email"];
    }

    public function getFormattedReplyTo()
    {
        $from = $this->getReplyToEmail();
        if ($this->getReplyToName()) {
            $from = $this->getReplyToName() . " <" . $from . ">";
        }
        return $from;
    }

    public function getRecipients($type)
    {
        if ($this->assertRecipientType($type)) {
            return $this->{$type};
        }
        return [];
    }

    public function getRecipientsEmailAddress($type)
    {
        return array_keys($this->getRecipients($type));
    }

    public function getFormattedRecipients($type)
    {
        $recipients = !is_null($type) ? $this->getRecipients($type) : $this->getAllRecipients();
        if (count($recipients) === 0) {
            return [];
        }
        $formattedRecipients = [];
        foreach ($recipients as $recipient) {
            if ($recipient[1]) {
                $formattedRecipients[] = $recipient[1] . " <" . $recipient[0] . ">";
            } else {
                $formattedRecipients[] = $recipient[0];
            }
        }
        return $formattedRecipients;
    }

    public function setSubject($subject)
    {
        $this->subject = \WHMCS\Input\Sanitize::decode($subject);
        return $this;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function setBodyAndPlainText($body)
    {
        $this->setBody($body)->setPlainText($body);
        return $this;
    }

    public function setBody($body)
    {
        if ($this->getType() == "admin") {
            $adminNotification = new AdminNotification();
            $body = $adminNotification->getPreparedHtml($this->getSubject(), $body);
        } else {
            $body = $this->applyGlobalWrapper($body);
        }
        $this->body = $body;
        return $this;
    }

    public function applyGlobalWrapper($blob)
    {
        return $this->appendGlobalFooter($this->appendGlobalHeader($blob));
    }

    public function appendGlobalHeader($blob)
    {
        if (strpos($blob, self::HEADER_MARKER) !== false) {
            return $blob;
        }
        $globalHeader = \WHMCS\Config\Setting::getValue("EmailGlobalHeader");
        if ($globalHeader) {
            $header = \WHMCS\Input\Sanitize::decode($globalHeader) . "\n" . self::HEADER_MARKER;
            $blob = $header . $blob;
        }
        return $blob;
    }

    public function appendGlobalFooter($blob)
    {
        if (strpos($blob, self::FOOTER_MARKER) !== false) {
            return $blob;
        }
        $globalFooter = \WHMCS\Config\Setting::getValue("EmailGlobalFooter");
        if ($globalFooter) {
            $footer = self::FOOTER_MARKER . "\n" . \WHMCS\Input\Sanitize::decode($globalFooter);
            $blob .= $footer;
        }
        return $blob;
    }

    public function setBodyFromSmarty($body)
    {
        $this->body = $body;
        return $this;
    }

    public function getBody()
    {
        $body = $this->body;
        if (!$body) {
            return $body;
        }
        if (strpos($body, "[EmailCSS]") !== false) {
            if ($this->getType() == "admin") {
                $body = str_replace("[EmailCSS]", AdminNotification::getCssStyling(), $body);
            } else {
                $body = str_replace("[EmailCSS]", \WHMCS\Config\Setting::getValue("EmailCSS"), $body);
            }
        } else {
            $body = "<style>" . PHP_EOL . \WHMCS\Config\Setting::getValue("EmailCSS") . PHP_EOL . "</style>" . PHP_EOL . $body;
        }
        return $body;
    }

    public function getBodyWithoutCSS()
    {
        return $this->body;
    }

    public function setPlainText($text)
    {
        $text = \WHMCS\Input\Sanitize::decode($text);
        $text = str_replace(["\r\n</p>\r\n<p>\r\n", "\n</p>\n<p>\n"], "\n\n", $text);
        $text = str_replace(["<br />\r\n", "<br />\n", "<br>\r\n", "<br>\n"], "\n", $text);
        $text = str_replace("<p>", "", $text);
        $text = str_replace("</p>", "\n\n", $text);
        $text = str_replace("<br>", "\n", $text);
        $text = str_replace("<br />", "\n", $text);
        $text = str_replace("[EmailCSS]", "", $text);
        $text = $this->replaceLinksWithUrl($text);
        $text = strip_tags($text);
        $this->bodyPlainText = trim($text);
        return $this;
    }

    protected function replaceLinksWithUrl($text)
    {
        return preg_replace("/<a.*?href=([\\\"])(.*?)\\1.*?<\\/a>/", "\$2", $text);
    }

    public function getPlainText()
    {
        return $this->bodyPlainText;
    }

    public function addStringAttachment($filename, $data)
    {
        $filename = \WHMCS\Input\Sanitize::cleanFilename($filename);
        $this->attachments[] = ["filename" => $filename, "data" => $data];
        return $this;
    }

    public function addFileAttachment($filename, $filepath)
    {
        $filename = \WHMCS\Input\Sanitize::cleanFilename($filename);
        $this->attachments[] = ["filename" => $filename, "filepath" => $filepath];
        return $this;
    }

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function getAttachmentNames()
    {
        $attachments = [];
        foreach ($this->getAttachments() as $attachment) {
            if (isset($attachment["filename"])) {
                $attachments[] = $attachment["filename"];
            }
        }
        return $attachments;
    }

    public function hasRecipients()
    {
        return 0 < count($this->to) + count($this->cc) + count($this->bcc);
    }

    public function saveToEmailLog(int $userId)
    {
        $emailData = ["userid" => $userId, "date" => \WHMCS\Carbon::now()->toDateTimeString(), "to" => implode(", ", $this->getFormattedRecipients("to")), "cc" => implode(", ", $this->getFormattedRecipients("cc")), "bcc" => implode(", ", $this->getFormattedRecipients("bcc")), "subject" => $this->getSubject(), "message" => $this->getBody() ?: $this->getPlainText(), "pending" => false, "failed" => false, "attachments" => $this->getAttachmentNames(), "updated_at" => \WHMCS\Carbon::now()->toDateTimeString()];
        $results = run_hook("EmailPreLog", $emailData);
        foreach ($results as $hookReturn) {
            if (is_array($hookReturn)) {
                foreach ($hookReturn as $key => $value) {
                    if ($key == "abortLogging" && $value === true) {
                        return false;
                    }
                    if (array_key_exists($key, $emailData)) {
                        $emailData[$key] = $value;
                    }
                }
            }
        }
        if (!is_array($emailData["attachments"])) {
            $emailData["attachments"] = [];
        }
        $emailData["attachments"] = json_encode($emailData["attachments"]);
        if (0 < $this->logId) {
            \WHMCS\Database\Capsule::table("tblemails")->where("id", $this->logId)->update($emailData);
            return $this->logId;
        }
        return \WHMCS\Database\Capsule::table("tblemails")->insertGetId($emailData);
    }

    protected function setGlobalBCCRecipients()
    {
        $bccRecipients = \WHMCS\Config\Setting::getValue("BCCMessages");
        if ($bccRecipients) {
            $bccRecipients = explode(",", $bccRecipients);
            foreach ($bccRecipients as $recipient) {
                $recipient = trim($recipient);
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $this->addRecipient("bcc", $recipient, "");
                }
            }
        }
        return $this;
    }

    protected function setEmailHeadersForType($self, $type)
    {
        $headers = [];
        $rfc3834Disabled = (bool) \WHMCS\Config\Setting::getValue("DisableRFC3834");
        if (array_key_exists($type, $this->defaultEmailHeadersByType)) {
            foreach ($this->defaultEmailHeadersByType[$type] as $header => $value) {
                if (!($rfc3834Disabled && in_array($header, self::RFC3834_HEADERS))) {
                    $headers[$header] = $value;
                }
            }
        }
        $this->headers = $headers;
        return $this;
    }

    public function setHeader($self, $name = "", $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
