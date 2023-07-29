<?php

namespace Transip\Api\Library\Entity\Vps;

class VncData extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $host = NULL;
    protected $path = NULL;
    protected $url = NULL;
    protected $token = NULL;
    protected $password = NULL;

    public function getHost()
    {
        return $this->host;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getPassword()
    {
        return $this->password;
    }
}
