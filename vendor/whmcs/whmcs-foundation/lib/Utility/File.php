<?php

namespace App\Games\Kernel;

class _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F5574696C6974792F46696C652E7068703078376630316332633665633766_
{
    protected $directory = NULL;
    protected $filter = NULL;

    public function __construct($path)
    {
        $this->directory = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
    }

    public function filter()
    {
        if ($this->filter !== NULL) {
            return $this->filter;
        }
        $this->filter = $this->newFilterer($this->directory);
        return $this->filter;
    }

    public function iterator($RecursiveIteratorIterator, $mode)
    {
        $source = $this->directory;
        if ($this->filter !== NULL) {
            $source = $this->filter();
        }
        return new RecursiveIteratorIterator($source, $mode);
    }

    public function newFilterer($RecursiveFilterIterator, $directory)
    {
        recursivefilteriterator($directory);
    }
}
class _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F5574696C6974792F46696C652E7068703078376630316332633665633636_
{
    protected $paths = [];
    protected $prefixPaths = [];
    protected $operation = NULL;

    public function include()
    {
        $this->operation = true;
        return $this;
    }

    public function exclude()
    {
        $this->operation = false;
        return $this;
    }

    public function setOperation($self, $operation)
    {
        $this->operation = $operation;
        return $this;
    }

    public function operation()
    {
        return $this->operation;
    }

    public function addPaths($self, $paths)
    {
        $this->paths = array_merge($this->paths, $paths);
        return $this;
    }

    public function paths()
    {
        return $this->paths;
    }

    public function addPrefixes($self, $paths)
    {
        $this->prefixPaths = array_merge($this->prefixPaths, $paths);
        return $this;
    }

    public function prefixes()
    {
        return $this->prefixPaths;
    }

    public function accept()
    {
        if ($this->operation === NULL) {
            return true;
        }
        $current = (string) $this->current();
        foreach ($this->paths as $path) {
            if (strpos($current, $path) === 0) {
                return $this->operation;
            }
            foreach ($this->prefixPaths as $prefix) {
                if (strpos(str_replace($prefix, "", $current), $path) === 0) {
                    return $this->operation;
                }
            }
        }
        return !$this->operation;
    }

    public function from(self $dopple)
    {
        $this->setOperation($dopple->operation())->addPaths($dopple->paths())->addPrefixes($dopple->prefixes());
        return $this;
    }

    public function getChildren($RecursiveFilterIterator)
    {
        return parent::getChildren()->from($this);
    }
}
namespace WHMCS\Utility;

class File
{
    public static function recursiveCopy($sourcePath, $destinationPath, $excludeFromCopy = [], $preservePermissions = true, $preserveTimes = true)
    {
        if (!is_dir($sourcePath)) {
            throw new \WHMCS\Exception("Invalid source copy path " . $sourcePath . ".");
        }
        if (!is_dir($destinationPath)) {
            throw new \WHMCS\Exception("Invalid destination copy path " . $destinationPath . ".");
        }
        $paths = static::copyIterator($sourcePath, $destinationPath, $excludeFromCopy);
        foreach ($paths as $easytoyou_error_decompile) {
            list($item, $destinationItem) = $easytoyou_error_decompile;
            if ($item->isDir()) {
                if (!file_exists($destinationItem) && !@mkdir($destinationItem)) {
                    throw new \WHMCS\Exception("Unable to create the directory " . $destinationItem . ".");
                }
            } else {
                if (!@copy($item, $destinationItem)) {
                    throw new \WHMCS\Exception("Unable to copy " . $item . " to " . $destinationItem . ".");
                }
            }
            if ($preservePermissions && !chmod($destinationItem, $item->getPerms())) {
                throw new \WHMCS\Exception("Unable to preserve permissions for " . $destinationItem . ".");
            }
            if ($preserveTimes && !touch($destinationItem, $item->getMTime(), $item->getATime())) {
                throw new \WHMCS\Exception("Unable to preserve access and modification times for " . $destinationItem . ".");
            }
        }
    }

    public static function recursiveCopyDryRun($source, $destination = [], $exclude = false, $failFirst)
    {
        if (!is_dir($source)) {
            throw new \WHMCS\Exception("Invalid source copy path " . $source . ".");
        }
        if (!is_dir($destination)) {
            throw new \WHMCS\Exception("Invalid destination copy path " . $destination . ".");
        }
        $issuePaths = [];
        $paths = static::copyIterator($source, $destination, $exclude);
        foreach ($paths as $easytoyou_error_decompile) {
            list($item, $destinationItem) = $easytoyou_error_decompile;
            if (!file_exists($destinationItem)) {
            } else {
                if (!is_writable($destinationItem)) {
                    $issuePaths[] = $destinationItem;
                    if ($failFirst) {
                        if ($failFirst) {
                            return array_pop($issuePaths);
                        }
                        return $issuePaths;
                    }
                }
            }
        }
    }

