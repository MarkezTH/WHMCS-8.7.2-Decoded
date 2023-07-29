<?php

namespace Transip\Api\Library\Repository\OpenStack;

class ProjectRepository extends \Transip\Api\Library\Repository\ApiRepository
{
    const RESOURCE_NAME = "openstack/projects";
    const RESOURCE_PARAMETER_SINGUlAR = "project";
    const RESOURCE_PARAMETER_PLURAL = "projects";

    protected function getRepositoryResourceNames()
    {
        return [self::RESOURCE_NAME];
    }

    public function getAll()
    {
        $projects = [];
        $response = $this->httpClient->get($this->getResourceUrl());
        $projectsArray = $this->getParameterFromResponse($response, self::RESOURCE_PARAMETER_PLURAL);
        foreach ($projectsArray as $projectArray) {
            $projects[] = new \Transip\Api\Library\Entity\OpenStackProject($projectArray);
        }
        return $projects;
    }

    public function create($name, $description)
    {
        $parameters = ["name" => $name, "description" => $description];
        $this->httpClient->post($this->getResourceUrl(), $parameters);
    }

    public function getByProjectId($OpenStackProject, $projectId)
    {
        $response = $this->httpClient->get($this->getResourceUrl($projectId));
        $projectArray = $this->getParameterFromResponse($response, self::RESOURCE_PARAMETER_SINGUlAR);
        return new \Transip\Api\Library\Entity\OpenStackProject($projectArray);
    }

    public function updateProject($project)
    {
        $parameters = ["project" => $project];
        $this->httpClient->put($this->getResourceUrl($project->getId()), $parameters);
    }

    public function handover($projectID, $targetCustomerName)
    {
        $parameters = ["action" => "handover", "targetCustomerName" => $targetCustomerName];
        $this->httpClient->patch($this->getResourceUrl($projectID), $parameters);
    }

    public function cancel($projectID)
    {
        $this->httpClient->delete($this->getResourceUrl($projectID));
    }
}
