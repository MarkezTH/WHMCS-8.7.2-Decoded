<?php

namespace WHMCS\Module\Mail;

class SparkPost implements \WHMCS\Module\Contracts\SenderModuleInterface
{
    use \WHMCS\Module\MailSender\DescriptionTrait;
    const URL = "https://api.sparkpost.com/api/v1/";
    const EU_URL = "https://api.eu.sparkpost.com/api/v1/";

    public function __construct()
    {
        $this->setDisplayName("SparkPost");
    }
    public function settings()
    {
        return ["host" => ["FriendlyName" => \AdminLang::trans("mail.sparkPost.accountType"), "Type" => "dropdown", "Options" => ["eu" => \AdminLang::trans("mail.sparkPost.EU"), "other" => \AdminLang::trans("mail.sparkPost.nonEU")], "Default" => "other"], "key" => ["FriendlyName" => \AdminLang::trans("mail.sparkPost.apiKey"), "Type" => "password", "Size" => "50"], "sink" => ["FriendlyName" => \AdminLang::trans("mail.sparkPost.sink"), "Type" => "yesno", "Description" => \AdminLang::trans("mail.sparkPost.sinkInfo")]];
    }
    public function testConnection($params)
    {
        try {
            if (!extension_loaded("fileinfo")) {
                throw new \WHMCS\Exception\Module\InvalidConfiguration("The 'fileinfo' extension is required to send emails with SparkPost");
            }
            $this->parseResponse($this->getGuzzleClient($params)->get("account"), "testConnection");
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration($e->getMessage());
        }
    }
    public function send($params, \WHMCS\Mail\Message $message)
    {
        $recipients = [];
        $firstTo = "";
        $ccEmails = [];
        $sink = "";
        if ($params["sink"]) {
            $sink = ".sink.sparkpostmail.com";
        }
        foreach ($message->getRecipients("to") as $to) {
            if (!$firstTo) {
                $firstTo = $to[0];
            }
            $recipients[] = ["address" => ["email" => $to[0] . $sink, "name" => $to[1]]];
        }
        foreach (\WHMCS\Mail\Message::RECIPIENT_TYPES as $recipientType) {
            if ($recipientType != "to") {
                foreach ($message->getRecipients($recipientType) as $recipient) {
                    $recipients[] = ["address" => ["email" => $recipient[0] . $sink, "name" => $recipient[1], "header_to" => $firstTo]];
                    if ($recipientType == "cc") {
                        $ccEmails[] = $recipient[0] . $sink;
                    }
                }
            }
        }
        $item = ["options" => ["click_tracking" => true], "recipients" => $recipients, "content" => ["from" => ["name" => $message->getFromName(), "email" => $message->getFromEmail()], "subject" => $message->getSubject(), "text" => $message->getPlainText(), "html" => $message->getBody()]];
        if (0 < count($ccEmails)) {
            $item["content"]["headers"]["CC"] = implode(",", $ccEmails);
        }
        if ($message->getReplyTo()) {
            $item["content"]["reply_to"] = $message->getFormattedReplyTo();
        } else {
            $item["content"]["reply_to"] = $message->getFormattedFrom();
        }
        foreach ($message->getAttachments() as $attachment) {
            if (array_key_exists("data", $attachment)) {
                $fileInfo = new \finfo(FILEINFO_MIME);
                $mimeInfo = $fileInfo->buffer($attachment["data"]);
                $item["content"]["attachments"][] = ["name" => $attachment["filename"], "type" => $mimeInfo, "data" => base64_encode($attachment["data"])];
            } else {
                $fileInfo = new \finfo(FILEINFO_MIME);
                $mimeInfo = $fileInfo->file($attachment["filepath"]);
                $item["content"]["attachments"][] = ["name" => $attachment["filename"], "type" => $mimeInfo, "data" => base64_encode(file_get_contents($attachment["filepath"]))];
            }
        }
        foreach ($message->getHeaders() as $header => $value) {
            $item["content"]["headers"][$header] = $value;
        }
        $this->parseResponse($this->getGuzzleClient($params)->post("transmissions", ["json" => $item]), "send", $item);
    }
    protected function getGuzzleClient($params)
    {
        $url = self::URL;
        if ($params["host"] == "eu") {
            $url = self::EU_URL;
        }
        return new \WHMCS\Http\Client\HttpClient(["base_uri" => $url, "headers" => ["Content-Type" => "application/json", "Authorization" => $params["key"]], \GuzzleHttp\RequestOptions::HTTP_ERRORS => false]);
    }
    protected function parseResponse($response, $action, $request = [])
    {
        $body = $response->getBody()->getContents();
        logModuleCall("SparkPost", $action, $request, $body);
        if (400 <= $response->getStatusCode()) {
            $message = "";
            $body = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($body["errors"]) && is_array($body["errors"])) {
                foreach ($body["errors"] as $error) {
                    if (!empty($error["message"])) {
                        $message .= \WHMCS\Input\Sanitize::encode($error["message"]) . PHP_EOL;
                    }
                }
            }
            if (strpos($message, "one valid recipient") !== false) {
                throw new \WHMCS\Exception\Mail\InvalidAddress($message);
            }
            if (!$message) {
                $response->getStatusCode();
                switch ($response->getStatusCode()) {
                    case 400:
                    case 405:
                    case 422:
                        $message = "There is a problem with your request.";
                        break;
                    case 401:
                    case 403:
                        $message = "Access Denied. Please check the API Key.";
                        break;
                    case 429:
                        $message = "Too many requests. Please try again later";
                        break;
                    case 500:
                    case 503:
                        $message = "Remote server failure. Please try again later";
                        break;
                    default:
                        $message = "An Unknown Error Occurred. Please try again";
                }
            }
            throw new \WHMCS\Exception\Mail\SendFailure($message);
        }
    }
}
