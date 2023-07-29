<?php

namespace WHMCS\Route\Middleware;

class RoutableApiRequestUri extends WhitelistFilter
{
    const ATTRIBUTE_API_V1_REQUEST = "isApiV1RouteRequest";
    const ATTRIBUTE_API_NG_VERSION = "isApiNGRouteRequest";
    const API_V1_VERSION = "v1";

    public function __construct($strictFilter = true, $filterList = [])
    {
        parent::__construct($strictFilter, array_unique(["/api", "/includes/api.php"]));
    }

    protected function getApiVersion($request)
    {
        $version = $this->getApiVersionFromPath($request->getUri()->getPath());
        if ($version) {
            return $version;
        }
        if ($this->isAllowed($request)) {
            return self::API_V1_VERSION;
        }
        return NULL;
    }

    public function getApiVersionFromPath($path)
    {
        $parts = explode("/", trim($path, "/"));
        if (empty($parts) || count($parts) < 2) {
            return NULL;
        }
        $namespace = array_shift($parts);
        if ($namespace != "api") {
            return NULL;
        }
        $version = array_shift($parts);
        if ($this->isValidApiVersion($version)) {
            return $version;
        }
        return NULL;
    }

    protected function whitelistApiV1Request(\Psr\Http\Message\ServerRequestInterface $request)
    {
        include_once ROOTDIR . "/includes/adminfunctions.php";
        if (!defined("APICALL")) {
            define("APICALL", true);
        }
        if (!$request instanceof \WHMCS\Api\ApplicationSupport\Http\ServerRequest) {
            $apiRequest = \WHMCS\Api\ApplicationSupport\Http\ServerRequest::fromGlobals($request->getServerParams(), $request->getQueryParams(), $request->getParsedBody(), $request->getCookieParams(), $request->getUploadedFiles());
            $apiRequest = $apiRequest->withUri($request->getUri());
            foreach ($request->getAttributes() as $attribute => $value) {
                $apiRequest = $apiRequest->withAttribute($attribute, $value);
            }
        } else {
            $apiRequest = $request;
        }
        if (!$apiRequest->getAttribute(static::ATTRIBUTE_API_V1_REQUEST)) {
            $apiRequest = $apiRequest->withAttribute(static::ATTRIBUTE_API_V1_REQUEST, true);
        }
        return $apiRequest;
    }

    protected function whitelistApiNgRequest(\Psr\Http\Message\ServerRequestInterface $request, $apiVersion)
    {
        if (!$request->getAttribute(static::ATTRIBUTE_API_NG_VERSION)) {
            $request = $request->withAttribute(static::ATTRIBUTE_API_NG_VERSION, $apiVersion);
        }
        return $request;
    }

    protected function whitelistRequest(\Psr\Http\Message\ServerRequestInterface $request)
    {
        $apiVersion = $this->getApiVersion($request);
        if ($apiVersion) {
            if ($apiVersion === self::API_V1_VERSION) {
                return $this->whitelistApiV1Request($request);
            }
            if ($this->isValidApiVersion($apiVersion, 2)) {
                return $this->whitelistApiNgRequest($request, $apiVersion);
            }
        }
        return $this->blacklistRequest($request);
    }

    protected function blacklistRequest(\Psr\Http\Message\ServerRequestInterface $request)
    {
        return $request->withAttribute(static::ATTRIBUTE_API_V1_REQUEST, false);
    }

    public function isValidApiVersion($version = 1, int $minVersionRequired)
    {
        if (!is_null($version) && preg_match("/^v(\\d+)\$/", $version, $matches)) {
            return $minVersionRequired <= $matches[1];
        }
        return false;
    }
}
