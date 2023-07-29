<?php

namespace WHMCS\Http\Message;

class CsvAttachmentResponse extends AbstractAttachmentResponse
{
    public function __construct($data, $attachmentFilename, $status = 200, $headers = [])
    {
        $body = [];
        if (is_array($data)) {
            $body = $this->prepareData($data);
        }
        parent::__construct(implode(PHP_EOL, $body), $attachmentFilename, $status, $headers);
    }

    protected function prepareData($data)
    {
        $body = [];
        foreach ($data as $row) {
            $cellData = [];
            foreach ($row as $cell) {
                $cell = \WHMCS\Input\Sanitize::decode($cell);
                $cell = strip_tags($cell);
                $cellData[] = sprintf("\"%s\"", str_replace("\"", "\"\"", $cell));
            }
            $body[] = implode(",", $cellData);
        }
        return $body;
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
        return "text/csv";
    }

    protected function getDataContentLength()
    {
        return strlen($this->getData());
    }
}
