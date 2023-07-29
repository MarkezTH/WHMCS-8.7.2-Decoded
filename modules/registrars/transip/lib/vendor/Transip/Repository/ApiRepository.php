<?php

namespace Transip\Api\Library\Repository;

abstract class ApiRepository
{
    protected $httpClient = NULL;

    public function __construct(\Transip\Api\Library\HttpClient\HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    protected function getResourceUrl(...$args)
    {
        $urlSuffix = "";
        $resourceNames = $this->getRepositoryResourceNames();
        while (($resourceName = array_shift($resourceNames)) !== NULL) {
            $id = array_shift($args);
            $urlSuffix .= "/" . $resourceName;
            if ($id !== NULL) {
                $urlSuffix .= "/" . $id;
            }
        }
        return $urlSuffix;
    }

    protected function getRepositoryResourceNames()
    {
        return [static::RESOURCE_NAME];
    }

    protected function getParameterFromResponse($response, $parameterName)
    {
        if (!isset($response[$parameterName])) {
            throw \Transip\Api\Library\Exception\ApiClientException::parameterMissingInResponse($response, $parameterName);
        }
        return $response[$parameterName];
    }
}
