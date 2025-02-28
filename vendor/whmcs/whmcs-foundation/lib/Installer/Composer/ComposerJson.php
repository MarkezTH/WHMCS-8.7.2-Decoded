<?php

namespace WHMCS\Installer\Composer;

class ComposerJson
{
    private $minimumStability = NULL;
    private $allowInsecureHttp = NULL;
    private $repositories = NULL;
    private $require = NULL;
    const STABILITY_DEV = "dev";
    const STABILITY_ALPHA = "alpha";
    const STABILITY_BETA = "beta";
    const STABILITY_RC = "RC";
    const STABILITY_STABLE = "stable";

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        $this->setMinimumStability(self::STABILITY_STABLE);
        $this->setAllowInsecureHttp(false);
        $this->repositories = [];
        $this->require = [];
    }

    public function getMinimumStability()
    {
        return $this->minimumStability;
    }

    public function setMinimumStability($value)
    {
        $this->checkStability($value);
        $this->minimumStability = $value;
        return $this;
    }

    public function checkStability($stability)
    {
        $allowedMinStabs = [self::STABILITY_DEV, self::STABILITY_ALPHA, self::STABILITY_BETA, self::STABILITY_RC, self::STABILITY_STABLE];
        if (!in_array($stability, $allowedMinStabs)) {
            throw new ComposerUpdateException("Invalid minimum stability");
        }
    }

    public function getAllowInsecureHttp()
    {
        return $this->allowInsecureHttp;
    }

    public function setAllowInsecureHttp($value)
    {
        $this->allowInsecureHttp = $value;
        return $this;
    }

    public function addRepository($url, $type = "composer")
    {
        $repo = ["type" => $type, "url" => $url];
        $this->repositories[$url] = $repo;
        return $this;
    }

    public function disablePackagist()
    {
        $this->repositories["packagist"] = ["packagist" => false];
        return $this;
    }

    public function enablePackagist()
    {
        if (isset($this->repositories["packagist"])) {
            unset($this->repositories["packagist"]);
        }
        return $this;
    }

    public function getVersionRequirementFromVersionPin($version)
    {
        if (!preg_match("/^\\d+(\\.\\d+){0,1}\$/", $version)) {
            throw new ComposerUpdateException("Cannot pin to version: " . $version);
        }
        return $version . ".*";
    }

    public function addRequirementWithVersion($packageName, $version)
    {
        $this->require[$packageName] = $version;
        return $this;
    }

    public function addRequirementWithStability($packageName, $stability)
    {
        $this->checkStability($stability);
        return $this->addRequirementWithVersion($packageName, "@" . $stability);
    }

    public function buildArray()
    {
        $jsonData["repositories"] = array_values($this->repositories);
        $jsonData["minimum-stability"] = $this->getMinimumStability();
        $jsonData["require"] = $this->require;
        $jsonData["autoload"]["psr-4"]["WHMCS\\"] = "vendor/whmcs/whmcs/vendor/whmcs/whmcs-foundation/lib";
        $jsonData["scripts"] = ["post-install-cmd" => ["WHMCS\\Installer\\Composer\\Hooks\\ComposerInstallerHook::postInstallCmd"], "post-update-cmd" => ["WHMCS\\Installer\\Composer\\Hooks\\ComposerInstallerHook::postUpdateCmd"]];
        if ($this->getAllowInsecureHttp()) {
            $jsonData["config"]["secure-http"] = false;
        }
        return $jsonData;
    }

    public function build()
    {
        return json_encode($this->buildArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
