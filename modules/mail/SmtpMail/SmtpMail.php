<?php

namespace WHMCS\Module\Mail;

class SmtpMail implements \WHMCS\Module\Contracts\Oauth2SenderModuleInterface, \WHMCS\Module\Contracts\AdminConfigInterface
{
    use \WHMCS\Module\MailSender\DescriptionTrait;
    use \WHMCS\Module\MailSender\Oauth2SenderModuleTrait;
    protected $mailer = NULL;

    public function __construct()
    {
        $this->setDisplayName("SMTP");
    }

    public function settings()
    {
        $smtpCompatibleProviders = array_keys(array_filter(\WHMCS\Mail\MailAuthHandler::PROVIDER_CLASSES, function ($providerClass) {
            return $providerClass::supportsLegacyMailProtocols();
        }));
        $oauthCallbackUrl = \App::getSystemUrl() ? fqdnRoutePath("admin-setup-mail-provider-oauth2-callback") : "";
        return ["encoding" => ["FriendlyName" => \AdminLang::trans("general.mailencoding"), "Type" => "dropdown", "Options" => \WHMCS\Mail\PhpMailer::getValidEncodings(), "Default" => 0], "service_provider" => ["FriendlyName" => \AdminLang::trans("mail.serviceProvider"), "Type" => "dropdown", "Options" => array_merge([\WHMCS\Mail\MailAuthHandler::PROVIDER_GENERIC => \AdminLang::trans("global.generic")], array_combine($smtpCompatibleProviders, $smtpCompatibleProviders)), "Default" => "plain", "Size" => "50"], "host" => ["FriendlyName" => \AdminLang::trans("general.smtphost"), "Type" => "text", "Size" => "50"], "port" => ["FriendlyName" => \AdminLang::trans("general.smtpport"), "Type" => "text", "Size" => "5", "Default" => "465"], "auth_type" => ["FriendlyName" => "SMTP Authentication", "Type" => "dropdown", "Options" => [\WHMCS\Mail\MailAuthHandler::AUTH_TYPE_PLAIN => "Password", \WHMCS\Mail\MailAuthHandler::AUTH_TYPE_OAUTH2 => "Oauth2"], "Default" => "plain", "Size" => "50"], "username" => ["FriendlyName" => \AdminLang::trans("general.smtpusername"), "Type" => "text", "Size" => "50"], "password" => ["FriendlyName" => \AdminLang::trans("general.smtppassword"), "Type" => "password", "Size" => "50"], "oauth2_callback_url" => ["FriendlyName" => \AdminLang::trans("mail.oauth2.callback_url"), "Description" => "<div class=\"input-group\"><input type=\"text\" id=\"smtpOauth2CallbackUrl\" name=\"oauth2_callback_url\" class=\"form-control input-inline input-500\" readonly value=\"" . $oauthCallbackUrl . "\">" . "<span class=\"input-group-btn\"><button class=\"btn btn-default copy-to-clipboard\" " . " data-clipboard-target=\"#smtpOauth2CallbackUrl\" type=\"button\">" . " <img src=\"../assets/img/clippy.svg\" alt=\"Copy to clipboard\" width=\"15\">" . "</button></span>" . "</div>"], "oauth2_client_id" => ["FriendlyName" => \AdminLang::trans("fields.clientid"), "Type" => "text", "Size" => "50"], "oauth2_client_secret" => ["FriendlyName" => \AdminLang::trans("fields.clientsecret"), "Type" => "password", "Size" => "50"], "oauth2_refresh_token" => ["FriendlyName" => \AdminLang::trans("fields.connectiontoken"), "ReadOnly" => "true", "Type" => "password", "Size" => "50"], "secure" => ["FriendlyName" => \AdminLang::trans("general.smtpssltype"), "Type" => "dropdown", "Options" => ["none" => \AdminLang::trans("global.none"), "ssl" => \AdminLang::trans("general.smtpssl"), "tls" => \AdminLang::trans("general.smtptls")], "Default" => "ssl"], "debug" => ["FriendlyName" => \AdminLang::trans("mail.debug"), "Type" => "yesno", "Description" => \AdminLang::trans("mail.debugdescription")]];
    }

