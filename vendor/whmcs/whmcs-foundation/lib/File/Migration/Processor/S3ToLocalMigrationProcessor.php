<?php

namespace WHMCS\File\Migration\Processor;

class S3ToLocalMigrationProcessor extends AbstractMigrationProcessor
{
    use LocalCapableProcessorTrait;
    private $localPath = NULL;
    private $s3Client = NULL;
    private $s3Bucket = NULL;
    private $s3PathPrefix = NULL;
    const DOWNLOAD_CONCURRENCY = 5;
    const TEMP_LOCAL_FILE_EXT = ".migration";

    public function setFromProvider(\WHMCS\File\Provider\StorageProviderInterface $fromProvider)
    {
        if (!$fromProvider instanceof \WHMCS\File\Provider\S3StorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid destination storage provider");
        }
        $this->s3Client = $fromProvider->createS3Client();
        $this->s3Bucket = $fromProvider->getBucket();
        $this->s3PathPrefix = $fromProvider->getPathPrefix($this->assetType);
        return $this;
    }

    public function setToProvider(\WHMCS\File\Provider\StorageProviderInterface $toProvider)
    {
        if (!$toProvider instanceof \WHMCS\File\Provider\LocalStorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid source storage provider");
        }
        $this->localPath = $toProvider->getLocalPath();
        return $this;
    }

    protected function doMigrate()
    {
        $numTotalObjects = count($this->objectsToMigrate);
        $objectIndex = 0;
        $totalDataSizeThisRun = 0;
        $cutoffTime = time() + $this->getTimeLimit();
        $failedObjects = [];
        $failureReasons = [];
        $downloadClient = new \WHMCS\Http\Client\HttpClient(["allow_redirects" => true]);
        $this->validateLocalPath($this->localPath);
        while ($objectIndex < $numTotalObjects) {
            $promises = [];
            $objectsMigratedInBatch = [];
            while ($objectIndex < $numTotalObjects && count($promises) < static::DOWNLOAD_CONCURRENCY) {
                $objectPath = $this->objectsToMigrate[$objectIndex++];
                if (!$this->isObjectMigrated($objectPath)) {
                    $remoteObjectKey = $this->s3PathPrefix . "/" . $objectPath;
                    $fullLocalFilePath = $this->localPath . DIRECTORY_SEPARATOR . $objectPath;
                    $this->createDirectoriesForFile($fullLocalFilePath);
                    $command = $this->s3Client->getCommand("GetObject", ["Bucket" => $this->s3Bucket, "Key" => $remoteObjectKey]);
                    $presignedUrlExpiration = time() + 300;
                    $presignedRequest = $this->s3Client->createPresignedRequest($command, $presignedUrlExpiration);
                    $tempDownloadFilePath = $fullLocalFilePath . static::TEMP_LOCAL_FILE_EXT;
                    file_put_contents($tempDownloadFilePath, "");
                    $promises[] = $downloadClient->sendAsync($presignedRequest, ["sink" => $tempDownloadFilePath]);
                    $objectsMigratedInBatch[] = ["tempDownloadFilePath" => $tempDownloadFilePath, "actualTargetFilePath" => $fullLocalFilePath, "objectPath" => $objectPath];
                }
            }
            if (!empty($promises)) {
                $results = settle($promises)->wait();
                foreach ($results as $index => $promise) {
                    $fileData = $objectsMigratedInBatch[$index];
                    $tempDownloadFilePath = $fileData["tempDownloadFilePath"];
                    $actualTargetFilePath = $fileData["actualTargetFilePath"];
                    $objectPath = $fileData["objectPath"];
                    if ($promise["state"] === \GuzzleHttp\Promise\PromiseInterface::FULFILLED) {
                        $response = $promise["value"];
                        $responseCode = $response->getStatusCode();
                        if ($responseCode === 200) {
                            if (rename($tempDownloadFilePath, $actualTargetFilePath)) {
                                $totalDataSizeThisRun += filesize($actualTargetFilePath);
                                $this->addMigratedObject($objectPath);
                            } else {
                                $failedObjects[] = $actualTargetFilePath;
                                $failureReasons[] = "The following file could not be moved during migration: " . $tempDownloadFilePath . " to " . $actualTargetFilePath;
                            }
                        } else {
                            $failedObjects[] = $actualTargetFilePath;
                            $failureReason = $response->getReasonPhrase();
                            if (300 <= $responseCode && $responseCode < 400) {
                                $failureReason .= ". Check that your S3 bucket name is correct";
                            }
                            $failureReasons[] = $failureReason;
                        }
                    } else {
                        $reason = $promise["reason"];
                        $response = $reason->getResponse();
                        $responseCode = $response->getStatusCode();
                        if (file_exists($tempDownloadFilePath)) {
                            unlink($tempDownloadFilePath);
                        }
                        $failedObjects[] = $actualTargetFilePath;
                        $failureReason = "Response code: " . $responseCode;
                        if ($responseCode === 403) {
                            $failureReason .= ". Check your S3 key and secret and make sure your server time is correct.";
                        }
                        $failureReasons[] = $failureReason;
                    }
                }
            }
            if ($this->getDataSizeLimit() > $totalDataSizeThisRun) {
                if ($cutoffTime >= time()) {
                }
            }
        }
        if (!empty($failedObjects)) {
            $uniqueFailureReasons = implode(", ", array_unique($failureReasons));
            throw new \WHMCS\Exception\Storage\AssetMigrationException(sprintf("Failed to migrate %d objects. %s", count($failedObjects), $uniqueFailureReasons));
        }
    }
}
