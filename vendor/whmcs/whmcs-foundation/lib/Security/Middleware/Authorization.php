<?php

namespace WHMCS\Security\Middleware;

class Authorization implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\DelegatingMiddlewareTrait;
    protected $request = NULL;
    protected $csrfRequestMethods = [];
    protected $csrfNamespace = "";
    protected $csrfCheckRequired = true;
    protected $requireClientServiceAttribute = NULL;
    protected $requireAnyPermission = [];
    protected $requireAllPermission = [];

    public function process(\Psr\Http\Message\ServerRequestInterface $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        $this->setRequest($request);
        return $this->delegateProcess($request, $delegate);
    }

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        $user = $request->getAttribute("authenticatedUser");
        return $this->assertAuthorization($request, $user);
    }

    public function assertAuthorization(\WHMCS\Http\Message\ServerRequest $request, $user = NULL)
    {
        if (!$this->hasValidCsrfToken()) {
            return $this->responseInvalidCsrfToken();
        }
        $anyPermission = array_filter($this->getRequireAnyPermission());
        $allPermission = array_filter($this->getRequireAllPermission());
        if (empty($anyPermission) && empty($allPermission)) {
            return $request;
        }
        $this->assertUserInterface($user);
        try {
            $this->assertPermissions($user);
        } catch (\WHMCS\Exception\Security\MissingAllPermission $e) {
            return $this->responseMissingMultiplePermissions($allPermission);
        } catch (\WHMCS\Exception\Security\MissingPermission $e) {
            return $this->responseMissingPermission($anyPermission);
        }
        $this->assertServiceClient($request);
        return $request;
    }

    protected function responseInvalidCsrfToken()
    {
        throw new \WHMCS\Exception\Authorization\InvalidCsrfToken("Invalid CSRF Protection Token");
    }

    protected function responseMissingMultiplePermissions($permissionNames = [])
    {
        throw new \WHMCS\Exception\Authorization\AccessDenied("Invalid Permissions. Requires \"" . implode("\", \"", $permissionNames) . "\".");
    }

    protected function responseMissingPermission($permissionNames = [])
    {
        throw new \WHMCS\Exception\Authorization\AccessDenied("Invalid Permissions. Requires at least one of the following: \"" . implode("\", \"", $permissionNames) . "\".");
    }

    public function requireCsrfToken($csrfRequestMethods = NULL, $csrfNamespace = NULL)
    {
        $this->setCsrfCheckRequired(true);
        if (is_null($csrfRequestMethods)) {
            $csrfRequestMethods = $this->getDefaultCsrfRequestMethods();
        }
        $this->setCsrfRequestMethods($csrfRequestMethods);
        if (is_null($csrfNamespace)) {
            $csrfNamespace = $this->getDefaultCsrfNamespace();
        }
        $this->setCsrfNamespace($csrfNamespace);
        return $this;
    }

    public function hasValidCsrfToken()
    {
        if (!$this->isCsrfCheckRequired()) {
            return true;
        }
        $requestMethod = $this->getRequest()->getMethod();
        if (!in_array($requestMethod, $this->getCsrfRequestMethods())) {
            return true;
        }
        $token = $this->getRequest()->get("token");
        try {
            check_token($this->getCsrfNamespace(), $token);
        } catch (\WHMCS\Exception\ProgramExit $e) {
            return false;
        }
        return true;
    }

    public function assertServiceClient($request)
    {
        if (!$this->isClientServiceRequired()) {
            return NULL;
        }
        $invalidService = function () {
            throw new \WHMCS\Exception("Invalid service");
        };
        $invalidClient = function () {
            throw new \WHMCS\Exception("Invalid client");
        };
        $serviceId = $request->getAttribute($this->requireClientServiceAttribute);
        if (empty($serviceId)) {
            $invalidService();
        }
        $authnClient = \DI::make("auth")->client();
        if (is_null($authnClient)) {
            $invalidClient();
        }
        $clientServiceQuery = $authnClient->services()->where("id", $serviceId);
        if (!$clientServiceQuery->exists()) {
            throw new \WHMCS\Exception\Authorization\AccessDenied();
        }
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function setRequest(\WHMCS\Http\Message\ServerRequest $request)
    {
        $this->request = $request;
        return $this;
    }

    public function getDefaultCsrfNamespace()
    {
        return "WHMCS.default";
    }

    public function getDefaultCsrfRequestMethods()
    {
        return ["POST"];
    }

    public function getCsrfRequestMethods()
    {
        return $this->csrfRequestMethods;
    }

    public function setCsrfRequestMethods($csrfRequestMethods)
    {
        $this->csrfRequestMethods = $csrfRequestMethods;
        return $this;
    }

    public function getCsrfNamespace()
    {
        return $this->csrfNamespace;
    }

    public function setCsrfNamespace($csrfNamespace)
    {
        $this->csrfNamespace = $csrfNamespace;
        return $this;
    }

    public function isCsrfCheckRequired()
    {
        return $this->csrfCheckRequired;
    }

    public function setCsrfCheckRequired($checkRequired)
    {
        $this->csrfCheckRequired = (bool) $checkRequired;
        return $this;
    }

    public function requireClientService($self, $serviceIdAttributeKey)
    {
        $this->requireClientServiceAttribute = $serviceIdAttributeKey;
        return $this;
    }

    public function isClientServiceRequired()
    {
        return !is_null($this->requireClientServiceAttribute);
    }

    public function getRequireAnyPermission()
    {
        return $this->requireAnyPermission;
    }

    public function setRequireAnyPermission($permissions = [])
    {
        $this->requireAnyPermission = $permissions;
        return $this;
    }

    public function getRequireAllPermission()
    {
        return $this->requireAllPermission;
    }

    public function setRequireAllPermission($permissions = [])
    {
        $this->requireAllPermission = $permissions;
        return $this;
    }

    protected function assertUserInterface($user = NULL)
    {
        if (!$user instanceof \WHMCS\User\Contracts\UserInterface) {
            throw new \WHMCS\Exception\Authorization\AccessDenied("Authentication Required");
        }
    }

    protected function assertPermissions($user)
    {
        $anyPermission = array_filter($this->getRequireAnyPermission());
        $allPermission = array_filter($this->getRequireAllPermission());
        try {
            foreach ($allPermission as $permissionName) {
                if (!$user->hasPermission($permissionName)) {
                    throw new \WHMCS\Exception\Security\MissingAllPermission();
                }
            }
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Security\MissingAllPermission();
        }
        if (empty($anyPermission)) {
            return NULL;
        }
        $isAllowed = false;
        try {
            foreach ($anyPermission as $permissionName) {
                if ($user->hasPermission($permissionName)) {
                    $isAllowed = true;
                    break;
                }
            }
        } catch (\Exception $e) {
        }
        if (!$isAllowed) {
            throw new \WHMCS\Exception\Security\MissingPermission();
        }
    }
}