    public function testConnection($params)
    {
        $mail = $this->phpMailerInstance($params);
        $fromEmail = \WHMCS\Config\Setting::getValue("SystemEmailsFromEmail");
        $fromName = \WHMCS\Config\Setting::getValue("SystemEmailsFromName");
        $currentAdmin = \WHMCS\User\Admin::getAuthenticatedUser();
        $mail->addAddress($currentAdmin->email, $currentAdmin->fullName);
        $mail->setSenderNameAndEmail($fromName, $fromEmail);
        if ($mail->From != $params["username"]) {
            $mail->clearReplyTos();
            $mail->addReplyTo($fromEmail, $fromName);
        }
        $mail->Subject = \AdminLang::trans("general.emailconfigtestsubject");
        $mail->Body = \AdminLang::trans("general.emailconfigtestbody");
        $this->mailer = $mail;
        $mail->send();
    }

    public function send($params, \WHMCS\Mail\Message $message)
    {
        $mail = $this->phpMailerInstance($params);
        $oauth = $mail->getOAuth();
        if ($oauth instanceof SmtpMail\SmtpOauth) {
            $newRefreshToken = $oauth->getSavedRefreshToken();
            if (!is_null($newRefreshToken)) {
                $this->updateOauth2RefreshToken($newRefreshToken);
            }
        }
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
            if (!empty($mail->Body) && empty($mail->AltBody)) {
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
        $mail->IsSMTP();
        $mail->SMTPAutoTLS = false;
        $mail->Host = $params["host"];
        $mail->Port = $params["port"];
        $mail->Hostname = $mail->serverHostname();
        if ($params["secure"]) {
            $mail->SMTPSecure = $params["secure"];
        }
        if ($params["username"]) {
            $mail->SMTPAuth = true;
            if (empty($params["auth_type"]) || $params["auth_type"] === \WHMCS\Mail\MailAuthHandler::AUTH_TYPE_PLAIN) {
                $mail->Username = $params["username"];
                $mail->Password = $params["password"];
            } else {
                $mail->AuthType = "XOAUTH2";
                $oauthHandler = new \WHMCS\Mail\MailAuthHandler();
                $oauth = new SmtpMail\SmtpOauth(["provider" => $oauthHandler->createProvider($params["service_provider"], $params["oauth2_client_id"], $params["oauth2_client_secret"], \WHMCS\Mail\MailAuthHandler::CONTEXT_OUTGOING_MAIL), "userName" => $params["username"], "clientId" => $params["oauth2_client_id"], "clientSecret" => $params["oauth2_client_secret"], "refreshToken" => $params["oauth2_refresh_token"]]);
                $mail->setOAuth($oauth);
            }
        }
        if ($params["debug"]) {
            $mail->SMTPDebug = 4;
            $mail->Debugoutput = function ($string, $level) {
                if (0 < $level) {
                    logActivity("SMTP Debug: " . $string);
                }
            };
        }
        $mail->XMailer = \WHMCS\Config\Setting::getValue("CompanyName");
        $mail->CharSet = \WHMCS\Config\Setting::getValue("Charset");
        return $mail;
    }

    public function getExtraAdminConfig()
    {
        return view("admin/setup/mail/providers/smtp_mail");
    }

    public function validateEnvironment()
    {
        $warnings = [];
        $systemUrl = \App::getSystemURL();
        if (empty($systemUrl)) {
            $string = \AdminLang::trans("mail.error.systemUrlMissing");
            $warnings[] = \WHMCS\View\Helper::alert($string, "warning", "oauth-only hidden");
        }
        return $warnings;
    }
}
