<?php

namespace Transip\Api\Library\Entity\Vps;

class OperatingSystem extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $name = NULL;
    protected $description = NULL;
    protected $version = NULL;
    protected $price = NULL;
    protected $installFlavours = [];
    protected $licenses = [];
    const INSTALL_FLAVOUR_INSTALLER = "installer";
    const INSTALL_FLAVOUR_PREINSTALLABLE = "preinstallable";
    const INSTALL_FLAVOUR_CLOUDINIT = "cloudinit";

    public function __construct($valueArray = [])
    {
        $licenses = $valueArray["licenses"] ?? [];
        foreach ($licenses as $license) {
            $this->licenses[] = new LicenseProduct($license);
        }
        unset($valueArray["licenses"]);
        parent::__construct($valueArray);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function isPreinstallableImage()
    {
        return in_array(self::INSTALL_FLAVOUR_PREINSTALLABLE, $this->getInstallFlavours(), true);
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getPrice()
    {
        return $this->price;
    }

    public function getInstallFlavours()
    {
        return $this->installFlavours;
    }

    public function getLicenses()
    {
        return $this->licenses;
    }
}