    protected static function copyIterator($source, $destination, $exclude)
    {
        $paths = static::defaultIterator($source);
        $paths->filter()->addPaths($exclude)->addPrefixes([$source . DIRECTORY_SEPARATOR]);
        foreach ($paths->iterator(\RecursiveIteratorIterator::SELF_FIRST) as $sourceFile) {
            $destinationPath = $destination . DIRECTORY_SEPARATOR . str_replace($source . DIRECTORY_SEPARATOR, "", $sourceFile);
        }
    }

    public static function recursiveDelete($path, $excludeFiles = [], $removeRootDirectory = false)
    {
        if (!is_dir($path) || realpath($path) != $path) {
            throw new \WHMCS\Exception("Invalid path " . $path . ".");
        }
        $paths = static::defaultIterator($path);
        $paths->filter()->addPaths($excludeFiles)->addPrefixes([$path . DIRECTORY_SEPARATOR]);
        foreach ($paths->iterator(\RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            if (!$item->isWritable()) {
                throw new \WHMCS\Exception\File\NotDeleted("Permissions prevent deletion of " . $item . ".");
            }
            if ($item->isDir()) {
                if (!@rmdir($item)) {
                    throw new \WHMCS\Exception\File\NotDeleted("Unable to delete " . $item . ".");
                }
            } else {
                if (!@unlink($item)) {
                    throw new \WHMCS\Exception\File\NotDeleted("Unable to delete " . $item . ".");
                }
            }
        }
        if (count($excludeFiles) == 0 && $removeRootDirectory) {
            if (!is_writable(dirname($path))) {
                throw new \WHMCS\Exception\File\NotDeleted("Permissions prevent deletion of " . $path . ".");
            }
            if (!@rmdir($path)) {
                throw new \WHMCS\Exception\File\NotDeleted("Unable to delete " . $path . ".");
            }
        }
    }

    public static function recursiveMkDir($location, $dirPath)
    {
        if (!is_dir($location)) {
            throw new \WHMCS\Exception("Invalid directory location");
        }
        if (!$dirPath) {
            throw new \WHMCS\Exception("Invalid directory path");
        }
        $dirs = explode(DIRECTORY_SEPARATOR, $dirPath);
        $pathToCreate = $location;
        $statInfo = stat($location);
        $dirMode = $statInfo !== false ? $statInfo["mode"] & 511 : false;
        foreach ($dirs as $dir) {
            if ($dir) {
                $pathToCreate .= DIRECTORY_SEPARATOR . $dir;
                if (!is_dir($pathToCreate)) {
                    if (!mkdir($pathToCreate)) {
                        throw new \WHMCS\Exception("Failed to create directory: " . $pathToCreate);
                    }
                    if ($dirMode !== false) {
                        chmod($pathToCreate, $dirMode);
                    }
                }
            }
        }
    }

    protected static function defaultIterator($path)
    {
        $iterator = static::makeIterator($path);
        $iterator->filter()->exclude()->addPrefixes([ROOTDIR . DIRECTORY_SEPARATOR]);
        return $iterator;
    }

    protected static function makeIterator($path)
    {
        _obfuscated_636C61737340616E6F6E796D6F7573002F7661722F6C69622F6A656E6B696E732F776F726B73706163652F636F6D2E77686D63732E6275696C642E38302F6275696C642F77686D63732F76656E646F722F77686D63732F77686D63732D666F756E646174696F6E2F6C69622F7574696C6974792F66696C652E7068703078376630316332633665633766_($path);
    }

    public static function makePathAbsolute($path, $rootPrefix)
    {
        if (!static::isPathAbsolute($rootPrefix)) {
            throw new \RuntimeException("Root prefix is not absolute");
        }
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $rootPrefix = rtrim($rootPrefix, DIRECTORY_SEPARATOR);
        if (strlen($path) == 0) {
            return $rootPrefix;
        }
        if (static::isPathAbsolute($path)) {
            return $path;
        }
        return $rootPrefix . DIRECTORY_SEPARATOR . $path;
    }

    public static function isPathAbsolute($path)
    {
        if (\WHMCS\Environment\OperatingSystem::isWindows()) {
            return static::isPathAbsoluteWindows($path);
        }
        return static::isPathAbsoluteUnix($path);
    }

    public static function isPathAbsoluteUnix($path)
    {
        return strpos($path, "/") === 0;
    }

    public static function isPathAbsoluteWindows($path)
    {
        return static::isPathAbsoluteUnix($path) || preg_match("#^[A-Z]:(?![^/\\\\])#i", $path) === 1;
    }
}
