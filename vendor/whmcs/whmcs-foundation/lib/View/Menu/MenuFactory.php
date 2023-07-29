<?php

namespace WHMCS\View\Menu;

class MenuFactory extends \Knp\Menu\MenuFactory
{
    protected $loader = NULL;
    protected $rootItemName = "";

    public function __construct()
    {
        parent::__construct();
        $this->loader = new \Knp\Menu\Loader\ArrayLoader($this);
    }

    public function createItem($ItemInterface, $name = [], $options)
    {
        $extension = new Factory\WhmcsExtension();
        $options = $extension->buildOptions($options);
        $item = parent::createItem($name, $options);
        $item = unserialize(sprintf("O:%d:\"%s\"%s", strlen("WHMCS\\View\\Menu\\Item"), "WHMCS\\View\\Menu\\Item", strstr(strstr(serialize($item), "\""), ":")));
        $extension->buildItem($item, $options);
        return $item;
    }

    protected function buildMenuStructure($structure = [])
    {
        return ["name" => $this->rootItemName, "children" => $structure];
    }

    public function emptySidebar()
    {
        return $this->loader->load($this->buildMenuStructure());
    }

    public function getLoader()
    {
        return $this->loader;
    }

    public function isOnRoutePath($routePathName, $wildcardMatch = false)
    {
        $route = routePath($routePathName);
        $requestUri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        if ($wildcardMatch) {
            return substr($requestUri, 0, strlen($route)) == $route;
        }
        return $requestUri == $route;
    }

    public function isOnGivenRoutePath($route)
    {
        $requestUri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
        return $requestUri == $route;
    }
}
