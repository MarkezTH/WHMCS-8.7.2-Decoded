<?php

namespace Transip\Api\Library\Repository\OpenStack;

class UserRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "openstack/users";
    const RESOURCE_PARAMETER_SINGUlAR = "user";
    const RESOURCE_PARAMETER_PLURAL = "users";

    protected function getRepositoryResourceNames()
    {
        return [self::RESOURCE_NAME];
    }

    public function getAll()
    {
        $users = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $usersArray = $this->getParameterFromResponse($response, self::RESOURCE_PARAMETER_PLURAL);
        foreach ($usersArray as $userArray) {
            $users[] = new \Transip\Api\Library\Entity\OpenStackUser($userArray);
        }
        return $users;
    }

    public function getByUserId($OpenStackUser, $userId)
    {
        $response = $this->httpClient->get($this->getResourceUrl($userId));
        $userArray = $this->getParameterFromResponse($response, self::RESOURCE_PARAMETER_SINGUlAR);
        return new \Transip\Api\Library\Entity\OpenStackUser($userArray);
    }

    public function create($username, $description, $email, $password, $projectId)
    {
        $parameters = ["username" => $username, "description" => $description, "email" => $email, "password" => $password, "projectId" => $projectId];
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function delete($userId)
    {
        $this->httpClient->delete($this->getResourceUrl($userId));
    }

    public function updatePassword($userId, $password)
    {
        $parameters = ["newPassword" => $password];
        $this->httpClient->patch($this->getResourceUrl($userId), $parameters);
    }

    public function updateUser($openStackUser)
    {
        $parameters = ["user" => $openStackUser];
        $this->httpClient->put($this->getResourceUrl($openStackUser->getId()), $parameters);
    }
}
