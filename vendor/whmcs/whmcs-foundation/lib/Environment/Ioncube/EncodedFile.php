<?php

namespace WHMCS\Environment\Ioncube;

class EncodedFile implements Contracts\EncodedFileInterface
{
    private $filename = NULL;
    private $encoderVersion = NULL;
    private $targetPhpVersion = "";
    private $bundledPhpVersions = [];
    private $fileContentHash = NULL;
    const MAX_LINES_IN_PREAMBLE = 1;
    const ENCODER_VERSION_PREAMBLE_HASHES = NULL;
    const VALID_BUNDLED_PHP72_VERSIONS = ["7.2", "7.3", "7.4"];

    public function __construct($filename = NULL, $contentHash = NULL, $encoderVersion = NULL, $bundledPhpVersions = NULL, $targetPhpVersion = NULL)
    {
        if (!is_null($filename) && !is_null($contentHash) && !is_null($encoderVersion) && !is_null($bundledPhpVersions) && !is_null($targetPhpVersion)) {
            $this->filename = $filename;
            $this->fileContentHash = $contentHash;
            $this->encoderVersion = $encoderVersion;
            $this->bundledPhpVersions = $bundledPhpVersions;
            $this->targetPhpVersion = $targetPhpVersion;
        } else {
            if ($filename) {
                $this->filename = $filename;
                $this->analyze();
            }
        }
    }

    public function getFileContentHash()
    {
        if (!$this->fileContentHash) {
            $this->fileContentHash = static::generateFileContentHash($this->getFilename());
        }
        return $this->fileContentHash;
    }

    public static function generateFileContentHash($filename)
    {
        return sha1_file($filename);
    }

