<?php

namespace WHMCS\Mail\Incoming\Protocol;

class Pop3 extends \Laminas\Mail\Protocol\Pop3 implements Oauth2Interface
{
    public function connectWithSslIfEnforced($host, $port, $sslType)
    {
        $isTls = false;
        $scheme = "tcp";
        $streamContextOptions = [];
        switch ($sslType) {
            case "ssl":
                $scheme = "ssl";
                if (\DI::make("config")->skip_mail_ssl_validation) {
                    $streamContextOptions = array_merge($streamContextOptions, ["verify_peer" => false, "verify_peer_name" => false]);
                }
                break;
            case "tls":
                $isTls = true;
                break;
            default:
                \Laminas\Stdlib\ErrorHandler::start();
                $remoteHost = $scheme . "://" . $host . ":" . $port;
                $this->socket = stream_socket_client($remoteHost, $errno, $errstr, self::TIMEOUT_CONNECTION, STREAM_CLIENT_CONNECT, stream_context_create($streamContextOptions));
                $error = \Laminas\Stdlib\ErrorHandler::stop();
                if (!$this->socket) {
                    throw new \Laminas\Mail\Exception\RuntimeException(sprintf("cannot connect to host %s", $error ? sprintf("; error = %s (errno = %d )", $error->getMessage(), $error->getCode()) : ""), 0, $error);
                }
                $welcome = $this->readResponse();
                strtok($welcome, "<");
                $this->timestamp = strtok(">");
                if (!strpos($this->timestamp, "@")) {
                    $this->timestamp = NULL;
                } else {
                    $this->timestamp = "<" . $this->timestamp . ">";
                }
                if ($isTls) {
                    $this->request("STLS");
                    $result = stream_socket_enable_crypto($this->socket, true, $this->getCryptoMethod());
                    if (!$result) {
                        throw new \Laminas\Mail\Exception\RuntimeException("cannot enable TLS");
                    }
                }
        }
    }

    public function readResponse($multiline = false)
    {
        \Laminas\Stdlib\ErrorHandler::start();
        $result = fgets($this->socket);
        $error = \Laminas\Stdlib\ErrorHandler::stop();
        if (!is_string($result)) {
            throw new \Laminas\Mail\Exception\RuntimeException("read failed - connection closed?", 0, $error);
        }
        $result = trim($result);
        if (strpos($result, " ")) {
            list($status, $message) = explode(" ", $result, 2);
        } else {
            $status = $result;
            $message = "";
        }
        if ($status != "+OK") {
            $errorMessage = "last request failed";
            if ($status == "-ERR") {
                $message = \WHMCS\Input\Sanitize::encode($message);
                $errorMessage .= ": " . $message;
            } else {
                if ($status === "+" && $message !== "") {
                    $payload = json_decode(base64_decode($message), true);
                    if (is_array($payload)) {
                        $errorMessage .= ": " . json_encode(\WHMCS\Input\Sanitize::encode($payload));
                    }
                }
            }
            throw new \Laminas\Mail\Exception\RuntimeException($errorMessage);
        }
        if ($multiline) {
            $message = "";
            $line = fgets($this->socket);
            while ($line && rtrim($line, "\r\n") != ".") {
                if ($line[0] == ".") {
                    $line = substr($line, 1);
                }
                $message .= $line;
                $line = fgets($this->socket);
            }
        }
        return $message;
    }

    public function login($user, $password, $tryApop = true)
    {
        if ($tryApop && $this->timestamp) {
            try {
                $this->request("APOP " . $user . " " . md5($this->timestamp . $password));
                return NULL;
            } catch (\Laminas\Mail\Exception\RuntimeException $e) {
            }
        }
        $this->request("USER " . $user);
        $this->request("PASS " . $password);
    }

    public function oauth2Login($userName, $accessToken)
    {
        $authString = base64_encode("user=" . $userName . "\1auth=Bearer " . $accessToken . "\1\1");
        $this->request("AUTH XOAUTH2 " . $authString);
    }
}
