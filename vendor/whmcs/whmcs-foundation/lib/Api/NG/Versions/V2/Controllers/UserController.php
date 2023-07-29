<?php

namespace WHMCS\Api\NG\Versions\V2\Controllers;

class UserController extends \WHMCS\Api\NG\Versions\V2\AbstractApiController implements \WHMCS\Api\NG\Versions\V2\PagedResponseInterface
{
    use \WHMCS\Api\NG\Versions\V2\PagedResponseTrait;
    const TWOFA_USER_VAR_NAME = "2fa_user_id";

    protected function formatClientListForUser($user)
    {
        $clients = [];
        $selectedClientId = \Auth::client()->id ?? NULL;
        foreach ($user->clients as $client) {
            $clients[] = ["id" => $client->uuid, "company" => $client->companyName, "selected" => $client->id === $selectedClientId];
        }
        return $clients;
    }

    protected function respondWithLoginSuccessForUser(\WHMCS\User\User $user)
    {
        return $this->createResponse(["email" => $user->email, "firstname" => $user->first_name, "lastname" => $user->last_name, "clients" => $this->formatClientListForUser($user)]);
    }

    public function createUserSession(\WHMCS\Http\Message\ServerRequest $request)
    {
        try {
            \Auth::authenticate($request->getFromJson("email"), $request->getFromJson("password"));
            $user = \Auth::user();
            if (!$user) {
                throw new \WHMCS\Exception\Authorization\AccessDenied();
            }
            $request->getState()->offsetUnset(self::TWOFA_USER_VAR_NAME);
            return $this->respondWithLoginSuccessForUser($user);
        } catch (\WHMCS\Exception\Authentication\RequiresSecondFactor $e) {
            \Auth::logout();
            $user = $e->getUser();
            if (!$user) {
                throw new \WHMCS\Exception\Authorization\AccessDenied();
            }
            return $this->respondWithTwoFaChallengeForUser($request, $user);
        } catch (\Throwable $e) {
            throw new \WHMCS\Exception\Authorization\AccessDenied();
        }
    }

    protected function respondWithTwoFaChallengeForUser(\WHMCS\Http\Message\ServerRequest $request, \WHMCS\User\User $user)
    {
        $twofaFields = (new \WHMCS\TwoFactorAuthentication())->setUser($user)->getFields();
        $request->getState()->offsetSet(self::TWOFA_USER_VAR_NAME, $user->id);
        return $this->createResponse(["condition" => "twofa", "fields" => $twofaFields], \Symfony\Component\HttpFoundation\Response::HTTP_PRECONDITION_FAILED);
    }

    public function verifyTwoFa(\WHMCS\Http\Message\ServerRequest $request)
    {
        $userId = $request->getState()->offsetGet(self::TWOFA_USER_VAR_NAME);
        if (!is_numeric($userId)) {
            throw new \WHMCS\Exception\Authorization\AccessDenied();
        }
        \WHMCS\Session::set(\WHMCS\Authentication\AuthManager::SESSION_TWOFACTOR_CLIENTID_NAME, $userId);
        $user = \Auth::twoFactorChallengeUser();
        if (!$user || !$user->second_factor) {
            throw new \WHMCS\Exception\Authorization\AccessDenied();
        }
        try {
            $twofaFields = (new \WHMCS\TwoFactorAuthentication())->setUser($user)->getFields();
            foreach ($twofaFields as $field) {
                $_POST[$field["name"]] = $request->getFromJson("fields." . $field["name"]);
            }
            \Auth::verifySecondFactor();
            $request->getState()->offsetUnset(self::TWOFA_USER_VAR_NAME);
            \Auth::setUser($user);
            return $this->respondWithLoginSuccessForUser($user);
        } catch (\WHMCS\Exception\Authentication\InvalidSecondFactor $e) {
            return $this->createResponse(["message" => "Could not verify second factor input"], \Symfony\Component\HttpFoundation\Response::HTTP_PRECONDITION_FAILED);
        }
    }

    public function deleteUserSession(\WHMCS\Http\Message\ServerRequest $request)
    {
        \Auth::logout();
        return $this->createResponse();
    }

    protected function requireUser($User, $request)
    {
        $user = \Auth::user();
        if (!$user) {
            throw new \WHMCS\Exception\Authorization\AccessDenied();
        }
        return $user;
    }

    public function getClients(\WHMCS\Http\Message\ServerRequest $request)
    {
        $clients = $this->formatClientListForUser($this->requireUser($request));
        return $this->createResponse($this->paginateData($clients, $request));
    }

    public function selectClient(\WHMCS\Http\Message\ServerRequest $request)
    {
        $this->requireUser($request);
        $client = \WHMCS\User\Client::findUuid($request->get("client_id"));
        if (!$client) {
            throw new \WHMCS\Exception\Authentication\InvalidClientRequested();
        }
        \Auth::setClientId($client->id);
        return $this->createResponse();
    }
}
