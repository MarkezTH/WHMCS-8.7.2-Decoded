<?php

namespace WHMCS\Module\Mail;

class PhpMail implements \WHMCS\Module\Contracts\SenderModuleInterface
{
    use \WHMCS\Module\MailSender\DescriptionTrait;
    protected $mailer = NULL;

    public function __construct()
    {
        $this->setDisplayName("PHP Mail (Default)");
    }

    public function settings()
    {
        return ["encoding" => ["FriendlyName" => \AdminLang::trans("general.mailencoding"), "Type" => "dropdown", "Options" => \WHMCS\Mail\PhpMailer::getValidEncodings(), "Default" => 0]];
    }

    public function testConnection($params)
    {
        $mail = $this->phpMailerInstance($params);
        $fromEmail = \WHMCS\Config\Setting::getValue("SystemEmailsFromEmail");
        $fromName = \WHMCS\Config\Setting::getValue("SystemEmailsFromName");
        $currentAdmin = \WHMCS\User\Admin::find(\WHMCS\Session::get("adminid"));
        $mail->addAddress($currentAdmin->email, $currentAdmin->fullName);
        if (\WHMCS\Config\Setting::getValue("BCCMessages")) {
            $bcc = \WHMCS\Config\Setting::getValue("BCCMessages");
            $bcc = explode(",", $bcc);
            foreach ($bcc as $value) {
                if (trim($value)) {
                    $mail->addBCC($value);
                }
            }
        }
        $mail->setSenderNameAndEmail($fromName, $fromEmail);
        $mail->Subject = \AdminLang::trans("general.emailconfigtestsubject");
        $mail->Body = \AdminLang::trans("general.emailconfigtestbody");
        $this->mailer = $mail;
        $mail->send();
    }

    public function send($params, \WHMCS\Mail\Message $message)
    {
        $mail = $this->phpMailerInstance($params);
        try {
            foreach ($message->getRecipients("to") as $to) {
                $mail->addAddress($to[0], $to[1]);
            }
            foreach ($message->getRecipients("cc") as $to) {
                $mail->addCC($to[0], $to[1]);
            }
            foreach ($message->getRecipients("bcc") as $to) {
                $mail->addBCC($to[0], $to[1]);
            }
            $mail->setSenderNameAndEmail($message->getFromName(), $message->getFromEmail());
            if ($message->getReplyTo()) {
                $mail->addReplyTo($message->getReplyToEmail(), $message->getReplyToName());
            } else {
                $mail->addReplyTo($message->getFromEmail(), $message->getFromName());
            }
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Mail\InvalidAddress($e->getMessage());
        }
        $mail->Subject = $message->getSubject();
        $body = $message->getBody();
        $plainText = $message->getPlainText();
        if ($body) {
            $mail->Body = $body;
            $mail->AltBody = $plainText;
            if (!empty($this->Body) && empty($this->AltBody)) {
                $mail->AltBody = " ";
            }
        } else {
            $mail->Body = $plainText;
        }
        foreach ($message->getAttachments() as $attachment) {
            if (array_key_exists("data", $attachment)) {
                $mail->AddStringAttachment($attachment["data"], $attachment["filename"]);
            } else {
                $mail->addAttachment($attachment["filepath"], $attachment["filename"]);
            }
        }
        foreach ($message->getHeaders() as $header => $value) {
            $mail->addCustomHeader($header, $value);
        }
        $this->mailer = $mail;
        $mail->send();
    }

    protected function phpMailerInstance($params)
    {
        $mail = new \WHMCS\Mail\PhpMailer(true);
        $mail->setEncoding((int) $params["encoding"]);
        $mail->isMail();
        $mail->XMailer = \WHMCS\Config\Setting::getValue("CompanyName");
        $mail->CharSet = \WHMCS\Config\Setting::getValue("Charset");
        return $mail;
    }
}
