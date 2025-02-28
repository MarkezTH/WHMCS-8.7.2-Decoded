<?php
/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Repository;

use Composer\Package\AliasPackage;

/**
 * Writable array repository.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class WritableArrayRepository extends ArrayRepository implements WritableRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function write()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function reload()
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getCanonicalPackages()
    {
        $packages = $this->getPackages();

        // get at most one package of each name, preferring non-aliased ones
        $packagesByName = array();
        foreach ($packages as $package) {
            if (!isset($packagesByName[$package->getName()]) || $packagesByName[$package->getName()] instanceof AliasPackage) {
                $packagesByName[$package->getName()] = $package;
            }
        }

        $canonicalPackages = array();

        // unfold aliased packages
        foreach ($packagesByName as $package) {
            while ($package instanceof AliasPackage) {
                $package = $package->getAliasOf();
            }

            $canonicalPackages[] = $package;
        }

        return $canonicalPackages;
    }
}
