<?php

namespace WHMCS\Module\Registrar\CentralNic\Api;

abstract class AbstractApi implements ApiInterface
{
    protected $url = NULL;
    protected $username = NULL;
    protected $password = NULL;
    protected $parser = NULL;
    protected $proxy = NULL;
    protected $transport = NULL;

    public function __construct($url, $username, $password, ParserInterface $parser, TransportInterface $transport)
    {
        $this->url = trim($url);
        $this->username = trim($username);
        $this->password = trim($password);
        $this->parser = $parser;
        $this->transport = $transport;
    }

    public function getParser($ParserInterface)
    {
        return $this->parser;
    }

    public function getUrl()
    {
        return $this->url ?? "";
    }

    public function getUsername()
    {
        return $this->username ?? "";
    }

    public function getPassword()
    {
        return $this->password ?? "";
    }

    public function getTransport($TransportInterface)
    {
        return $this->transport;
    }

    public function call($command)
    {
        try {
            return new Response($this->parser, $this->doCall($command));
        } catch (\Exception $e) {
            throw new \Exception("Remote Provider Error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function setProxy($self, $proxy)
    {
        $this->proxy = $proxy;
        return $this;
    }

    public function getProxy()
    {
        return $this->proxy ?? "";
    }

    public function doCall($command)
    {
        return $this->transport->doCall($command, $this);
    }
    public abstract function getCustomHeader();
}
