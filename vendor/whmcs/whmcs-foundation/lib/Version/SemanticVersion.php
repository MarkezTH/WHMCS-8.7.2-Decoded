<?php

namespace WHMCS\Version;

class SemanticVersion extends AbstractVersion
{
    protected function getSemVerPattern()
    {
        $pattern = "^(0|[1-9]\\d*)\\.(0|[1-9]\\d*)\\.(0|[1-9]\\d*)(?:-(0|[1-9]\\d*|\\d*[a-zA-Z-][a-zA-Z0-9-]*)(?:\\.((?:(?:0|[1-9]\\d*|[a-zA-Z-][a-zA-Z]*)\\.?)*).*)?)?\$";
        return $pattern;
    }

    public static function isSemantic($version)
    {
        try {
            new self($version);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isValid($version)
    {
        if (!is_string($version)) {
            return false;
        }
        try {
            $versionParts = $this->separateBuildTag($version);
        } catch (\WHMCS\Exception\Version\Parse $e) {
            return false;
        }
        $pattern = $this->getSemVerPattern();
        return preg_match("/" . $pattern . "/", $versionParts[0]) ? true : false;
    }

    protected function separateBuildTag($version)
    {
        $versionParts = explode("+", $version, 2);
        if (empty($versionParts[0])) {
            throw new \WHMCS\Exception\Version\BadVersionNumber(sprintf("Missing primary version info in \"%s\"", $version));
        }
        if (count($versionParts) == 2 && empty($versionParts[1])) {
            throw new \WHMCS\Exception\Version\BadVersionNumber(sprintf("Missing build tag info in \"%s\"", $version));
        }
        return $versionParts;
    }

    public function parse($version)
    {
        $pattern = $this->getSemVerPattern();
        $versionParts = $this->separateBuildTag($version);
        $matches = [];
        if (preg_match("/" . $pattern . "/", $versionParts[0], $matches) === false) {
            throw new \WHMCS\Exception\Version\BadVersionNumber(sprintf("\"%s\" is not a semantic version string!", $version));
        }
        if (count($matches) < 4) {
            throw new \WHMCS\Exception\Version\BadVersionNumber(sprintf("\"%s\" is not a semantic version string! Too few version segments.", $version));
        }
        array_shift($matches);
        if (isset($matches[0])) {
            $this->setMajor(strtolower($matches[0]));
        } else {
            $this->setMajor(NULL);
        }
        if (isset($matches[1])) {
            $this->setMinor(strtolower(str_replace(".", "", $matches[1])));
        } else {
            $this->setMinor(NULL);
        }
        if (isset($matches[2])) {
            $this->setPatch(strtolower(str_replace(".", "", $matches[2])));
        } else {
            $this->setPatch(NULL);
        }
        if (isset($matches[3])) {
            $this->setPreReleaseIdentifier(strtolower($matches[3]));
        } else {
            $this->setPreReleaseIdentifier(NULL);
        }
        if (isset($matches[4])) {
            $this->setPreReleaseRevision(strtolower($matches[4]));
        } else {
            $this->setPreReleaseRevision(NULL);
        }
        if (empty($versionParts[1])) {
            $versionParts[1] = NULL;
        }
        $this->setBuildTag($versionParts[1]);
        return $this;
    }

    public function getCanonical()
    {
        $version = parent::getCanonical();
        $label = $this->getPreReleaseIdentifier();
        $preRelRevision = $this->getPreReleaseRevision();
        if (!$label) {
            $label = self::DEFAULT_PRERELEASE_IDENTIFIER;
        }
        if (!$preRelRevision) {
            $preRelRevision = self::DEFAULT_PRERELEASE_REVISION;
        }
        $version = sprintf("%s-%s.%d", $version, $label, $preRelRevision);
        return $version;
    }

    public function getRelease($precision = false)
    {
        return implode(".", array_slice(explode(".", parent::getCanonical()), 0, $precision));
    }

    public function getPreReleaseIdentifierPriority()
    {
        return self::getLabelPriority($this->getPreReleaseIdentifier());
    }

    public static function compare(SemanticVersion $a, SemanticVersion $b, $operator)
    {
        return self::compareVersions($a, $b, $operator, false);
    }

    public static function isNextRevision(SemanticVersion $current, SemanticVersion $next)
    {
        $currentPlusOne = new SemanticVersion($current->getCanonical());
        $patchNumber = (int) $currentPlusOne->getPatch();
        $currentPlusOne->setPatch($patchNumber + 1);
        return self::compareVersions($currentPlusOne, $next, "=", true);
    }

    private static function compareVersions(SemanticVersion $a, SemanticVersion $b, $operator, $allowLooseReleaseIncrement = false)
    {
        $primaryA = $a->getRelease();
        $primaryB = $b->getRelease();
        $status = version_compare($primaryA, $primaryB);
        if ($status !== 0) {
            return self::getBoolForOperatorCompare($operator, $status);
        }
        $map = self::getLabelHierarchyMap();
        $labelA = $map[$a->getPreReleaseIdentifier()];
        $labelB = $map[$b->getPreReleaseIdentifier()];
        $status = version_compare($labelA, $labelB);
        if ($status !== 0) {
            return self::getBoolForOperatorCompare($operator, $status);
        }
        $preRevisionA = $a->getPreReleaseRevision();
        $preRevisionA = $preRevisionA ? $preRevisionA : 1;
        $preRevisionB = $b->getPreReleaseRevision();
        $preRevisionB = $preRevisionB ? $preRevisionB : 1;
        $status = version_compare($preRevisionA, $preRevisionB);
        if ($allowLooseReleaseIncrement && $status !== 0 && ($operator == "=" || $operator == "==")) {
            $aPreviousCanonical = $a->setPatch($a->getPatch() - 1)->getCanonical();
            $isBumpComparison = $a->getVersion() == $aPreviousCanonical;
            if ($isBumpComparison) {
                return true;
            }
        }
        if ($status !== 0) {
            return self::getBoolForOperatorCompare($operator, $status);
        }
        if ($status !== 0) {
            return true;
        }
        if ($operator == "=" || $operator == "==") {
            return true;
        }
        return false;
    }

    protected static function getBoolForOperatorCompare($operator, $status)
    {
        if ($status == -1) {
            if ($operator == "<" || $operator == "lt") {
                return true;
            }
            return false;
        }
        if ($status == 1) {
            if ($operator == ">" || $operator == "gt") {
                return true;
            }
            return false;
        }
    }

    protected static function getLabelHierarchyMap()
    {
        return ["alpha" => 1, "beta" => 2, "rc" => 3, "" => 4, "release" => 4, "stable" => 5];
    }

    public static function getLabelPriority($label)
    {
        return self::getLabelHierarchyMap()[$label];
    }

    public static function compareForSort(SemanticVersion $versionA, SemanticVersion $versionB)
    {
        if (static::compare($versionA, $versionB, "=")) {
            return 0;
        }
        return static::compare($versionA, $versionB, "<") ? -1 : 1;
    }

    public static function isMajorVersionAtLeast($version, int $major = NULL, int $minor = NULL, int $patch = NULL, $releaseLabel)
    {
        if ($version->getMajor() != $major) {
            return false;
        }
        if (!is_null($minor) && ($version->getMinor() != $minor || $minor < $version->getMinor())) {
            return false;
        }
        if (!is_null($patch) && ($version->getPatch() != $patch || $patch < $version->getPatch())) {
            return false;
        }
        if (!is_null($releaseLabel) && ($version->getPreReleaseIdentifierPriority() != self::getLabelPriority($releaseLabel) || self::getLabelPriority($releaseLabel) < $version->getPreReleaseIdentifierPriority())) {
            return false;
        }
        return true;
    }

    public static function sort($versions = SORT_ASC, int $order)
    {
        $direction = $order === SORT_ASC ? 1 : -1;
        usort($versions, function (SemanticVersion $a, SemanticVersion $b) use($direction) {
            return $direction * SemanticVersion::compareForSort($a, $b);
        });
    }
}
