<?php

namespace WHMCS\Environment\Ioncube\Inspector\Iterator;

class Directory extends AbstractInspectorIterator
{
    public static function fromDirectory($directory = "")
    {
        $instance = new static();
        $instance->exchangeArray($instance->inspectDirectory($directory));
        return $instance;
    }

    public function inspectDirectory($directory = NULL)
    {
        if (!$directory) {
            $directory = ROOTDIR;
        } else {
            if (!is_dir($directory) || !is_readable($directory)) {
                throw new \Psr\Log\InvalidArgumentException("Cannot read directory " . $directory);
            }
        }
        $include = [$directory];
        $exclude = [];
        if ($directory === ROOTDIR) {
            $cronDir = \App::getCronDirectory();
            if (!$this->directoryContains(ROOTDIR, $cronDir) && is_dir($cronDir)) {
                $include[] = $cronDir;
            }
            $exclude = [ROOTDIR . DIRECTORY_SEPARATOR . "tests", ROOTDIR . DIRECTORY_SEPARATOR . ".git", ROOTDIR . DIRECTORY_SEPARATOR . "vendor", \App::getTemplatesCacheDir()];
            $exclude = array_merge($exclude, \WHMCS\File\Provider\StorageProviderFactory::getLocalStoragePathsInUse());
        }
        return $this->getEncodedFilesFromDirectory($include, $exclude, $this->getFilterAllEncodedFiles());
    }

    protected function getFilterAllEncodedFiles()
    {
        return function (\WHMCS\Environment\Ioncube\Log\File $file) {
            $analyzer = $file->getAnalyzer();
            $dontCareAbout = [\WHMCS\Environment\Ioncube\EncodedFile::ENCODER_VERSION_NONE];
            if (in_array($analyzer->getEncoderVersion(), $dontCareAbout)) {
                return false;
            }
            return true;
        };
    }

    protected function getEncodedFilesFromDirectory($includeDirectories = [], $excludeDirectories = [], $filter = NULL)
    {
        $items = [];
        if (!is_callable($filter)) {
            $filter = NULL;
        }
        foreach ($includeDirectories as $dirPath) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                $fileExt = pathinfo($filePath, PATHINFO_EXTENSION);
                if ($fileExt === "php") {
                    $skip = false;
                    foreach ($excludeDirectories as $excludeDirectory) {
                        if ($this->directoryContains($excludeDirectory, $filePath)) {
                            $skip = true;
                            if (!$skip) {
                                $potentialLogEntry = $this->factoryLogFile($filePath);
                                $fp = $potentialLogEntry->getFileFingerprint();
                                if ($filter) {
                                    if (call_user_func($filter, $potentialLogEntry)) {
                                        $items[$fp] = $potentialLogEntry;
                                    }
                                } else {
                                    $items[$fp] = $potentialLogEntry;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $items;
    }

    private function directoryContains($parentDirectory, $directory)
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $directory = $directory . DIRECTORY_SEPARATOR;
        $parentDirectory = rtrim($parentDirectory, DIRECTORY_SEPARATOR);
        $parentDirectory = $parentDirectory . DIRECTORY_SEPARATOR;
        return strpos($directory, $parentDirectory) === 0;
    }
}
