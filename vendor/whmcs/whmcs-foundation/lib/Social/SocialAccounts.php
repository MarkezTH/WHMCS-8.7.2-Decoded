<?php

namespace WHMCS\Social;

class SocialAccounts
{
    protected $names = ["bitbucket" => "BitBucket", "discord" => "Discord", "facebook" => "Facebook", "flickr" => "Flickr", "github" => "GitHub", "gitter" => "Gitter", "instagram" => "Instagram", "linkedin" => "LinkedIn", "reddit" => "Reddit", "skype" => "Skype", "slack" => "Slack", "twitter" => "Twitter", "viber" => "Viber", "vimeo" => "Vimeo", "whatsapp" => "WhatsApp", "youtube" => "YouTube"];
    protected $fontAwesomeIcons = ["bitbucket" => "fa-bitbucket", "discord" => "fa-discord", "facebook" => "fa-facebook-f", "flickr" => "fa-flickr", "github" => "fa-github", "gitter" => "fa-gitter", "instagram" => "fa-instagram", "linkedin" => "fa-linkedin", "reddit" => "fa-reddit", "skype" => "fa-skype", "slack" => "fa-slack", "twitter" => "fa-twitter", "viber" => "fa-viber", "vimeo" => "fa-vimeo", "whatsapp" => "fa-whatsapp", "youtube" => "fa-youtube"];
    protected $configurationNotes = ["discord" => "Since Discord is invite based, you must generate a permenent invite URL and enter the part after https://discord.gg/ here", "linkedin" => "Requires a named company page - does not support individuals", "slack" => "Enter Slack workspace ID", "whatsapp" => "Enter phone number registered for WhatsApp including country prefix"];
    protected $urls = ["bitbucket" => "https://bitbucket.org/{id}", "discord" => "https://discord.gg/{id}", "facebook" => "https://www.facebook.com/{id}", "flickr" => "https://www.flickr.com/{id}", "github" => "https://github.com/{id}", "gitter" => "https://gitter.im/{id}", "instagram" => "https://www.instagram.com/{id}", "linkedin" => "https://www.linkedin.com/company/{id}", "reddit" => "https://www.reddit.com/r/{id}", "skype" => "skype:{id}?call", "slack" => "https://{id}.slack.com/", "twitter" => "https://www.twitter.com/{id}", "viber" => "https://viber.me/{id}", "vimeo" => "https://www.vimeo.com/{id}", "whatsapp" => "https://wa.me/{id}", "youtube" => "https://www.youtube.com/{id}"];
    const STORAGE_KEY = "SocialAccounts";

    public function get($key)
    {
        $config = \WHMCS\Config\Setting::getValue(self::STORAGE_KEY);
        $decoded = json_decode($config, true);
        return is_array($decoded) && array_key_exists($key, $decoded) ? $decoded[$key] : NULL;
    }

    public function save($data)
    {
        $dataToSave = [];
        foreach ($this->names as $name => $displayName) {
            $value = array_key_exists($name, $data) ? $data[$name] : NULL;
            if ($value) {
                $dataToSave[$name] = $value;
            }
        }
        \WHMCS\Config\Setting::setValue(self::STORAGE_KEY, json_encode($dataToSave));
        return $this;
    }

    public function getAll()
    {
        $accts = [];
        foreach ($this->names as $name => $displayName) {
            $configNote = array_key_exists($name, $this->configurationNotes) ? $this->configurationNotes[$name] : NULL;
            $icon = array_key_exists($name, $this->fontAwesomeIcons) ? $this->fontAwesomeIcons[$name] : NULL;
            $value = $this->get($name);
            $url = array_key_exists($name, $this->urls) ? $this->urls[$name] : NULL;
            $accts[] = new SocialAccount($name, $displayName, $icon, $configNote, $value, $url);
        }
        return $accts;
    }

    public function getConfigured()
    {
        $accts = $this->getAll();
        foreach ($accts as $key => $acct) {
            if (is_null($acct->getValue())) {
                unset($accts[$key]);
            }
        }
        return $accts;
    }
}
