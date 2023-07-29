<?php

namespace WHMCS\Api\ApplicationSupport\Http;

class ServerRequest extends \WHMCS\Http\Message\ServerRequest
{
    public function __clone()
    {
        \DI::make("runtimeStorage")->apiRequest = $this;
    }

    public static function fromGlobals($server = NULL, $query = NULL, $body = NULL, $cookies = NULL, $files = NULL)
    {
        $whmcsPsr7Request = \Laminas\Diactoros\ServerRequestFactory::fromGlobals($server, $query, $body, $cookies, $files);
        return (new static($whmcsPsr7Request->getServerParams(), $whmcsPsr7Request->getUploadedFiles(), $whmcsPsr7Request->getUri(), $whmcsPsr7Request->getMethod(), $whmcsPsr7Request->getBody(), $whmcsPsr7Request->getHeaders(), $whmcsPsr7Request->getCookieParams(), $whmcsPsr7Request->getQueryParams(), $whmcsPsr7Request->getParsedBody(), $whmcsPsr7Request->getProtocolVersion()))->seedAttributes();
    }

    protected function seedAttributes()
    {
        $attributeMap = ["action" => ["attributeName" => "action", "default" => ""], "responsetype" => ["attributeName" => "response_format", "default" => NULL], "identifier" => ["attributeName" => "identifier", "default" => ""], "secret" => ["attributeName" => "secret", "default" => ""], "username" => ["attributeName" => "username", "default" => ""], "password" => ["attributeName" => "password", "default" => ""], "accesskey" => ["attributeName" => "accesskey", "default" => ""]];
        $request = $this;
        foreach ($attributeMap as $userInputKey => $attribute) {
            $request = $request->withAttribute($attribute["attributeName"], $request->get($userInputKey, $attribute["default"]));
        }
        return $request;
    }

    public function getAction()
    {
        return $this->getAttribute("action", "");
    }

    public function getResponseFormat()
    {
        return $this->getAttribute("response_format", "") ?? "";
    }

    public function isDeviceAuthentication()
    {
        return (bool) $this->getAttribute("identifier", false);
    }

    public function getIdentifier()
    {
        return $this->getAttribute("identifier", false);
    }

    public function getSecret()
    {
        return $this->getAttribute("secret", false);
    }

    public function getUsername()
    {
        return $this->getAttribute("username", false);
    }

    public function getPassword()
    {
        return $this->getAttribute("password", false);
    }

    public function getAccessKey()
    {
        return \WHMCS\Input\Sanitize::decode($this->getAttribute("accesskey", ""));
    }
}
