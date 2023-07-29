<?php

namespace WHMCS\Http\Message;

class FileAttachmentResponse extends AbstractAttachmentResponse
{
    public function __construct($file, $attachmentFilename = NULL, $status = 200, $headers = [])
    {
        $file = new \SplFileInfo($file);
        if (!$attachmentFilename) {
            $attachmentFilename = $file->getFilename();
        }
        parent::__construct($file, $attachmentFilename, $status, $headers);
    }

    protected function createDataStream()
    {
        return new \Laminas\Diactoros\Stream($this->getData()->getRealPath(), "r");
    }

    protected function getDataContentType()
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($this->getData()->getRealPath());
    }

    protected function getDataContentLength()
    {
        return $this->getData()->getSize();
    }
}
