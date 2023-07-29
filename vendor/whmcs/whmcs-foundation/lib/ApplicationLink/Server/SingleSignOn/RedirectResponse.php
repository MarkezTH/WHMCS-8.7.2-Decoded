<?php

namespace WHMCS\ApplicationLink\Server\SingleSignOn;

class RedirectResponse extends \Symfony\Component\HttpFoundation\RedirectResponse
{
    protected $pathScopeMap = ["clientarea:homepage" => "/clientarea.php", "clientarea:profile" => "/clientarea.php?action=details", "clientarea:billing_info" => "/clientarea.php?action=creditcard", "clientarea:emails" => "/clientarea.php?action=emails", "clientarea:announcements" => "/index.php?rp=/announcements", "clientarea:downloads" => "/index.php?rp=/download", "clientarea:knowledgebase" => "/knowledgebase.php", "clientarea:network_status" => "/serverstatus.php", "clientarea:services" => "/clientarea.php?action=services", "clientarea:product_details" => "/clientarea.php?action=productdetails&id=:serviceId", "clientarea:domains" => "/clientarea.php?action=domains", "clientarea:domain_details" => "/clientarea.php?action=domaindetails&id=:domainId", "clientarea:invoices" => "/clientarea.php?action=invoices", "clientarea:tickets" => "/supporttickets.php", "clientarea:submit_ticket" => "/submitticket.php", "clientarea:shopping_cart" => "/cart.php", "clientarea:shopping_cart_addons" => "/cart.php?gid=addons", "clientarea:upgrade" => "/upgrade.php?type=package&id=:serviceId", "clientarea:shopping_cart_domain_register" => "/cart.php?a=add&domain=register", "clientarea:shopping_cart_domain_transfer" => "/cart.php?a=add&domain=transfer", "sso:custom_redirect" => "/:ssoRedirectPath"];
    protected $scopesWithDynamicPaths = ["sso:custom_redirect" => ["ssoRedirectPath"], "clientarea:product_details" => ["serviceId"], "clientarea:domain_details" => ["domainId"], "clientarea:upgrade" => ["serviceId"]];
    protected $targetSessionVariables = ["wp-toolkit-deluxe" => ["serviceId"]];
    protected static $contextRoutePath = ["wp-toolkit-deluxe" => "routeWptkd"];
    protected $redirectContext = NULL;
    const DEFAULT_URL = "/clientarea.php";
    const DEFAULT_SCOPE = "clientarea:homepage";

    public function __construct($url = "", $status = 302, $headers = [])
    {
        if (empty($url)) {
            $url = static::DEFAULT_URL;
        }
        parent::__construct($url, $status, $headers);
    }

    public function setTargetUrlFromToken(\WHMCS\ApplicationLink\AccessToken $token)
    {
        $path = "";
        if ($this->getRedirectContext()) {
            $path = $this->getContextUrl($token);
        }
        if (!$path) {
            $this->setRedirectContext(NULL);
            $path = $this->getScopePath($token);
        }
        if (parse_url($path, PHP_URL_SCHEME)) {
            $url = $path;
        } else {
            $pathParts = explode("?", $path, 2);
            $systemUrl = \App::getSystemURL(false);
            if (!empty($pathParts[1])) {
                $url = \App::getRedirectUrl($pathParts[0], $pathParts[1], $systemUrl);
            } else {
                $url = \App::getRedirectUrl($path, "", $systemUrl);
            }
        }
        parent::setTargetUrl($url);
        return $this;
    }

    public function getScopesWithDynamicPaths()
    {
        return $this->scopesWithDynamicPaths;
    }

    public function getScopePath(\WHMCS\ApplicationLink\AccessToken $token, $data = [])
    {
        $preMadeRedirect = $token->redirectUri;
        if ($preMadeRedirect) {
            return html_entity_decode($preMadeRedirect);
        }
        $scopeForRedirect = $this->getScope($token);
        if (!$data && isset($this->scopesWithDynamicPaths[$scopeForRedirect])) {
            $neededVariables = $this->scopesWithDynamicPaths[$scopeForRedirect];
            foreach ($neededVariables as $holder) {
                if (in_array($holder, ["serviceId", "domainId"])) {
                    $data[$holder] = $token->client->serviceId;
                }
            }
        }
        return $this->fillPlaceHolders($this->getPathFromScope($scopeForRedirect), $data);
    }

    public static function isValidRedirectContext($context = "")
    {
        return array_key_exists($context, self::$contextRoutePath);
    }

    public function getRedirectContext()
    {
        return $this->redirectContext;
    }

    public function setRedirectContext($redirectContext)
    {
        $this->redirectContext = $redirectContext;
        return $this;
    }

    public function getContextUrl(\WHMCS\ApplicationLink\AccessToken $token)
    {
        $path = "";
        $context = $this->getRedirectContext();
        if (isset(self::$contextRoutePath[$context])) {
            $method = self::$contextRoutePath[$context];
            if ($method && method_exists($this, $method)) {
                return call_user_func_array([$this, $method], [$token]);
            }
        }
        return $path;
    }

    public function routeWptkd(\WHMCS\ApplicationLink\AccessToken $token)
    {
        return fqdnRoutePath("store-addon", "wp-toolkit-deluxe");
    }

    public function getTargetSessionVariables(\WHMCS\ApplicationLink\AccessToken $token)
    {
        $targets = [];
        if ($this->getRedirectContext()) {
            $targets[] = $this->getRedirectContext();
        }
        $targets[] = $this->getScope($token);
        $data = [];
        foreach ($targets as $target) {
            if (isset($this->targetSessionVariables[$target])) {
                $neededVariables = $this->targetSessionVariables[$target];
                foreach ($neededVariables as $holder) {
                    if (in_array($holder, ["serviceId", "domainId"])) {
                        $data[$holder] = $token->client->serviceId;
                    }
                }
            }
        }
        return $data;
    }

    public function getScope(\WHMCS\ApplicationLink\AccessToken $token)
    {
        $scopeForRedirect = "";
        foreach ($token->scopes()->get() as $scope) {
            if ($scope->scope != "clientarea:sso") {
                $scopeForRedirect = $scope->scope;
                if (empty($scopeForRedirect)) {
                    $scopeForRedirect = static::DEFAULT_SCOPE;
                }
                return $scopeForRedirect;
            }
        }
    }

    protected function getPathFromScope($scope)
    {
        $path = $this->pathScopeMap[static::DEFAULT_SCOPE];
        if (!empty($this->pathScopeMap[$scope])) {
            $path = $this->pathScopeMap[$scope];
        }
        return $path;
    }

    protected function fillPlaceHolders($path, $data = [])
    {
        $placeholders = $this->scopesWithDynamicPaths;
        foreach ($placeholders as $scope => $variables) {
            foreach ($variables as $variable) {
                $value = isset($data[$variable]) ? $data[$variable] : "";
                $value = sprintf("%s", $value);
                $path = str_replace(":" . $variable, $value, $path);
            }
        }
        return $path;
    }
}
