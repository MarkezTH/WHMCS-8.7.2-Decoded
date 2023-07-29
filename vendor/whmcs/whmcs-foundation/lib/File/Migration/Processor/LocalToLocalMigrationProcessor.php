<?php

namespace WHMCS\File\Migration\Processor;

class LocalToLocalMigrationProcessor extends AbstractMigrationProcessor
{
    use LocalCapableProcessorTrait;
    private $sourcePath = NULL;
    private $targetPath = NULL;

    public function setFromProvider(\WHMCS\File\Provider\StorageProviderInterface $fromProvider)
    {
        if (!$fromProvider instanceof \WHMCS\File\Provider\LocalStorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid source storage provider");
        }
        $this->sourcePath = $fromProvider->getLocalPath();
        return $this;
    }

    public function setToProvider(\WHMCS\File\Provider\StorageProviderInterface $toProvider)
    {
        if (!$toProvider instanceof \WHMCS\File\Provider\LocalStorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid destination storage provider");
        }
        $this->targetPath = $toProvider->getLocalPath();
        return $this;
    }

    protected function doMigrate()
    {
        $numTotalObjects = count($this->objectsToMigrate);
        $objectIndex = 0;
        $totalDataSizeTriedThisRun = 0;
        $cutoffTime = time() + $this->getTimeLimit();
        $failedObjects = [];
        $failureReasons = [];
        $this->validateLocalPath($this->targetPath);
        while ($objectIndex < $numTotalObjects && $totalDataSizeTriedThisRun < $this->getDataSizeLimit()) {
            $objectPath = $this->objectsToMigrate[$objectIndex++];
            if (!$this->isObjectMigrated($objectPath)) {
                $fullSourceFilePath = $this->sourcePath . DIRECTORY_SEPARATOR . $objectPath;
                $fullTargetFilePath = $this->targetPath . DIRECTORY_SEPARATOR . $objectPath;
                $this->createDirectoriesForFile($fullTargetFilePath);
                $objectSize = filesize($fullSourceFilePath);
                if (copy($fullSourceFilePath, $fullTargetFilePath)) {
                    $this->addMigratedObject($objectPath);
                } else {
                    $failedObjects[] = $objectPath;
                    $failureReasons[] = "File copy failed: " . $objectPath;
                }
                $totalDataSizeTriedThisRun += $objectSize;
                if ($this->getDataSizeLimit() > $totalDataSizeTriedThisRun) {
                    if ($cutoffTime >= time()) {
                    }
                }
            }
        }
        if ($failedObjects) {
            $uniqueFailureReasons = implode(", ", array_unique($failureReasons));
            throw new \WHMCS\Exception\Storage\AssetMigrationException(sprintf("Failed to migrate %d objects. %s", count($failedObjects), $uniqueFailureReasons));
        }
    }
}
