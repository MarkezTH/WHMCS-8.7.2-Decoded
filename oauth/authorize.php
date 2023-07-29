<?php

use WHMCS\Application\Support\Facades\Auth;
use WHMCS\Application\Support\Facades\Lang;

$requestSuperGlobal = $_REQUEST;
$post = $_POST;
$get = $_GET;
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "init.php";
$_REQUEST = $requestSuperGlobal;
$_POST = $post;
$_GET = $get;
$tmpRequest = OAuth2\HttpFoundationBridge\Request::createFromGlobals();
$isLoginDeclinedRequest = $tmpRequest->request->get("login_declined");
$isLogoutRequest = $tmpRequest->request->get("logout");
$isLoginRedirectPostLogout = $tmpRequest->query->get("request_hash");
$userHasAuthorized = $tmpRequest->request->get("userAuthorization") == "yes";
$userAuthzRequestPostLogoutLogin = NULL;
$isReturnToAppRequest = $tmpRequest->request->get("return_to_app");
$passedRequestHash = $tmpRequest->request->get("request_hash", $tmpRequest->query->get("request_hash"));
if ($passedRequestHash) {
    $origRequest = WHMCS\ApplicationLink\Server\Server::retrieveRequest($passedRequestHash, false);
    $tmpRequest->query->add($origRequest->query->all());
    $tmpRequest->request->add($origRequest->request->all());
    $tmpRequest->headers->add($origRequest->headers->all());
    $tmpRequest->setMethod($origRequest->getMethod());
}
$request = $tmpRequest;
if ($isLogoutRequest) {
    Auth::logout();
    $response = new Symfony\Component\HttpFoundation\RedirectResponse(sprintf("%s/oauth/authorize.php?request_hash=%s", WHMCS\ApplicationLink\Server\Server::getIssuer(), $passedRequestHash));
    $response->send();
    exit;
}
$requestHash = NULL;
if ($isLoginRedirectPostLogout) {
    $requestHash = $passedRequestHash;
} else {
    if ($isReturnToAppRequest) {
        $requestHash = $passedRequestHash;
        $isLoginDeclinedRequest = true;
    }
}
$response = new OAuth2\HttpFoundationBridge\Response();
$response->prepare($request);
$server = DI::make("oauth2_server");
$issuer = WHMCS\ApplicationLink\Server\Server::getIssuer();
$server->setConfig("issuer", $issuer);
$previouslyAuthorized = false;
$clientAccount = NULL;
if (!$server->validateAuthorizeRequest($request, $response)) {
    $response->send();
    exit;
}
gracefulCoreRequiredFileInclude("/includes/clientareafunctions.php");
$userClient = function () {
    if (is_null(Auth::user())) {
        return NULL;
    }
    $client = Auth::client();
    if (is_null($client)) {
        $client = Auth::user()->ownedClients()->first();
    }
    return $client;
};
$ca = new WHMCS\ClientArea();
$ca->setPageTitle("OAuth Authorization");
$ca->initPage();
$client = WHMCS\ApplicationLink\Client::whereIdentifier($request->query->get("client_id", $request->request->get("client_id", $request->query->get("client_id"))))->first();
$ca->assign("appName", $client->name);
$ca->assign("appLogo", $client->logoUri);
$ca->assign("issuerurl", $issuer . "/");
if (!$isLoginDeclinedRequest) {
    if (!$requestHash) {
        $requestHash = $server::storeRequest($request);
    }
    $ca->assign("request_hash", $requestHash);
    $clientAccount = $userClient();
    if (!Auth::user()) {
        WHMCS\Authentication\LoginHandler::saveGotoRequest(WHMCS\Input\Sanitize::decode($_SERVER["REQUEST_URI"]));
        WHMCS\Authentication\LoginHandler::disableRedirectToTwoFactor(true);
        WHMCS\Authentication\LoginHandler::setIsOauthLoginRequest(true);
        $ca->assign("requestedAction", "Login");
        $ca->assign("incorrect", (bool) $whmcs->get_req_var("incorrect"));
        if (Auth::twoFactorChallengeUser()) {
            $twofa = new WHMCS\TwoFactorAuthentication();
            $user = Auth::twoFactorChallengeUser();
            if ($user) {
                $twofa->setUser($user);
                if (!$twofa->isActiveClients() || !$twofa->isEnabled()) {
                    WHMCS\Session::destroy();
                    redir();
                }
                $challenge = $twofa->moduleCall("challenge");
                if ($challenge) {
                    $ca->assign("challenge", $challenge);
                } else {
                    $ca->assign("error", Lang::trans("oauth.badTwoFactorAuthModule"));
                }
            } else {
                $ca->assign("error", Lang::trans("errorButTryAgain"));
            }
            WHMCS\Authentication\LoginHandler::setReturnUri(WHMCS\Input\Sanitize::decode($_SERVER["REQUEST_URI"]));
            $ca->assign("content", $ca->getSingleTplOutput("oauth/login-twofactorauth"));
        } else {
            $ca->assign("content", $ca->getSingleTplOutput("oauth/login"));
        }
        $ca->setTemplate("oauth/layout");
        $ca->disableHeaderFooterOutput();
        $ca->output();
    } else {
        if (is_null($clientAccount)) {
            $ca->assign("userInfo", ["firstName" => Auth::user()->first_name, "lastName" => Auth::user()->last_name, "clientName" => ""]);
            $ca->assign("error", "Your account is not associated with any clients. A client association is required.");
            $ca->assign("content", $ca->getSingleTplOutput("oauth/error"));
            $ca->setTemplate("oauth/layout");
            $ca->disableHeaderFooterOutput();
            $ca->output();
        } else {
            $previouslyAuthorized = false;
            $clientName = "";
            if ($clientAccount) {
                $previouslyAuthorized = $server->hasUserAuthorizedRequestedScopes($clientAccount);
                $clientName = $clientAccount->companyName ?: $clientAccount->fullName;
            }
            $ca->assign("userInfo", ["firstName" => Auth::user()->first_name, "lastName" => Auth::user()->last_name, "clientName" => $clientName]);
            if (!$previouslyAuthorized) {
                if (!$request->request->get("userAuthorization")) {
                    $ca->assign("requestedPermissions", [Lang::trans("oauth.permAccessNameAndEmail")]);
                    $ca->assign("requestedAuthorizations", ["openid", "profile", "email"]);
                    $ca->assign("requestedAction", "Authorize App");
                    $ca->assign("content", $ca->getSingleTplOutput("oauth/authorize"));
                    $ca->setTemplate("oauth/layout");
                    $ca->disableHeaderFooterOutput();
                    $ca->output();
                } else {
                    if ($userHasAuthorized) {
                        $officialScopes = ["openid", "profile", "email"];
                        $authorizedScopes = [];
                        foreach ($request->request->get("authz") as $authz) {
                            if (in_array($authz, $officialScopes)) {
                                $authorizedScopes[] = $authz;
                            }
                        }
                        $server->updateUserAuthorizedScopes($clientAccount, $authorizedScopes);
                        $previouslyAuthorized = $server->hasUserAuthorizedRequestedScopes($clientAccount);
                    } else {
                        $previouslyAuthorized = false;
                        $server->updateUserAuthorizedScopes($clientAccount, []);
                    }
                }
            }
        }
    }
}
$userUuid = 0;
if ($clientAccount) {
    $userUuid = $clientAccount->uuid;
}
if ($clientAccount instanceof WHMCS\User\Client && !$clientAccount->isAllowedToAuthenticate()) {
    $msg = "OAuth authorization request denied due to unexpected active login session for \"Closed\" User ID: %s";
    logActivity(sprintf($msg, $clientAccount->id));
    $response->setError(OAuth2\HttpFoundationBridge\Response::HTTP_UNAUTHORIZED, "Invalid authentication", "Cannot process authorization request for associated \"Closed\" user account.");
} else {
    $server->handleAuthorizeRequest($request, $response, $previouslyAuthorized, $userUuid);
}
Log::debug("oauth/authorize", ["request" => ["headers" => $request->server->getHeaders(), "request" => $request->request->all(), "query" => $request->query->all()], "response" => ["body" => $response->getContent()]]);
$response->send();
