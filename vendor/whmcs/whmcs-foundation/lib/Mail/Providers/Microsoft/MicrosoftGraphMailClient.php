<?php

namespace WHMCS\Mail\Providers\Microsoft;

class MicrosoftGraphMailClient
{
    protected $guzzle = NULL;
    protected $nextMessagesPageLink = NULL;
    protected $endOfMessages = false;
    protected $userEmailAddress = NULL;
    const BASE_URL = "https://graph.microsoft.com/v1.0/";
    const DEFAULT_RESULTS_PER_CALL = 100;
    const INCOMING_FOLDER_NAME = "inbox";

    public function __construct($token)
    {
        $this->guzzle = new \GuzzleHttp\Client(["base_uri" => static::BASE_URL, \GuzzleHttp\RequestOptions::HEADERS => ["Authorization" => "Bearer " . $token], \GuzzleHttp\RequestOptions::HTTP_ERRORS => true]);
    }

    protected function parseResponse($response)
    {
        $responseContents = $response->getBody()->getContents();
        if ($responseContents === "") {
            return [];
        }
        $data = json_decode($responseContents, true);
        if (is_null($data)) {
            throw new \WHMCS\Exception(sprintf("Invalid response from Microsoft Graph: %s", substr($responseContents, 0, 100)));
        }
        return $data;
    }

    protected function get($uri)
    {
        return $this->parseResponse($this->guzzle->get($uri));
    }

    protected function post($uri, $data = [], $headers)
    {
        if (empty($headers["Content-Type"])) {
            $headers["Content-Type"] = "application/json";
        }
        $requestParams = [\GuzzleHttp\RequestOptions::HEADERS => $headers];
        if (is_string($data)) {
            $requestParams[\GuzzleHttp\RequestOptions::BODY] = $data;
        } else {
            if (is_array($data)) {
                $requestParams[\GuzzleHttp\RequestOptions::FORM_PARAMS] = $data;
            } else {
                throw new \WHMCS\Exception("Invalid data for Microsoft Graph POST operation");
            }
        }
        return $this->parseResponse($this->guzzle->post($uri, $requestParams));
    }

    protected function delete($uri)
    {
        return $this->parseResponse($this->guzzle->delete($uri));
    }

    protected function sanitizeFolderName($mailFolderName)
    {
        $mailFolderName = trim(preg_replace("/[^a-z]+/i", "", $mailFolderName));
        if ($mailFolderName === "") {
            throw new \WHMCS\Exception("Invalid mail folder name");
        }
        return $mailFolderName;
    }

    protected function getMailFolderData($mailFolderName)
    {
        $mailFolderName = $this->sanitizeFolderName($mailFolderName);
        $mailFolderData = $this->parseResponse($this->guzzle->get("me/mailFolders/" . $mailFolderName));
        return ["itemCount" => (int) ($mailFolderData["totalItemCount"] ?? 0)];
    }

    public function getMessageCount($mailFolderName)
    {
        return $this->getMailFolderData($mailFolderName)["itemCount"];
    }

    protected function getMessageIdBatch($maxItems)
    {
        if ($this->endOfMessages) {
            return [];
        }
        if ($this->nextMessagesPageLink) {
            $uri = $this->nextMessagesPageLink;
        } else {
            $uri = sprintf("me/mailFolders/%s/messages?\$select=id&\$top=%d", self::INCOMING_FOLDER_NAME, $maxItems);
        }
        $messageData = $this->get($uri);
        $messages = $messageData["value"] ?? NULL;
        if (!is_array($messages)) {
            throw new \WHMCS\Exception("Invalid response from Microsoft Graph");
        }
        $this->nextMessagesPageLink = $messageData["@odata.nextLink"] ?? NULL;
        if (!$this->nextMessagesPageLink) {
            $this->endOfMessages = true;
        }
        $receivedMessageIds = array_map(function ($item) {
            return $item["id"];
        }, $messages);
        return $receivedMessageIds;
    }

    public function getMessageIds()
    {
        $messageIds = $this->getMessageIdBatch();
        while (!$this->endOfMessages) {
            $messageIds = array_merge($messageIds, $this->getMessageIdBatch());
        }
        return $messageIds;
    }

    public function deleteMessage($messageId)
    {
        if (!trim($messageId)) {
            throw new \WHMCS\Exception("Invalid data for Microsoft Graph DELETE operation");
        }
        $uri = sprintf("me/messages/%s", urlencode($messageId));
        return $this->delete($uri);
    }

    public function sendRfcMessage($rfcMessage)
    {
        return $this->post("me/sendMail", base64_encode($rfcMessage), ["Content-Type" => "text/plain"]);
    }

    public function getMessage($messageId)
    {
        $uri = sprintf("me/messages/%s/\$value", urlencode($messageId));
        return $this->guzzle->get($uri)->getBody()->getContents();
    }

    public function getUserEmailAddress()
    {
        if (!is_null($this->userEmailAddress)) {
            return $this->userEmailAddress;
        }
        $userData = $this->get("me");
        if (empty($userData["userPrincipalName"])) {
            throw new \WHMCS\Exception("Failed to get user data from Microsoft Graph");
        }
        $this->userEmailAddress = $userData["userPrincipalName"];
        return $this->userEmailAddress;
    }

    public static function isUrlRewriteModeValid()
    {
        $uriPath = new \WHMCS\Route\UriPath();
        $pathMode = $uriPath->getMode();
        return in_array($pathMode, [\WHMCS\Route\UriPath::MODE_REWRITE, \WHMCS\Route\UriPath::MODE_ACCEPTPATHINFO]);
    }
}
