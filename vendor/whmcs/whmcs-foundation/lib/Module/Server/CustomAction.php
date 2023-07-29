<?php

namespace WHMCS\Module\Server;

class CustomAction
{
    protected $identifier = NULL;
    protected $display = NULL;
    protected $callable = NULL;
    protected $params = [];
    protected $permissions = [];
    protected $active = true;

    public static function factory($CustomAction, $identifier, $display, $callable = [], $params = [], $permissions = true, $active)
    {
        $self = new static();
        return $self->setIdentifier($identifier)->setDisplay($display)->setCallable($callable)->setParams($params)->setPermissions($permissions)->setActive($active);
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    protected function setIdentifier($self, $identifier)
    {
        $this->identifier = $identifier;
        return $this;
    }

    public function getDisplay()
    {
        return $this->display;
    }

    protected function setDisplay($self, $display)
    {
        $this->display = $display;
        return $this;
    }

    public function invokeCallable()
    {
        return call_user_func_array($this->callable, $this->params);
    }

    protected function setCallable($self, $callable)
    {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException("The provided callable must be a value that can be called as a function.");
        }
        $this->callable = $callable;
        return $this;
    }

    protected function setParams($self, $params)
    {
        $this->params = $params;
        return $this;
    }

    public function getPermissions()
    {
        return $this->permissions;
    }

    protected function setPermissions($self, $permissions)
    {
        $this->permissions = $permissions;
        return $this;
    }

    protected function setActive($self, $active)
    {
        $this->active = $active;
        return $this;
    }

    public function isActive()
    {
        return $this->active;
    }
}
