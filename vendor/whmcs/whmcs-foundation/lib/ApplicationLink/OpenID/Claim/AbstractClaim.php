<?php

namespace WHMCS\ApplicationLink\OpenID\Claim;

abstract class AbstractClaim
{
    protected $user = NULL;
    protected $claimName = NULL;

    public function __construct(\WHMCS\User\UserInterface $user, $claimName = NULL)
    {
        $this->setUser($user);
        if ($claimName) {
            $this->setClaimName($claimName);
        }
        $this->hydrate();
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(\WHMCS\User\UserInterface $user)
    {
        $this->user = $user;
        return $this;
    }

    public function getClaimName()
    {
        return $this->claimName;
    }

    public function setClaimName($claimName)
    {
        $this->claimName = $claimName;
        return $this;
    }

    public function toArray()
    {
        $data = [];
        $properties = get_object_vars($this);
        foreach ($properties as $propName => $propValue) {
            if (!($propName == "user" || $propName == "claimName")) {
                $data[$propName] = $propValue;
            }
        }
        return $data;
    }
    protected abstract function hydrate();
}
