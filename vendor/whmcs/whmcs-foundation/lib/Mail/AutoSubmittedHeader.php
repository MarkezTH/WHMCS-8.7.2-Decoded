<?php

namespace WHMCS\Mail;

class AutoSubmittedHeader
{
    protected $autoSubmittedValue = NULL;
    protected $knownHeaderValues = ["no", "auto-replied", "auto-generated"];

    public function __construct(\ZBateson\MailMimeParser\Message $message)
    {
        if ($message->getHeader("Auto-Submitted")) {
            $this->autoSubmittedValue = strtolower($message->getHeader("Auto-Submitted")->getRawValue());
        }
    }

    public function isGenerated()
    {
        return $this->rawData() === "auto-generated";
    }

    public function isReplied()
    {
        return $this->rawData() === "auto-replied";
    }

    public function isValueKnown()
    {
        return in_array($this->rawData(), $this->knownHeaderValues);
    }

    public function isHuman()
    {
        return !$this->exists() || $this->rawData() === "no";
    }

    public function exists()
    {
        return $this->rawData() !== NULL;
    }

    public function isAutomated()
    {
        return $this->isGenerated() || $this->isReplied();
    }

    public function rawData()
    {
        return $this->autoSubmittedValue;
    }
}
