<?php

namespace WHMCS\Module\Registrar\CentralNic\Commands;

abstract class AbstractCommand
{
    use \WHMCS\Module\Registrar\CentralNic\ParametersTrait;
    protected $api = NULL;
    protected $httpMethod = "POST";
    protected $params = [];
    protected $command = NULL;

    public function __construct(\WHMCS\Module\Registrar\CentralNic\Api\ApiInterface $api)
    {
        $this->api = $api;
    }

    public function getCommand()
    {
        if (empty($this->command)) {
            throw new \Exception("Command can not be empty");
        }
        return $this->command;
    }

    public function getHttpMethod()
    {
        return $this->httpMethod;
    }

    public function setParam($key, $value)
    {
        $this->params[trim($key)] = $value;
        return $this;
    }

    public function addParams($params)
    {
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function deleteParam($key)
    {
        unset($this->params[$key]);
        return $this;
    }

    public function execute()
    {
        $this->params = array_merge(["command" => $this->getCommand()], $this->params);
        return $this->handleResponse($this->api->call($this));
    }

    public function handleResponse($response)
    {
        $response->getCode();
        switch ($response->getCode()) {
            case "200":
                return $response;
                break;
            default:
                throw new \Exception($response->getDescription(), $response->getCode());
        }
    }
}
