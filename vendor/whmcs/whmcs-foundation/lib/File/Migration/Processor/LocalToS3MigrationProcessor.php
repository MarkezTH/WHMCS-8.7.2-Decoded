<?php

namespace WHMCS\File\Migration\Processor;

class LocalToS3MigrationProcessor extends AbstractMigrationProcessor
{
    private $localPath = NULL;
    private $s3Client = NULL;
    protected $s3Bucket = NULL;
    private $s3PathPrefix = NULL;
    const UPLOAD_CONCURRENCY = 5;

    public function setFromProvider(\WHMCS\File\Provider\StorageProviderInterface $fromProvider)
    {
        if (!$fromProvider instanceof \WHMCS\File\Provider\LocalStorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid source storage provider");
        }
        $this->localPath = $fromProvider->getLocalPath();
        return $this;
    }

    public function setToProvider(\WHMCS\File\Provider\StorageProviderInterface $toProvider)
    {
        if (!$toProvider instanceof \WHMCS\File\Provider\S3StorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid destination storage provider");
        }
        $this->s3Client = $toProvider->createS3Client();
        $this->s3Bucket = $toProvider->getBucket();
        $this->s3PathPrefix = $toProvider->getPathPrefix($this->assetType);
        return $this;
    }

    protected function getObjectKeyFromAwsResult(\Aws\Result $awsResult)
    {
        $remoteObjectKey = NULL;
        if ($awsResult->hasKey("Key")) {
            $remoteObjectKey = $awsResult->get("Key");
        } else {
            if ($awsResult->hasKey("ObjectURL")) {
                $urlParts = parse_url($awsResult->get("ObjectURL"));
                if (!$urlParts) {
                    return NULL;
                }
                if (preg_match("/^([^\\.]+\\.)?s3\\.([^\\.]+\\.)?amazonaws\\.com\$/", $urlParts["host"], $matches)) {
                    $bucketName = isset($matches[1]) ? rtrim($matches[1], ".") : NULL;
                    $urlPath = ltrim($urlParts["path"], "/");
                    if ($bucketName) {
                        if ($bucketName === $this->s3Bucket) {
                            $remoteObjectKey = $urlPath;
                        }
                    } else {
                        if (strpos($urlPath, $this->s3Bucket . "/") === 0) {
                            $remoteObjectKey = substr($urlPath, strlen($this->s3Bucket) + 1);
                        }
                    }
                    if ($remoteObjectKey) {
                        $remoteObjectKey = urldecode($remoteObjectKey);
                    }
                }
            }
        }
        if (!$remoteObjectKey) {
            return NULL;
        }
        return sha1($remoteObjectKey);
    }

    protected function doMigrate()
    {
        $numTotalObjects = count($this->objectsToMigrate);
        $objectIndex = 0;
        $totalDataSizeTriedThisRun = 0;
        $cutoffTime = time() + $this->getTimeLimit();
        $failedObjects = [];
        $failureReasons = [];
        while ($objectIndex < $numTotalObjects) {
            $promises = [];
            $objectsMigratedInBatch = [];
            while ($objectIndex < $numTotalObjects && count($promises) < static::UPLOAD_CONCURRENCY && $totalDataSizeTriedThisRun < $this->getDataSizeLimit()) {
                $objectPath = $this->objectsToMigrate[$objectIndex++];
                if (!$this->isObjectMigrated($objectPath)) {
                    $fullLocalFilePath = $this->localPath . DIRECTORY_SEPARATOR . $objectPath;
                    $remoteObjectKey = $this->s3PathPrefix . "/" . $objectPath;
                    $objectsMigratedInBatch[sha1($remoteObjectKey)] = $objectPath;
                    $objectSize = filesize($fullLocalFilePath);
                    if (is_int($objectSize) && 0 < $objectSize) {
                        $uploader = new \Aws\S3\MultipartUploader($this->s3Client, $fullLocalFilePath, ["bucket" => $this->s3Bucket, "key" => $remoteObjectKey]);
                        $promises[] = $uploader->promise();
                    } else {
                        if ($objectSize === 0) {
                            $command = $this->s3Client->getCommand("PutObject", ["Bucket" => $this->s3Bucket, "Key" => $remoteObjectKey]);
                            $promises[] = $this->s3Client->executeAsync($command);
                        } else {
                            $failedObjects[] = $objectPath;
                            $failureReasons[] = "File could not be accessed during migration: " . $fullLocalFilePath;
                        }
                    }
                    if (is_int($objectSize)) {
                        $totalDataSizeTriedThisRun += $objectSize;
                    }
                }
            }
            if (!empty($promises)) {
                $results = settle($promises)->wait();
                foreach ($results as $promise) {
                    if ($promise["state"] === \GuzzleHttp\Promise\PromiseInterface::FULFILLED) {
                        $awsResult = $promise["value"];
                        $remoteObjectKey = $this->getObjectKeyFromAwsResult($awsResult);
                        if ($remoteObjectKey && array_key_exists($remoteObjectKey, $objectsMigratedInBatch)) {
                            $objectPath = $objectsMigratedInBatch[$remoteObjectKey];
                            $this->addMigratedObject($objectPath);
                        } else {
                            $unrecognizedObjectInfo = json_encode($awsResult->toArray());
                            $failedObjects[] = sha1($unrecognizedObjectInfo);
                            $failureReasons[] = "An object migration resulted in an unrecognized response: " . $unrecognizedObjectInfo;
                        }
                    } else {
                        $awsResult = $promise["reason"];
                        if ($awsResult instanceof \Aws\S3\Exception\S3MultipartUploadException) {
                            $objectPath = $awsResult->getKey();
                            $failedObjects[] = $objectPath;
                            $previousException = $awsResult->getPrevious();
                            if ($previousException instanceof \Aws\Exception\AwsException) {
                                $failureReasons[] = $previousException->getAwsErrorMessage() ?: $previousException->getMessage();
                            }
                        }
                    }
                }
            }
            if ($this->getDataSizeLimit() > $totalDataSizeTriedThisRun) {
                if ($cutoffTime >= time()) {
                }
            }
        }
        if ($failedObjects) {
            $uniqueFailureReasons = implode(", ", array_unique($failureReasons));
            throw new \WHMCS\Exception\Storage\AssetMigrationException(sprintf("Failed to migrate %d objects. %s", count($failedObjects), $uniqueFailureReasons));
        }
    }
}
