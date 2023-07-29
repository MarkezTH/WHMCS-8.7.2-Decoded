<?php

namespace Transip\Api\Library\Repository\OpenStack\Project;

class UserRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "users";
    const RESOURCE_PARAMETER_PLURAL = "users";

    protected function getRepositoryResourceNames()
    {
        return [\Transip\Api\Library\Repository\OpenStack\ProjectRepository::RESOURCE_NAME, self::RESOURCE_NAME];
    }

    public function getByProjectId($projectId)
    {
        $users = [];
        $response = $this->httpClient->get($this->getResourceUrl($projectId));
        $usersArray = $this->getParameterFromResponse($response, self::RESOURCE_PARAMETER_PLURAL);
        foreach ($usersArray as $userArray) {
            $users[] = new \Transip\Api\Library\Entity\OpenStackUser($userArray);
        }
        return $users;
    }

    public function grantUserAccessToProject($projectId, $userId)
    {
        $url = $this->getResourceUrl($projectId);
        $parameters = ["userId" => $userId];
        $this->httpClient->post($url, $parameters);
    }

    public function revokeUserAccessFromProject($projectId, $userId)
    {
        $this->httpClient->delete($this->getResourceUrl($projectId, $userId));
    }
}
