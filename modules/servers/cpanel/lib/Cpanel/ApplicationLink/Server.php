<?php

namespace Cpanel\ApplicationLink;

class Server extends \WHMCS\ApplicationLink\Server\Server
{
    public function handleTokenRequest(\OAuth2\RequestInterface $request, \OAuth2\ResponseInterface $response = NULL)
    {
        list($request, $response) = $this->preProcessHandleTokenRequest($request, $response);
        if (!$response || !$response->isOk()) {
            return $response;
        }
        parent::handleTokenRequest($request, $response);
        $response = $this->postProcessHandleTokenRequest($response, $request);
        return $response;
    }

    protected function preProcessHandleTokenRequest(\OAuth2\HttpFoundationBridge\Request $request, \OAuth2\HttpFoundationBridge\Response $response = NULL)
    {
        $storage = $this->getStorage("client_credentials");
        $this->addGrantType(new \WHMCS\ApplicationLink\GrantType\SingleSignOn($storage), "client_credentials");
        $scope = $request->query->get("scope", "");
        if (!$scope) {
            $scope = $request->request->get("scope", "");
        }
        $clientId = $request->request->get("subscriber_unique_id", "");
        $secret = $request->request->get("token", "");
        $request->request->add(["grant_type" => "single_sign_on", "client_id" => $clientId, "client_secret" => $secret, "scope" => $scope]);
        return [$request, $response];
    }

    protected function postProcessHandleTokenRequest(\OAuth2\ResponseInterface $response, \OAuth2\HttpFoundationBridge\Request $request)
    {
        $data = json_decode($response->getContent(), true);
        if (!$data["access_token"]) {
            return $response;
        }
        $site = \App::getSystemURL();
        $endpoint = "singlesignon";
        $token = \WHMCS\ApplicationLink\AccessToken::where("access_token", "=", $data["access_token"])->first();
        if (!$this->getScopeUtil()->checkScope("clientarea:sso", $token->scope)) {
            $endpoint = "resource";
        }
        $context = $request->query->get("context", "");
        if (!$context) {
            $context = $request->request->get("context", "");
        }
        if ($context && \WHMCS\ApplicationLink\Server\SingleSignOn\RedirectResponse::isValidRedirectContext($context)) {
            $context = "&context=" . $context;
        } else {
            $context = "";
        }
        $data["redirect_url"] = sprintf("%soauth/%s.php?module_type=server&module=cpanel%s&access_token=%s", $site, $endpoint, $context, $data["access_token"]);
        $response->setData($data);
        $response->setStatusCode(\OAuth2\HttpFoundationBridge\Response::HTTP_OK);
        return $response;
    }

    public function postAccessTokenResponseAction(\OAuth2\RequestInterface $request, \OAuth2\ResponseInterface $response)
    {
        $data = json_decode($response->getContent(), true);
        if (!empty($data["attempt"])) {
            $attempt = (int) $data["attempt"];
            if ($attempt < 4) {
            }
        }
    }
}
