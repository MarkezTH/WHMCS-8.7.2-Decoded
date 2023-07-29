<?php

namespace WHMCS\Http\Message;

class JsonAttachmentResponse extends AbstractAttachmentResponse
{
    public function __construct($data, $attachmentFilename, $status = 200, $headers = [], $encodingOptions = \Laminas\Diactoros\Response\JsonResponse::DEFAULT_JSON_FLAGS)
    {
        if (is_array($data)) {
            json_encode(NULL);
            $data = json_encode($data, $encodingOptions);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new \InvalidArgumentException(sprintf("Unable to encode data to JSON in %s: %s", "WHMCS\\Http\\Message\\JsonAttachmentResponse", json_last_error_msg()));
            }
        }
        parent::__construct($data, $attachmentFilename, $status, $headers);
    }

    protected function createDataStream()
    {
        $body = new \Laminas\Diactoros\Stream("php://temp", "wb+");
        $body->write($this->getData());
        $body->rewind();
        return $body;
    }

    protected function getDataContentType()
    {
        return "application/json";
    }

    protected function getDataContentLength()
    {
        return strlen($this->getData());
    }
}
