<?php

class HeartInternetReg_API
{
    public $namespace = "urn:ietf:params:xml:ns:epp-1.0";
    private $hostname = "api.heartinternet.co.uk";

    public function connect($test_mode = false)
    {
        $uri = "tls://" . $this->hostname;
        $port = $test_mode ? 1701 : 700;
        $errorCode = NULL;
        $errorMsg = NULL;
        $this->res = fsockopen($uri, $port, $errorCode, $errorMsg);
        if (!is_resource($this->res)) {
            throw new Exception(sprintf("Failed to connect to %s:%s [(%d) %s]", $uri, $port, $errorCode, $errorMsg));
        }
        return $this->getResponse();
    }

    public function getResponse()
    {
        if (feof($this->res)) {
            throw new Exception("Connection closed");
        }
        $size_packed = fread($this->res, 4);
        if ($size_packed === false || strlen($size_packed) == 0) {
            return "";
        }
        $size = unpack("N", $size_packed);
        $out = "";
        $last = "";
        $s = $size[1] - 4;
        while (0 < $s) {
            $last = fread($this->res, $s);
            $out .= $last;
            $s -= strlen($last);
        }
        return $out;
    }

    public function sendMessage($output, $no_parsing = false)
    {
        if (fwrite($this->res, pack("N", strlen($output) + 4) . $output) === false) {
            throw new Exception("Connection closed");
        }
        $content = $this->getResponse();
        if ($content) {
            if ($no_parsing) {
                return $content;
            }
            $result = [];
            $parser = xml_parser_create();
            xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
            xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
            xml_parse_into_struct($parser, $content, $result);
            return $result;
        }
        throw new Exception("No response");
    }

    public function getLogInRequest($userid, $password, $objects, $extensions)
    {
        if (!preg_match("/^[a-f0-9]+\$/", $userid)) {
            throw new Exception("Invalid username, should look like '9cf2cdbcce5e00c0'");
        }
        if (!$objects || empty($objects)) {
            throw new Exception("You must provide some object namespaces, please see the login examples in the documentation");
        }
        $doc = new DOMDocument();
        $content = $doc->createElement("login");
        $clID_element = $doc->createElement("clID");
        $clID_element->appendChild($doc->createTextNode($userid));
        $content->appendChild($clID_element);
        $pw_element = $doc->createElement("pw");
        $pw_element->appendChild($doc->createTextNode($password));
        $content->appendChild($pw_element);
        $options_element = $doc->createElement("options");
        $version_element = $doc->createElement("version");
        $version_element->appendChild($doc->createTextNode("1.0"));
        $options_element->appendChild($version_element);
        $lang_element = $doc->createElement("lang");
        $lang_element->appendChild($doc->createTextNode("en"));
        $options_element->appendChild($lang_element);
        $content->appendChild($options_element);
        $svcs_element = $doc->createElement("svcs");
        foreach ($objects as $object) {
            $element = $doc->createElement("objURI");
            $element->appendChild($doc->createTextNode((string) $object));
            $svcs_element->appendChild($element);
        }
        $svcs_extensions = $doc->createElement("svcExtension");
        foreach ($extensions as $extension) {
            $element = $doc->createElement("extURI");
            $element->appendChild($doc->createTextNode((string) $extension));
            $svcs_extensions->appendChild($element);
        }
        $svcs_element->appendChild($svcs_extensions);
        $content->appendChild($svcs_element);
        return $this->buildXML($content);
    }

    public function logIn($xmlLoginRequest)
    {
        $result = $this->sendMessage($xmlLoginRequest);
        foreach ($result as $tag) {
            if ($tag["tag"] == "result" && $tag["type"] != "close" && $tag["attributes"]["code"] != 1000) {
                throw new Exception("Failed to log in!: " . $tag["attributes"]["code"]);
            }
            if ($tag["tag"] == "session-id") {
                return $tag["value"];
            }
        }
        return $result;
    }

    public function buildXML($content)
    {
        $doc = $content->ownerDocument;
        $epp = $doc->createElement("epp");
        $epp->setAttribute("xmlns", $this->namespace);
        $doc->appendChild($epp);
        $c = $doc->createElement("command");
        $epp->appendChild($c);
        $c->appendChild($content);
        $output = $doc->saveXML();
        return $output;
    }

    public function disconnect()
    {
        fclose($this->res);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
