<?php

namespace WHMCS\Http\Message;

abstract class AbstractAttachmentResponse extends \Laminas\Diactoros\Response
{
    use \Laminas\Diactoros\Response\InjectContentTypeTrait;
    protected $data = NULL;
    protected $attachmentFilename = NULL;

    public function __construct($data, $attachmentFilename, $status = 200, $headers = [])
    {
        $this->setData($data);
        $this->setAttachmentFilename($attachmentFilename);
        $headers = array_replace($headers, ["content-length" => $this->getDataContentLength(), "content-disposition" => $this->getDataContentDisposition()]);
        parent::__construct($this->createDataStream(), $status, $this->injectContentType($this->getDataContentType(), $headers));
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    public function getAttachmentFilename()
    {
        return $this->attachmentFilename;
    }

    public function setAttachmentFilename($attachmentFilename)
    {
        $this->attachmentFilename = $attachmentFilename;
        return $this;
    }
    protected abstract function createDataStream();
    protected abstract function getDataContentType();
    protected abstract function getDataContentLength();

    protected function getDataContentDisposition()
    {
        return sprintf("attachment; filename=\"%s\"", $this->getAttachmentFilename());
    }
}