    public function analyze()
    {
        $hFile = fopen($this->filename, "r");
        if (!is_resource($hFile)) {
            throw new \WHMCS\Exception("Could not open file'" . $this->filename . "'");
        }
        try {
            $firstLine = fgets($hFile);
            if (empty($firstLine)) {
                $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_NONE;
            } else {
                if (!is_string($firstLine)) {
                    throw new \WHMCS\Exception("Could not read from file '" . $this->filename . "'");
                }
                if (preg_match("|//ICB0 ([\\d\\:a-f\\s]+)|", $firstLine, $matches)) {
                    $phpVersionMarker = trim($matches[1]);
                    if (preg_match_all("/([\\d]+)\\:[\\da-f]+/i", $phpVersionMarker, $matches)) {
                        foreach ($matches[1] as $phpVersionSignature) {
                            $phpVersion = substr($phpVersionSignature, 0, 1) . "." . substr($phpVersionSignature, 1);
                            $this->bundledPhpVersions[] = $phpVersion;
                        }
                        if (!empty($this->bundledPhpVersions)) {
                            $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_V10_PLUS_BUNDLED;
                        }
                        foreach ($this->bundledPhpVersions as $version) {
                            if (strpos($version, "8") === 0) {
                                $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_V12_PLUS_BUNDLED;
                                unset($version);
                            }
                        }
                    }
                }
                if (empty($this->encoderVersion)) {
                    $preamble = "";
                    $linesRead = 0;
                    while (!feof($hFile)) {
                        $line = fgets($hFile);
                        if (strlen(trim($line)) != 0) {
                            if (strpos($line, "//") === 0) {
                                if (preg_match("|^//\\s+(\\d+)\\.(\\d+)\\s+(\\d+)|", $line, $matches)) {
                                    $encoderMajorVersion = $matches[1];
                                    if ($encoderMajorVersion == "8") {
                                        $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_V8_OR_OLDER;
                                    } else {
                                        if ($encoderMajorVersion == "9") {
                                            $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_V9_PLUS_NON_BUNDLED;
                                        } else {
                                            if ($encoderMajorVersion == "10") {
                                                $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_V10_PLUS_NON_BUNDLED;
                                            } else {
                                                if ($encoderMajorVersion == "11") {
                                                    $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_V11_PLUS_NON_BUNDLED;
                                                } else {
                                                    if ($encoderMajorVersion == "12") {
                                                        $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_V12_PLUS_NON_BUNDLED;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if (!empty($this->encoderVersion)) {
                                        $targetPhpVersionMarker = $matches[3];
                                        $this->targetPhpVersion = substr($targetPhpVersionMarker, 0, 1) . "." . substr($targetPhpVersionMarker, 1, 1);
                                    }
                                }
                            } else {
                                if (strpos($line, "?>") !== 0) {
                                    $preamble .= $line;
                                    if (self::MAX_LINES_IN_PREAMBLE > ++$linesRead) {
                                    }
                                }
                            }
                        }
                    }
                    if (empty($this->encoderVersion)) {
                        if (stripos($preamble, "extension_loaded('ionCube Loader')") === false) {
                            $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_NONE;
                        } else {
                            $preambleHash = strtolower(hash("sha1", $preamble));
                            if (array_key_exists($preambleHash, self::ENCODER_VERSION_PREAMBLE_HASHES)) {
                                $this->encoderVersion = self::ENCODER_VERSION_PREAMBLE_HASHES[$preambleHash];
                            } else {
                                $this->encoderVersion = Contracts\EncodedFileInterface::ENCODER_VERSION_UNKNOWN;
                            }
                        }
                    }
                }
            }
        } finally {
            fclose($hFile);
        }
    }

    public function getEncoderVersion()
    {
        return $this->encoderVersion;
    }

    public function isBundled()
    {
        return !empty($this->bundledPhpVersions);
    }

    public function hasPhpVersionBundled($phpVersion)
    {
        return (bool) in_array($phpVersion, $this->bundledPhpVersions);
    }

    public function getBundledPhpVersions()
    {
        return $this->bundledPhpVersions;
    }

    public function getTargetPhpVersion()
    {
        return $this->targetPhpVersion;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function canRunOnIoncubeLoaderVersion($ioncubeLoaderVersion)
    {
        if (!$this->encoderVersion) {
            throw new \WHMCS\Exception("Encoder version was not read");
        }
        $requiredLoaderVersion = NULL;
        switch ($this->encoderVersion) {
            case self::ENCODER_VERSION_V12_PLUS_NON_BUNDLED:
                $requiredLoaderVersion = "12.0";
                break;
            case self::ENCODER_VERSION_V12_PLUS_BUNDLED:
            case self::ENCODER_VERSION_V10_PLUS_BUNDLED:
                $requiredLoaderVersion = "10.1";
                break;
            case self::ENCODER_VERSION_V10_PLUS_NON_BUNDLED:
                $requiredLoaderVersion = "10.0";
                break;
            case self::ENCODER_VERSION_V9_PLUS_NON_BUNDLED:
                $requiredLoaderVersion = "6.0";
                break;
            case self::ENCODER_VERSION_V8_OR_OLDER:
                $requiredLoaderVersion = "4.0";
                if ($requiredLoaderVersion) {
                    return version_compare($ioncubeLoaderVersion, $requiredLoaderVersion, ">=");
                }
                return false;
                break;
            case self::ENCODER_VERSION_OUTDATED:
                return false;
                break;
            default:
                return true;
        }
    }

    public function canRunOnInstalledIoncubeLoader()
    {
        $installedIoncubeLoaderVersion = Loader\LocalLoader::getVersion();
        if ($installedIoncubeLoaderVersion) {
            return $this->canRunOnIoncubeLoaderVersion($installedIoncubeLoaderVersion->getVersion());
        }
        return $this->encoderVersion === self::ENCODER_VERSION_NONE;
    }

    private function canBundleRunOnPhpVersion($phpVersion)
    {
        if (!$this->isBundled()) {
            throw new \WHMCS\Exception("This file is not encoded with a bundle");
        }
        $phpVersionMajorMinor = preg_replace("/^([\\d]+\\.[\\d]+)(.*)/", "", $phpVersion);
        $canRun = (bool) in_array($phpVersionMajorMinor, $this->bundledPhpVersions);
        if (!$canRun) {
            if (version_compare($phpVersion, "7.0", "<=")) {
                $canRun = in_array("5.6", $this->bundledPhpVersions);
            } else {
                if (version_compare($phpVersion, "8.0", "<")) {
                    $canRun = in_array("7.1", $this->bundledPhpVersions);
                    if (version_compare($phpVersion, "7.2", ">=")) {
                        $compare = array_intersect(self::VALID_BUNDLED_PHP72_VERSIONS, $this->bundledPhpVersions);
                        if (count($compare) == 1) {
                            $canRun = true;
                        }
                    }
                } else {
                    $canRun = in_array("8.1", $this->bundledPhpVersions);
                }
            }
        }
        return $canRun;
    }

    public function canRunOnPhpVersion($phpVersion)
    {
        if (!$this->encoderVersion) {
            throw new \WHMCS\Exception("Encoder version was not read");
        }
        switch ($this->encoderVersion) {
            case self::ENCODER_VERSION_V12_PLUS_BUNDLED:
            case self::ENCODER_VERSION_V10_PLUS_BUNDLED:
                $canRun = $this->canBundleRunOnPhpVersion($phpVersion);
                break;
            case self::ENCODER_VERSION_V12_PLUS_NON_BUNDLED:
                $canRun = version_compare($phpVersion, "8.1", ">=");
                break;
            case self::ENCODER_VERSION_V10_PLUS_NON_BUNDLED:
                if (version_compare($this->targetPhpVersion, "7.1", "<")) {
                    $canRun = version_compare($phpVersion, "7.1", "<");
                } else {
                    $canRun = version_compare($phpVersion, "7.0", ">");
                }
                $canRun = $canRun && !version_compare($phpVersion, $this->targetPhpVersion, "<");
                break;
            case self::ENCODER_VERSION_V9_PLUS_NON_BUNDLED:
                $canRun = version_compare($phpVersion, "7.1", "<");
                if (!empty($this->targetPhpVersion) && $canRun) {
                    $canRun = !version_compare($phpVersion, $this->targetPhpVersion, "<");
                }
                break;
            case self::ENCODER_VERSION_V8_OR_OLDER:
                $canRun = version_compare($phpVersion, "7.0", "<");
                if (!empty($this->targetPhpVersion) && $canRun) {
                    $canRun = !version_compare($phpVersion, $this->targetPhpVersion, "<");
                }
                break;
            case self::ENCODER_VERSION_OUTDATED:
                $canRun = false;
                break;
            default:
                $canRun = true;
                return $canRun;
        }
    }

    public function canRunOnInstalledPhpVersion()
    {
        return $this->canRunOnPhpVersion(PHP_VERSION);
    }

    public function getLoggable()
    {
        $loggable = new Log\File(["filename" => $this->getFilename(), "content_hash" => $this->getFileContentHash(), "encoder_version" => $this->getEncoderVersion(), "bundled_php_versions" => $this->getBundledPhpVersions(), "loaded_in_php" => [], "target_php_version" => $this->getTargetPhpVersion()]);
        $loggable->setAnalyzer($this);
        return $loggable;
    }

    public function versionCompatibilityAssessment($phpVersion, Contracts\LoaderInterface $loader = NULL)
    {
        if (!$this->encoderVersion) {
            throw new \WHMCS\Exception("Encoder version was not read");
        }
        switch ($this->encoderVersion) {
            case Contracts\EncodedFileInterface::ENCODER_VERSION_V12_PLUS_BUNDLED:
            case Contracts\EncodedFileInterface::ENCODER_VERSION_V10_PLUS_BUNDLED:
                if ($this->canBundleRunOnPhpVersion($phpVersion)) {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_YES;
                } else {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_NO;
                }
                break;
            case Contracts\EncodedFileInterface::ENCODER_VERSION_V12_PLUS_NON_BUNDLED:
            case Contracts\EncodedFileInterface::ENCODER_VERSION_V10_PLUS_NON_BUNDLED:
                if ($this->canRunOnPhpVersion($phpVersion)) {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_YES;
                } else {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_NO;
                }
                break;
            case Contracts\EncodedFileInterface::ENCODER_VERSION_V9_PLUS_NON_BUNDLED:
                if ($this->canRunOnPhpVersion($phpVersion)) {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_LIKELY;
                } else {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_NO;
                }
                break;
            case Contracts\EncodedFileInterface::ENCODER_VERSION_V8_OR_OLDER:
                if ($this->canRunOnPhpVersion($phpVersion)) {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_LIKELY;
                } else {
                    $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_NO;
                }
                break;
            case Contracts\EncodedFileInterface::ENCODER_VERSION_OUTDATED:
                $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_NO;
                break;
            default:
                $assessment = Contracts\EncodedFileInterface::ASSESSMENT_COMPAT_UNLIKELY;
                return $assessment;
        }
    }
}
