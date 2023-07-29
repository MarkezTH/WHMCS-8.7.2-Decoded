<?php

namespace WHMCS\Exception\Authentication;

class RequiresSecondFactor extends AbstractAuthenticationException
{
    private $user = NULL;

    public static function createForUser(\WHMCS\User\User $user)
    {
        $self = new static();
        $self->user = $user;
        return $self;
    }

    public function getUser($User)
    {
        return $this->user;
    }
}
