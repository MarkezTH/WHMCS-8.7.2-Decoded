<?php

namespace WHMCS\Module\Mail;

use WHMCS\Mail\Providers\Microsoft\MicrosoftGraphMailClient;

class MicrosoftGraph implements \WHMCS\Module\Contracts\Oauth2SenderModuleInterface, \WHMCS\Module\Contracts\AdminConfigInterface
{
    use \WHMCS\Module\MailSender\DescriptionTrait;
    use \WHMCS\Module\MailSender\Oauth2SenderModuleTrait;
    private $msGraphClient = NULL;
    protected $isConnectionTest = false;

    public function __construct()
    {
        $this->setDisplayName("Microsoft");
    }

    public function settings()
    {
        $oauthCallbackUrl = \App::getSystemURL() ? fqdnRoutePath("admin-setup-mail-provider-oauth2-callback") : "";
        return ["oauth2_callback_url" => ["FriendlyName" => \AdminLang::trans("mail.oauth2.callback_url"), "Description" => "<div class=\"input-group\">\n    <input type=\"text\" id=\"smtpOauth2CallbackUrl\" name=\"oauth2_callback_url\"\n        class=\"form-control input-inline input-500\" readonly\n        value=\"" . $oauthCallbackUrl . "\">\n    <span class=\"input-group-btn\">\n        <button class=\"btn btn-default copy-to-clipboard\"\n            data-clipboard-target=\"#smtpOauth2CallbackUrl\" type=\"button\">\n            <img src=\"../assets/img/clippy.svg\" alt=\"Copy to clipboard\" width=\"15\">\n        </button>\n    </span>\n</div>"], "oauth2_client_id" => ["FriendlyName" => \AdminLang::trans("fields.microsoftappid"), "Type" => "text", "Size" => "50"], "oauth2_client_secret" => ["FriendlyName" => \AdminLang::trans("fields.clientsecret"), "Type" => "password", "Size" => "50"], "oauth2_refresh_token" => ["FriendlyName" => \AdminLang::trans("fields.connectiontoken"), "ReadOnly" => "true", "Type" => "password", "Size" => "50"], "debug" => ["FriendlyName" => \AdminLang::trans("mail.debug"), "Type" => "yesno", "Description" => \AdminLang::trans("mail.debugdescription")]];
    }

    public function testConnection($params)
    {
        $this->isConnectionTest = true;
        $currentAdmin = \WHMCS\User\Admin::getAuthenticatedUser();
        $message = new \WHMCS\Mail\Message();
        $message->addRecipient("to", $currentAdmin->email, $currentAdmin->fullName);
        $message->setSubject(\AdminLang::trans("general.emailconfigtestsubject"));
        $message->setPlainText(\AdminLang::trans("general.emailconfigtestbody"));
        $this->send($params, $message);
    }

    protected function getMsGraphClient($params): MicrosoftGraphMailClient
    {
        if ($this->msGraphClient) {
            return $this->msGraphClient;
        }
        $oauthHandler = new \WHMCS\Mail\MailAuthHandler();
        $authProvider = $oauthHandler->createProvider(\WHMCS\Mail\MailAuthHandler::PROVIDER_MICROSOFT, $params["oauth2_client_id"], $params["oauth2_client_secret"], \WHMCS\Mail\MailAuthHandler::CONTEXT_OUTGOING_MAIL);
        $accessToken = $authProvider->getAccessToken(new \League\OAuth2\Client\Grant\RefreshToken(), ["refresh_token" => $params["oauth2_refresh_token"]]);
        if (!$this->isConnectionTest) {
            $newRefreshToken = $accessToken->getRefreshToken();
            if (!is_null($newRefreshToken)) {
                $this->updateOauth2RefreshToken($newRefreshToken);
            }
        }
        $this->msGraphClient = new \WHMCS\Mail\Providers\Microsoft\MicrosoftGraphMailClient($accessToken->getToken());
        return $this->msGraphClient;
    }

    public function send($params, \WHMCS\Mail\Message $message)
    {
        $mail = new \WHMCS\Mail\PhpMailer(true);
        $mail->CharSet = \WHMCS\Config\Setting::getValue("Charset");
        $mail->isSMTP();
        try {
            $msClient = $this->getMsGraphClient($params);
            $msUserEmailAddress = $msClient->getUserEmailAddress();
        } catch (\Throwable $e) {
            if ($params["debug"]) {
                logActivity(sprintf("%s Mail Service: %s", $this->getDisplayName(), $e->getMessage()));
            }
            throw new \WHMCS\Exception\Mail\SendFailure($e->getMessage());
        }
        try {
            foreach ($message->getRecipients("to") as $to) {
                $mail->addAddress($to[0], $to[1]);
            }
            foreach ($message->getRecipients("cc") as $to) {
                $mail->addCC($to[0], $to[1]);
            }
            $bccHeader = $mail->addrAppend("Bcc", $message->getRecipients("bcc"));
            $mail->addCustomHeader($bccHeader);
            $mail->setSenderNameAndEmail($message->getFromName(), $msUserEmailAddress);
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
        $mail->preSend();
        $rfcMailContent = $mail->getRfcMailContent();
        try {
            $msClient->sendRfcMessage($rfcMailContent);
        } catch (\Throwable $e) {
            if ($params["debug"]) {
                logActivity(sprintf("%s Mail Service: %s", $this->getDisplayName(), $e->getMessage()));
            }
            throw new \WHMCS\Exception\Mail\SendFailure($e->getMessage());
        }
    }

    public function getExtraAdminConfig()
    {
        return view("admin/setup/mail/providers/microsoft_graph");
    }

    public function validateEnvironment()
    {
        $warnings = [];
        $systemUrl = \App::getSystemURL();
        if (empty($systemUrl)) {
            $string = \AdminLang::trans("mail.error.systemUrlMissing");
            $warnings[] = \WHMCS\View\Helper::alert($string, "warning");
        } else {
            if (!\WHMCS\Mail\Providers\Microsoft\MicrosoftGraphMailClient::isUrlRewriteModeValid()) {
                $string = \AdminLang::trans("mail.error.friendlyUrlModeInvalid");
                $warnings[] = \WHMCS\View\Helper::alert($string, "warning");
            }
        }
        return $warnings;
    }
}
