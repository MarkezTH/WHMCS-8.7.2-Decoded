<?php

class Plesk_Api
{
    private $_templatesDir = NULL;
    protected $_login = NULL;
    protected $_password = NULL;
    protected $_hostname = NULL;
    protected $_port = NULL;
    protected $_isSecure = NULL;
    const STATUS_OK = "ok";
    const STATUS_ERROR = "error";
    const ERROR_AUTHENTICATION_FAILED = 1001;
    const ERROR_AGENT_INITIALIZATION_FAILED = 1003;
    const ERROR_OBJECT_NOT_FOUND = 1013;
    const ERROR_PARSING_XML = 1014;
    const ERROR_OPERATION_FAILED = 1023;

    public function __construct($login, $password, $hostname, $port, $isSecure)
    {
        $this->_login = $login;
        $this->_password = $password;
        $this->_hostname = $hostname;
        $this->_port = $port;
        $this->_isSecure = $isSecure;
        $this->_templatesDir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "templates/api";
    }

    public function __call($name, $args)
    {
        $params = $args[0] ?? [];
        return $this->request($name, $params);
    }

    public function isAdmin()
    {
        return "admin" === $this->_login;
    }

    protected function request($command, $params)
    {
        $translator = Plesk_Registry::getInstance()->translator;
        $url = ($this->_isSecure ? "https" : "http") . "://" . $this->_hostname . ":" . $this->_port . "/enterprise/control/agent.php";
        $headers = ["HTTP_AUTH_LOGIN: " . $this->_login, "HTTP_AUTH_PASSWD: " . $this->_password, "Content-Type: text/xml"];
        $template = $this->_templatesDir . DIRECTORY_SEPARATOR . Plesk_Registry::getInstance()->version . DIRECTORY_SEPARATOR . $command . ".tpl";
        if (!file_exists($template)) {
            throw new Exception($translator->translate("ERROR_NO_TEMPLATE_TO_API_VERSION", ["COMMAND" => $command, "API_VERSION" => Plesk_Registry::getInstance()->version]));
        }
        $escapedParams = [];
        foreach ($params as $name => $value) {
            $escapedParams[$name] = $this->_escapeValueRecursive($value);
        }
        extract($escapedParams);
        ob_start();
        include $template;
        $data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><packet version=\"" . Plesk_Registry::getInstance()->version . "\">" . ob_get_clean() . "</packet>";
        foreach (array_keys($escapedParams) as $name) {
            unset($name);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($curl);
        $errorCode = curl_errno($curl);
        $errorMessage = curl_error($curl);
        curl_close($curl);
        if ($errorCode) {
            throw new Exception("Curl error: [" . $errorCode . "] " . $errorMessage . ".");
        }
        $result = simplexml_load_string($response);
        logModuleCall("plesk", Plesk_Registry::getInstance()->actionName, $data, $response, (array) $result);
        if ($result === false) {
            throw new Exception("Server response could not be processed.", 1014);
        }
        if (isset($result->system) && "error" === (string) $result->system->status) {
            throw new Exception((string) $result->system->errtext, (int) $result->system->errcode);
        }
        $statusResult = $result->xpath("//result");
        if (1 === count($statusResult)) {
            $statusResult = reset($statusResult);
            if ("error" === (string) $statusResult->status) {
                switch ((int) $statusResult->errcode) {
                    case 1001:
                        $errorMessage = $translator->translate("ERROR_AUTHENTICATION_FAILED");
                        break;
                    case 1003:
                        $errorMessage = $translator->translate("ERROR_AGENT_INITIALIZATION_FAILED");
                        break;
                    default:
                        $errorMessage = (string) $statusResult->errtext;
                        throw new Exception($errorMessage, (int) $statusResult->errcode);
                }
            }
        }
        return $result;
    }

    private function _escapeValue($value)
    {
        $value = (string) $value;
        return htmlspecialchars($value, ENT_COMPAT | ENT_HTML401);
    }

    private function _escapeValueRecursive($value)
    {
        if (is_array($value)) {
            array_walk_recursive($value, function (&$item) {
                $item = $this->_escapeValue($item);
            });
            return $value;
        }
        return $this->_escapeValue($value);
    }
}
