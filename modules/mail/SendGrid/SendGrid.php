<?php

namespace WHMCS\Module\Mail;

use Psr\Http\Message\ResponseInterface;

class SendGrid implements \WHMCS\Module\Contracts\SenderModuleInterface
{
    use \WHMCS\Module\MailSender\DescriptionTrait;
    const URL = "https://api.sendgrid.com/v3/";

    public function __construct()
    {
        $this->setDisplayName("SendGrid");
    }

    public function settings()
    {
        return ["key" => ["FriendlyName" => \AdminLang::trans("mail.sendGrid.apiKey"), "Type" => "password", "Size" => "50"]];
    }

    public function testConnection($params)
    {
        $forbiddenMessages = ["authorization required", "access forbidden"];
        try {
            $guzzle = $this->getGuzzleClient($params, false)->get("mail_settings");
            $request = $this->parseResponse($guzzle, "testConnection");
            if ($request->getStatusCode() < 400) {
                return NULL;
            }
            throw new \WHMCS\Exception\Module\InvalidConfiguration("Invalid HTTP Status Code Received: " . $request->getStatusCode());
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Module\InvalidConfiguration($e->getMessage());
        }
    }

    public function send($params, \WHMCS\Mail\Message $message)
    {
        $item = ["personalizations" => [["to" => NULL]], "from" => ["email" => $message->getFromEmail(), "name" => $message->getFromName()], "subject" => $message->getSubject(), "content" => $this->makeContent($message)];
        foreach (["to", "cc", "bcc"] as $kind) {
            $recipients = $message->getRecipients($kind);
            if ($kind == "to" && empty($recipients)) {
                throw new \WHMCS\Exception\Mail\SendFailure("To recipient is required by SendGrid");
            }
            if (!empty($recipients)) {
                $item["personalizations"][0][$kind] = [];
                foreach ($recipients as $easytoyou_error_decompile) {
                    list($email, $name) = $easytoyou_error_decompile;
                    $item["personalizations"][0][$kind][] = $this->makeRecipient($email, $name);
                }
            }
        }
        foreach ($message->getAttachments() as $attachment) {
            if (array_key_exists("data", $attachment)) {
                $item["attachments"][] = ["filename" => $attachment["filename"], "content" => base64_encode($attachment["data"])];
            } else {
                $item["attachments"][] = ["filename" => $attachment["filename"], "content" => base64_encode(file_get_contents($attachment["filepath"]))];
            }
        }
        if ($message->getReplyTo()) {
            $item["reply_to"]["email"] = $message->getReplyToEmail();
            $item["reply_to"]["name"] = $message->getReplyToName();
        } else {
            $item["reply_to"]["email"] = $message->getFromEmail();
            $item["reply_to"]["name"] = $message->getFromName();
        }
        foreach ($message->getHeaders() as $header => $value) {
            $item["headers"][$header] = $value;
        }
        try {
            $guzzle = $this->getGuzzleClient($params, false);
            $request = $guzzle->post("mail/send", ["json" => $item]);
            $this->parseResponse($request, "send", $item);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (substr($message, 0, 8) == "transfer") {
                $message = "Access Denied: Please Check Mail Configuration";
            }
            throw new \WHMCS\Exception\Mail\SendFailure($message);
        }
    }

    protected function makeRecipient($email, $name = NULL)
    {
        return ["email" => $email, "name" => $name ?? ""];
    }

    protected function makeContent($message)
    {
        $content = [["type" => "text/plain", "value" => $message->getPlainText()]];
        $htmlPart = $message->getBody();
        if (!empty($htmlPart)) {
            $content[] = ["type" => "text/html", "value" => $htmlPart];
        }
        unset($htmlPart);
        return $content;
    }

    protected function getGuzzleClient($params, $exceptions = true)
    {
        $url = self::URL;
        return new \WHMCS\Http\Client\HttpClient(["base_uri" => $url, "headers" => ["Content-Type" => "application/json", "Authorization" => "Bearer " . $params["key"]], \GuzzleHttp\RequestOptions::HTTP_ERRORS => $exceptions]);
    }

    protected function parseResponse($response, $action, $request = []): ResponseInterface
    {
        $forbiddenMessages = ["authorization required", "access forbidden"];
        $statusCode = (int) $response->getStatusCode();
        $responseData = $response->getBody()->getContents();
        $success = false;
        if (in_array($statusCode, [200, 202])) {
            $success = true;
        }
        $responseDecoded = json_decode($responseData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $responseDecoded = $responseData;
        }
        logModuleCall("SendGrid", $action, $request, $responseData);
        if (!$success) {
            if (is_array($responseDecoded) && array_key_exists("errors", $responseDecoded)) {
                $errors = [];
                foreach ($responseDecoded["errors"] as $key => $data) {
                    if (in_array($data["message"], $forbiddenMessages)) {
                        throw new \WHMCS\Exception\Mail\SendFailure("Access Denied. Check the API Key");
                    }
                    $errors[] = $data["message"];
                }
                $message = implode("\r\n", $errors);
            } else {
                $message = $responseDecoded;
            }
            if (strpos($message, "not contain a valid address") !== false) {
                throw new \WHMCS\Exception\Mail\InvalidAddress($message);
            }
            throw new \WHMCS\Exception\Mail\SendFailure($message);
        } else {
            return $response;
        }
    }
}
