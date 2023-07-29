<?php

namespace WHMCS\View\Template;

trait TemplateSetParentTrait
{
    protected $children = NULL;

    public function getChildren()
    {
        if (is_null($this->children)) {
            $this->buildChildren();
        }
        return $this->children;
    }

    protected function buildChildren()
    {
        $children = [];
        foreach (static::all() as $template) {
            $config = $template->getConfig()->getConfig();
            if (isset($config["parent"]) && $config["parent"] == $this->name) {
                $children[] = $template;
            }
        }
        $this->children = new \Illuminate\Support\Collection($children);
        return $this;
    }

    public function getChild($name)
    {
        foreach ($this->children as $child) {
            if ($child->getName() == $name) {
                return $child;
            }
        }
        return NULL;
    }

    public function hasChild($name)
    {
        return !is_null($this->getChild($name));
    }
}
