<?php

namespace WHMCS\Http\Message;

abstract class AbstractViewableResponse
{
    protected $getBodyFromPrivateStream = false;

    public function __construct($data = "", $status = 200, $headers = [])
    {
        parent::__construct($data, $status, $headers);
    }

    public function getBody($StreamInterface)
    {
        if ($this->getBodyFromPrivateStream) {
            return parent::getBody();
        }
        $body = new \Laminas\Diactoros\Stream("php://temp", "wb+");
        $body->write($this->getOutputContent());
        $body->rewind();
        return $body;
    }
    protected abstract function getOutputContent();
}
