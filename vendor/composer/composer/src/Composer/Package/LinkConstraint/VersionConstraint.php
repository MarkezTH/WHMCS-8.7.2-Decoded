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

namespace Composer\Package\LinkConstraint;

use Composer\Semver\Constraint\Constraint;

trigger_error('The ' . __NAMESPACE__ . '\VersionConstraint class is deprecated, use Composer\Semver\Constraint\Constraint instead.', E_USER_DEPRECATED);

/**
 * @deprecated use Composer\Semver\Constraint\Constraint instead
 */
class VersionConstraint extends Constraint implements LinkConstraintInterface
{
}
