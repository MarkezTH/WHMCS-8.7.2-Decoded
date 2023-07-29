<?php

namespace WHMCS\Module\Notification\Slack;

class Slack implements \WHMCS\Module\Contracts\NotificationModuleInterface
{
    use \WHMCS\Module\Notification\DescriptionTrait;
    const API_URL = "https://slack.com/api/";

    public function __construct()
    {
        $this->setDisplayName("Slack")->setLogoFileName("logo.png");
    }

    public function settings()
    {
        $helpText = \AdminLang::trans("help.title");
        $helpLink = "<div class=\"pull-right\">\n    <a href=\"https://docs.whmcs.com/Configuring_Notifications_with_Slack\"\n       class=\"btn btn-default btn-xs\"\n       target=\"_blank\"\n    >\n        <i class=\"fal fa-lightbulb\"></i>\n        " . $helpText . "\n    </a>\n</div>";
        return ["oauth_token" => ["FriendlyName" => "OAuth Access Token" . $helpLink, "Type" => "text", "Description" => "An OAuth token for the Custom App you have installed in your Slack workspace. Your App needs the \"channels:read\", \"channels:join\" and \"chat:write\" scopes. If you wish to notify private channels, the scope \"groups:read\" is also required."]];
    }

    public function testConnection($settings)
    {
        $uri = "conversations.list";
        $postdata = ["limit" => "1"];
        try {
            $this->call($settings, $uri, $postdata);
        } catch (\WHMCS\Exception $e) {
            $errorMsg = $e->getMessage();
            if ($errorMsg == "An error occurred: invalid_auth") {
                $errorMsg = "Token is invalid. Please check your input and try again.";
            }
            throw new \WHMCS\Exception($errorMsg);
        }
    }

    public function notificationSettings()
    {
        return ["channel" => ["FriendlyName" => "Channel", "Type" => "dynamic", "Description" => "Select the desired channel for a notification.<br>Private Channels are shown with *", "Required" => true], "message" => ["FriendlyName" => "Customise Message", "Type" => "text", "Description" => "Allows you to customise the primary display message shown in the notification."]];
    }

    public function getDynamicField($fieldName, $settings)
    {
        if ($fieldName == "channel") {
            $uri = "conversations.list";
            $postdata = ["types" => "public_channel,private_channel", "limit" => "2000", "exclude_members" => true, "exclude_archived" => true];
            $response = $this->call($settings, $uri, $postdata);
            $channels = [];
            foreach ($response->channels as $channel) {
                $channelName = $channel->name;
                if ($channel->is_group && $channel->is_private) {
                    $channelName .= "*";
                }
                $channels[] = ["id" => $channel->id, "name" => $channelName];
            }
            usort($channels, function ($a, $b) {
                return strnatcmp($a["name"], $b["name"]);
            });
            return ["values" => $channels];
        } else {
            return [];
        }
    }

    public function sendNotification(\WHMCS\Notification\Contracts\NotificationInterface $notification, $moduleSettings, $notificationSettings)
    {
        $messageBody = $notification->getMessage();
        if ($notificationSettings["message"]) {
            $messageBody = $notificationSettings["message"];
        }
        $attachment = (new Attachment())->fallback($messageBody . " " . $notification->getUrl())->title(\WHMCS\Input\Sanitize::decode($notification->getTitle()))->title_link($notification->getUrl())->text($messageBody);
        foreach ($notification->getAttributes() as $attribute) {
            $value = $attribute->getValue();
            if ($attribute->getUrl()) {
                $value = "<" . $attribute->getUrl() . "|" . $value . ">";
            }
            $attachment->addField((new Field())->title($attribute->getLabel())->value($value)->short());
        }
        $channel = $notificationSettings["channel"];
        $channel = explode("|", $channel, 2);
        $channelId = $channel[0];
        $message = (new Message())->channel($channelId)->username("WHMCS Bot")->attachment($attachment);
        $uri = "chat.postMessage";
        $this->call($moduleSettings, $uri, $message->toArray());
    }

    protected function call($settings, $uri, $postdata = [], $throwOnError = true)
    {
        $postdata["token"] = $settings["oauth_token"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL . $uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode($response);
        logModuleCall("slack", $uri, $postdata, $response, $decoded, [$settings["oauth_token"]]);
        if (!isset($decoded->ok)) {
            throw new \WHMCS\Exception("Bad response: " . $response);
        }
        if (!$decoded->ok && $throwOnError) {
            throw new \WHMCS\Exception("An error occurred: " . $decoded->error);
        }
        return $decoded;
    }

    public function postRuleSave($moduleConfiguration, $providerConfig)
    {
        $channel = $providerConfig["channel"];
        $channel = explode("|", $channel, 2);
        list($channelId, $channelName) = $channel;
        $uri = "conversations.join";
        $postData = ["channel" => $channelId];
        $response = $this->call($moduleConfiguration, $uri, $postData, false);
        if ($response->ok === false) {
            $errorMsg = \WHMCS\Input\Sanitize::encode($response->needed ?? "");
            switch ($response->error) {
                case "missing_scope":
                case "no_permission":
                    throw new \WHMCS\Exception\Information("Missing Scope: Your App needs the \"" . $errorMsg . "\" scope");
                    break;
                case "method_not_supported_for_channel_type":
                    throw new \WHMCS\Exception\Information("Private channels cannot be automatically joined. Ensure your App is invited to " . $channelName);
                    break;
                case "channel_not_found":
                default:
                    throw new \WHMCS\Exception("An error occurred: " . $response->error);
            }
        }
    }
}
