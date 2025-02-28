<?php

namespace WHMCS\ApplicationLink\OpenID\Claim;

class ClaimFactory extends AbstractClaim
{
    protected $claimMap = ["profile" => "\\WHMCS\\ApplicationLink\\OpenID\\Claim\\Profile", "email" => "\\WHMCS\\ApplicationLink\\OpenID\\Claim\\Email"];
    protected $userClaims = [];
    protected $requestedClaims = [];

    public function __construct(\WHMCS\User\UserInterface $user, $claims)
    {
        $this->requestedClaims = $claims;
        parent::__construct($user);
    }

    protected function hydrate()
    {
        foreach ($this->requestedClaims as $claim) {
            $this->userClaims[$claim] = $this->getClaim($claim);
        }
        return $this;
    }

    public function getClaim($claim)
    {
        if (!isset($this->claimMap[$claim])) {
            return NULL;
        }
        if (isset($this->userClaims[$claim])) {
            return $this->userClaims[$claim];
        }
        $class = $this->claimMap[$claim];
        return new $class($this->getUser());
    }

    public function toArray()
    {
        $data = [];
        foreach ($this->userClaims as $userClaim) {
            if ($userClaim) {
                $data += $userClaim->toArray();
            }
        }
        return $data;
    }
}
