<?php

namespace WHMCS\Module\Mail;

class Mailgun implements \WHMCS\Module\Contracts\SenderModuleInterface
{
    use \WHMCS\Module\MailSender\DescriptionTrait;
    const URL = "https://api.mailgun.net/v3/";
    const EU_URL = "https://api.eu.mailgun.net/v3/";

    public function __construct()
    {
        $this->setDisplayName("Mailgun");
    }

    public function settings()
    {
        return ["host" => ["FriendlyName" => \AdminLang::trans("mail.mailgun.accountType"), "Type" => "dropdown", "Options" => ["eu" => \AdminLang::trans("mail.mailgun.EU"), "other" => \AdminLang::trans("mail.mailgun.nonEU")], "Default" => "other"], "domain" => ["FriendlyName" => \AdminLang::trans("mail.mailgun.sendingDomain"), "Type" => "text", "Size" => "50"], "key" => ["FriendlyName" => \AdminLang::trans("mail.mailgun.apiKey"), "Type" => "password", "Size" => "50"]];
    }

    public function testConnection($params)
    {
        try {
            $this->parseResponse($this->getGuzzleClient($params)->get("stats/total?event=accepted"), "testConnection");
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration($e->getMessage());
        }
    }

    public function send($params, \WHMCS\Mail\Message $message)
    {
        $guzzle = $this->getGuzzleClient($params);
        $body = [["name" => "from", "contents" => $message->getFormattedFrom()], ["name" => "subject", "contents" => $message->getSubject()], ["name" => "text", "contents" => $message->getPlainText()], ["name" => "html", "contents" => $message->getBody()]];
        foreach (\WHMCS\Mail\Message::RECIPIENT_TYPES as $recipientType) {
            $emails = $message->getFormattedRecipients($recipientType);
            if (is_array($emails) && 0 < count($emails)) {
                $body[] = ["name" => $recipientType, "contents" => implode(",", $emails)];
            }
        }
        foreach ($message->getAttachments() as $attachment) {
            if (array_key_exists("data", $attachment)) {
                $body[] = ["name" => "attachment", "contents" => $attachment["data"], "filename" => $attachment["filename"]];
            } else {
                $body[] = ["name" => "attachment", "contents" => file_get_contents($attachment["filepath"]), "filename" => $attachment["filename"]];
            }
        }
        foreach ($message->getHeaders() as $header => $value) {
            $body[] = ["name" => "h:" . $header, "contents" => $value];
        }
        if ($message->getReplyTo()) {
            $body[] = ["name" => "h:reply-to", "contents" => $message->getFormattedReplyTo()];
        } else {
            $body[] = ["name" => "h:reply-to", "contents" => $message->getFormattedFrom()];
        }
        $this->parseResponse($guzzle->request("POST", "messages", ["multipart" => $body]), "send", $body);
    }

    protected function getGuzzleClient($params)
    {
        $url = self::URL;
        if ($params["host"] == "eu") {
            $url = self::EU_URL;
        }
        return new \WHMCS\Http\Client\HttpClient(["base_uri" => $url . $params["domain"] . "/", "headers" => ["Authorization" => "Basic " . base64_encode("api:" . $params["key"])], \GuzzleHttp\RequestOptions::HTTP_ERRORS => false]);
    }

    protected function parseResponse($response, $action, $request = [])
    {
        $body = $response->getBody()->getContents();
        logModuleCall("MailGun", $action, $request, $body);
        if ($response->getStatusCode() !== 200) {
            $parsedBody = json_decode($body, true);
            $errorMsg = "";
            if (is_array($parsedBody)) {
                if (!empty($parsedBody["message"])) {
                    $errorMsg = $parsedBody["message"];
                } else {
                    if (!empty($parsedBody["error"])) {
                        $errorMsg = $parsedBody["error"];
                    }
                }
            }
            if (!$errorMsg) {
                $errorMsg = "An unknown error occurred";
            }
            if (strpos($errorMsg, "not a valid address") !== false) {
                throw new \WHMCS\Exception\Mail\InvalidAddress($errorMsg);
            }
            throw new \WHMCS\Exception\Mail\SendFailure($errorMsg);
        }
    }
}
