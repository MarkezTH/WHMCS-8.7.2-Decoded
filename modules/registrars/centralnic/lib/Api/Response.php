<?php

namespace WHMCS\Module\Registrar\CentralNic\Api;

class Response
{
    protected $parser = NULL;
    protected $input = NULL;
    protected $code = 0;
    protected $description = "";
    protected $data = [];

    public function __construct(ParserInterface $parser, $input)
    {
        $this->parser = $parser;
        $this->input = $input;
        $this->transform();
    }

    protected function transform()
    {
        if (empty($this->input)) {
            $this->data = [];
            $this->code = 0;
            $this->description = "";
        } else {
            $response = $this->changeKeyCaseRecursively($this->getParser()->parseResponse($this->input), CASE_LOWER);
            $this->data = $this->getParser()->getResponseData($response);
            $this->code = $this->getParser()->getResponseCode($response);
            $this->description = $this->getParser()->getResponseDescription($response);
        }
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function getDataValue($key)
    {
        return $this->getParser()->getResponseDataValue($key, $this->getData());
    }

    protected function changeKeyCaseRecursively($array, int $case)
    {
        return array_map(function ($value) use($case) {
            if (is_array($value)) {
                $value = $this->changeKeyCaseRecursively($value, $case);
            }
            return $value;
        }, array_change_key_case($array, $case));
    }
}
