<?php

class WHMCS_Nominet
{
    private $params = NULL;
    private $socket = NULL;
    private $response = "";
    private $responsearray = "";
    private $errmsg = "";
    private $resultcode = 0;
    const PAYLOAD_ERROR = -1;
    const PAYLOAD_RAW = 1;
    const PAYLOAD_XML = 2;

    public function __construct()
    {
    }

    public static function init($params)
    {
        $obj = new self();
        $obj->params = $params;
        return $obj;
    }

    public function getLastError()
    {
        return $this->errmsg ? $this->errmsg : "An unknown error occurred";
    }

    public function setError($errmsg)
    {
        $this->errmsg = $errmsg;
    }

    public function getParam($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : "";
    }

    public function getDomain()
    {
        return $this->getParam("sld") . "." . $this->getParam("tld");
    }

    public function connect()
    {
        if ($this->getParam("TestMode")) {
            $host = "testbed-epp.nominet.org.uk";
        } else {
            $host = "epp.nominet.org.uk";
        }
        $port = 700;
        $timeout = 10;
        $target = sprintf("tls://%s:%s", $host, $port);
        $context = stream_context_create(["ssl" => ["crypto_method" => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT]]);
        $errstr = "";
        $errno = 0;
        $this->socket = stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($this->socket)) {
            $this->setError("Connecting to " . $target . ". <p>The error message was '" . $errstr . "' (code " . $errno . ")");
            fclose($this->socket);
            return false;
        }
        $response = "";
        $payloadType = $this->capturePayload($response);
        if ($payloadType == 1 || $payloadType == -1) {
            $this->setError($response);
            fclose($this->socket);
            return false;
        }
        $this->processResponse($response);
        $this->logCall("connect", $target);
        return true;
    }

    protected function capturePayload($payload)
    {
        $peekLength = 100;
        $prefixLength = 4;
        $readBytes = 0;
        $read = function (int $length) use($readBytes) {
            $error = NULL;
            if (!is_resource($this->socket)) {
                return [false, $error];
            }
            if (feof($this->socket)) {
                return [NULL, $error];
            }
            $read = @fread($this->socket, $length);
            if ($read === false) {
                $error = error_get_last()["message"];
                return [false, $error];
            }
            $readBytes += strlen($read);
            return [$read, NULL];
        };
        $handleError = function ($read, $error) {
            if (!is_null($error)) {
                $this->setError("Reading from server: " . $error);
                return true;
            }
            if ($read === false) {
                $this->setError("Connection closed by remote server");
                return true;
            }
            return false;
        };
        list($peek, $error) = $read($peekLength);
        if ($handleError($peek, $error)) {
            return -1;
        }
        if (strpos($peek, "<?xml") === false) {
            do {
                list($peekMore, $error) = $read($peekLength);
                if (!$handleError($peekMore, $error)) {
                    $peek .= $peekMore;
                }
            } while (is_null($peekMore));
            $payload = $peek;
            return 1;
        }
        $responseLength = unpack("N", substr($peek, 0, $prefixLength));
        if ($responseLength === false || !is_array($responseLength)) {
            $this->setError("Got a bad frame header length from server");
            return -1;
        }
        $responseLength = array_pop($responseLength);
        if ($responseLength < 5) {
            $this->setError("Got a bad frame header length from server");
            return -1;
        }
        $responseLength = min($responseLength, 10485760);
        $xml = substr($peek, $prefixLength);
        list($remainingXml, $error) = $read($responseLength - $peekLength);
        if ($handleError($remainingXml, $error)) {
            return -1;
        }
        $payload = $xml . $remainingXml;
        return 2;
    }

    private function processResponse($response)
    {
        $this->response = $response;
        $this->responsearray = XMLtoArray($response);
        if (preg_match("%<domain:ns>(.+)</domain:ns>%s", $response, $matches)) {
            $ns = trim($matches[1]);
            $ns = preg_replace("%</?domain:hostObj>%", " ", $ns);
            $ns = preg_split("/\\s+|\n/", $ns, NULL, PREG_SPLIT_NO_EMPTY);
            foreach ($ns as $k => $value) {
                $ns[$k] = chop($value, ".");
            }
            if (0 < count($ns)) {
                $this->responsearray["EPP"]["RESPONSE"]["RESDATA"]["DOMAIN:INFDATA"]["DOMAIN:NS"]["DOMAIN:HOSTOBJ"] = $ns;
            }
        }
        return true;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getResponseArray()
    {
        return $this->responsearray;
    }

    public function getResultCode()
    {
        $response_code_pattern = "<result code=\"(\\d+)\">";
        $matches = [];
        preg_match($response_code_pattern, $this->response, $matches);
        $resultcode = isset($matches[1]) ? (int) $matches[1] : 0;
        return $resultcode;
    }

    public function isErrorCode()
    {
        $resultcode = $this->getResultCode();
        return $resultcode < 2000 ? false : true;
    }

    public function getErrorDesc()
    {
        $results = $this->getResponseArray();
        $results = $results["EPP"]["RESPONSE"];
        if (isset($results["RESULT"]["EXTVALUE"]["REASON"])) {
            return $results["RESULT"]["EXTVALUE"]["REASON"];
        }
        if (isset($results["RESULT"]["MSG"])) {
            return $results["RESULT"]["MSG"];
        }
    }

    public function call($xml)
    {
        $command = XMLtoArray($xml);
        $command = array_keys($command["COMMAND"]);
        $command = $command[0];
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">" . $xml;
        fwrite($this->socket, pack("N", strlen($xml) + 4) . $xml);
        $response = "";
        if ($this->capturePayload($response) != 2) {
            $this->setError($response);
            return false;
        }
        $this->processResponse($response);
        $this->logCall($command, $xml);
        return true;
    }

    private function logCall($action, $request)
    {
        if (function_exists("logModuleCall")) {
            logModuleCall("nominet", $action, $request, $this->getResponse(), $this->getResponseArray(), [$this->getParam("Username"), $this->getParam("Password")]);
        }
        return true;
    }

    public function login()
    {
        $xml = "  <command>\n                <login>\n                  <clID>" . $this->getParam("Username") . "</clID>\n                  <pw>" . $this->escapeParam($this->getParam("Password")) . "</pw>\n                  <options>\n                    <version>1.0</version>\n                    <lang>en</lang>\n                  </options>\n                  <svcs>\n\t\t    <objURI>urn:ietf:params:xml:ns:domain-1.0</objURI>\n\t\t    <objURI>urn:ietf:params:xml:ns:contact-1.0</objURI>\n\t\t    <objURI>urn:ietf:params:xml:ns:host-1.0</objURI>\n\t\t    ";
        $xml .= "<svcExtension>\n\t\t      <extURI>http://www.nominet.org.uk/epp/xml/contact-nom-ext-1.0</extURI>\n\t\t      <extURI>http://www.nominet.org.uk/epp/xml/domain-nom-ext-1.0</extURI>\n\t\t      <extURI>http://www.nominet.org.uk/epp/xml/std-release-1.0</extURI>\n\t\t    </svcExtension>\n                  </svcs>\n                </login>\n                <clTRID>ABC-12345</clTRID>\n              </command>\n            </epp>";
        $res = $this->call($xml);
        if ($res) {
            if ($this->isErrorCode()) {
                $this->setError("Login Failed. Please check the Nominet details in Configuration (<i class=\"fa fa-wrench\" aria-hidden=\"true\"></i>) > System Settings > Domain Registrars");
            } else {
                return true;
            }
        }
        return false;
    }

    public function connectAndLogin()
    {
        if ($this->connect() && $this->login()) {
            return true;
        }
        return false;
    }

    public function escapeParam($param)
    {
        return htmlspecialchars($param);
    }
}
