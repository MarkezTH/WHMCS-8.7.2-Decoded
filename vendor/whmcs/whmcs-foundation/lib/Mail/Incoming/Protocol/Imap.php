<?php

namespace WHMCS\Mail\Incoming\Protocol;

class Imap extends \Laminas\Mail\Protocol\Imap implements Oauth2Interface
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
                if (!$this->assumedNextLine("* OK")) {
                    throw new \Laminas\Mail\Exception\RuntimeException("host doesn't allow connection");
                }
                if ($isTls) {
                    $result = $this->requestAndResponse("STARTTLS");
                    $result = $result && stream_socket_enable_crypto($this->socket, true, $this->getCryptoMethod());
                    if (!$result) {
                        throw new \Laminas\Mail\Exception\RuntimeException("cannot enable TLS");
                    }
                }
        }
    }

    public function oauth2Login($userName, $accessToken)
    {
        $authString = base64_encode("user=" . $userName . "\1auth=Bearer " . $accessToken . "\1\1");
        $tag = NULL;
        $this->sendRequest("AUTHENTICATE XOAUTH2 " . $authString, [], $tag);
        $responseTag = NULL;
        $data = $this->nextTaggedLine($responseTag);
        if ($responseTag === "+") {
            $errorMessage = "Oauth2 auth failed";
            fwrite($this->socket, "\r\n");
            $tokens = "";
            $this->readLine($tokens, $tag, true);
            $payload = json_decode(base64_decode($data), true);
            $errorMessage .= ", " . \WHMCS\Input\Sanitize::encode(trim($tokens));
            if (is_array($payload)) {
                $errorMessage .= ": " . json_encode(\WHMCS\Input\Sanitize::encode($payload));
            }
            throw new \Laminas\Mail\Exception\RuntimeException($errorMessage);
        }
        if ($responseTag === "*") {
            $this->readResponse($tag);
        }
    }
}
