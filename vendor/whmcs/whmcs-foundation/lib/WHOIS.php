<?php

namespace WHMCS;

class WHOIS
{
    protected $definitions = [];
    protected $definitionsPath = NULL;
    protected $socketPrefix = "socket://";
    private $domain = "";

    public function __construct($definitionsPath = "")
    {
        if (!empty($definitionsPath)) {
            $this->definitionsPath = $definitionsPath;
        }
        $this->load();
    }

    protected function load()
    {
        $path = ROOTDIR . DIRECTORY_SEPARATOR . $this->definitionsPath . "dist.whois.json";
        $overridePath = ROOTDIR . DIRECTORY_SEPARATOR . $this->definitionsPath . "whois.json";
        $this->definitions = array_merge($this->parseFile($path), $this->parseFile($overridePath));
    }

    protected function parseFile($path)
    {
        $return = [];
        if (file_exists($path)) {
            $definitions = file_get_contents($path);
            if ($definitions = @json_decode($definitions, true)) {
                foreach ($definitions as $definition) {
                    $extensions = explode(",", $definition["extensions"]);
                    unset($definition["extensions"]);
                    foreach ($extensions as $extension) {
                        $return[$extension] = $definition;
                    }
                }
            } else {
                logActivity("Unable to load WHOIS Server Definition File: " . $path);
            }
        }
        return $return;
    }

    public function init()
    {
    }

    public function getSocketPrefix()
    {
        return $this->socketPrefix;
    }

    public function canLookup($tld)
    {
        return array_key_exists($tld, $this->definitions);
    }

    public function getFromDefinitions($tld, $key)
    {
        return isset($this->definitions[$tld][$key]) ? $this->definitions[$tld][$key] : "";
    }

    protected function getUri($tld)
    {
        if ($this->canLookup($tld)) {
            $uri = $this->getFromDefinitions($tld, "uri");
            if (empty($uri)) {
                throw new Exception("Uri not defined for whois service");
            }
            return $uri;
        }
        throw new Exception("Whois server not known for " . $tld);
    }

    protected function isSocketLookup($tld)
    {
        if ($this->canLookup($tld)) {
            $uri = $this->getUri($tld);
            return substr($uri, 0, strlen($this->getSocketPrefix())) == $this->getSocketPrefix();
        }
        throw new Exception("Whois server not known for " . $tld);
    }

    protected function getAvailableMatchString($tld)
    {
        if ($this->canLookup($tld)) {
            return $this->getFromDefinitions($tld, "available");
        }
        throw new Exception("Whois server not known for " . $tld);
    }

    protected function httpWhoisLookup($domain, $uri)
    {
        $url = $uri . $domain;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception("Error: " . curl_errno($ch) . " - " . curl_error($ch));
        }
        curl_close($ch);
        return $data;
    }

    protected function socketWhoisLookup($domain, $server, $port, $tld)
    {
        $fp = @fsockopen($server, $port, $errorNumber, $errorMessage, 10);
        if ($fp === false) {
            throw new Exception("Error: " . $errorNumber . " - " . $errorMessage);
        }
        @fputs($fp, $domain . "\r\n");
        @socket_set_timeout($fp, 10);
        $data = "";
        while (!@feof($fp)) {
            $data .= @fread($fp, 4096);
        }
        @fclose($fp);
        return $data;
    }

    public function lookup($parts)
    {
        $sld = $parts["sld"];
        $tld = $parts["tld"];
        if (!substr($tld, 0, 1) === ".") {
            $tld = "." . $tld;
        }
        $encodedDomain = Domains\Idna::toPunycode($sld . $tld);
        $encodedDomainParts = explode(".", $encodedDomain, 2);
        if ($encodedDomainParts[0] !== $sld) {
            if (Config\Setting::getValue("AllowIDNDomains")) {
                $sld = $encodedDomainParts[0];
            } else {
                return false;
            }
        }
        try {
            $uri = $this->getUri($tld);
            $availableMatchString = $this->getAvailableMatchString($tld);
            $isSocketLookup = $this->isSocketLookup($tld);
        } catch (Exception $e) {
            return false;
        }
        $this->domain = $domain = $sld . $tld;
        try {
            if ($isSocketLookup) {
                $uri = substr($uri, strlen($this->getSocketPrefix()));
                $port = 43;
                if (strpos($uri, ":")) {
                    $port = explode(":", $uri, 2);
                    list($uri, $port) = $port;
                }
                $lookupResult = $this->socketWhoisLookup($domain, $uri, $port, $tld);
            } else {
                $lookupResult = $this->httpWhoisLookup($domain, $uri);
            }
        } catch (\Exception $e) {
            $results = [];
            $results["result"] = "error";
            if (isset($_SESSION["adminid"])) {
                $results["errordetail"] = $e->getMessage();
            }
            return $results;
        }
        $lookupResult = " ---" . $lookupResult;
        $results = [];
        if (strpos($lookupResult, $availableMatchString) !== false) {
            $results["result"] = "available";
        } else {
            $results["result"] = "unavailable";
            if ($isSocketLookup) {
                $results["whois"] = nl2br(htmlentities($lookupResult, ENT_SUBSTITUTE));
            } else {
                $results["whois"] = nl2br(htmlentities(strip_tags($lookupResult), ENT_SUBSTITUTE));
            }
        }
        return $results;
    }

    public function logLookup()
    {
        if ($this->domain) {
            Database\Capsule::table("tblwhoislog")->insert(["date" => Carbon::now()->toDateTimeString(), "domain" => $this->domain, "ip" => Utility\Environment\CurrentRequest::getIP()]);
        }
    }
}
