<?php

namespace WHMCS\File\Migration\Processor;

class S3ToS3MigrationProcessor extends AbstractMigrationProcessor
{
    private $s3FromClient = NULL;
    private $s3ToClient = NULL;
    private $s3FromBucket = NULL;
    private $s3ToBucket = NULL;
    private $s3PathPrefix = NULL;
    const MIGRATION_CONCURRENCY = 10;

    public function setFromProvider(\WHMCS\File\Provider\StorageProviderInterface $fromProvider)
    {
        if (!$fromProvider instanceof \WHMCS\File\Provider\S3StorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid source storage provider");
        }
        $this->s3FromClient = $fromProvider->createS3Client();
        $this->s3FromBucket = $fromProvider->getBucket();
        $this->s3PathPrefix = $fromProvider->getPathPrefix($this->assetType);
        return $this;
    }

    public function setToProvider(\WHMCS\File\Provider\StorageProviderInterface $toProvider)
    {
        if (!$toProvider instanceof \WHMCS\File\Provider\S3StorageProvider) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Invalid destination storage provider");
        }
        $this->s3ToClient = $toProvider->createS3Client();
        $this->s3ToBucket = $toProvider->getBucket();
        return $this;
    }

    private function getS3ClientToAccessBothBuckets()
    {
        foreach ([$this->s3FromClient, $this->s3ToClient] as $client) {
            try {
                foreach ([$this->s3FromBucket, $this->s3ToBucket] as $bucket) {
                    $testObjectKey = \Illuminate\Support\Str::random(32);
                    $testObjectBody = \Illuminate\Support\Str::random(32);
                    $client->putObject(["Bucket" => $bucket, "Key" => $testObjectKey, "Body" => $testObjectBody]);
                    $listedObjects = $client->listObjects(["Bucket" => $bucket, "Prefix" => $testObjectKey])->get("Contents");
                    if (!is_array($listedObjects) || empty($listedObjects) || (int) $listedObjects[0]["Size"] !== strlen($testObjectBody)) {
                        throw new \WHMCS\Exception\Storage\StorageException("Failed to list a test object");
                    }
                    $retrievedObjectStream = $client->getObject(["Bucket" => $bucket, "Key" => $testObjectKey])->get("Body");
                    if ($retrievedObjectStream->getContents() !== $testObjectBody) {
                        throw new \WHMCS\Exception\Storage\StorageException("GetObject returned unexpected content");
                    }
                    $client->deleteObject(["Bucket" => $bucket, "Key" => $testObjectKey]);
                    $listedObjects = $client->listObjects(["Bucket" => $bucket, "Prefix" => $testObjectKey])->get("Contents");
                    if (!empty($listedObjects)) {
                        throw new \WHMCS\Exception\Storage\StorageException("Failed to assert deletion of a test object");
                    }
                }
                return $client;
            } catch (\Exception $e) {
            }
        }
        return NULL;
    }

    protected function doMigrate()
    {
        $client = $this->getS3ClientToAccessBothBuckets();
        if (!$client) {
            throw new \WHMCS\Exception\Storage\AssetMigrationException("Either source or destination S3 configuration must be permitted to access both source and destination buckets in order to perform S3 to S3 migration.");
        }
        $numTotalObjects = count($this->objectsToMigrate);
        $objectIndex = 0;
        $cutoffTime = time() + $this->getTimeLimit();
        $failedObjects = [];
        $failureReasons = [];
        while ($objectIndex < $numTotalObjects) {
            $commands = [];
            $objectsMigratedInBatch = [];
            while ($objectIndex < $numTotalObjects && count($commands) < static::MIGRATION_CONCURRENCY) {
                $objectPath = $this->objectsToMigrate[$objectIndex++];
                if (!$this->isObjectMigrated($objectPath)) {
                    $sourceObjectKey = $this->s3PathPrefix . "/" . $objectPath;
                    $targetObjectKey = $sourceObjectKey;
                    $objectsMigratedInBatch[] = $objectPath;
                    $commands[] = $this->s3FromClient->getCommand("CopyObject", ["Bucket" => $this->s3ToBucket, "CopySource" => $this->s3FromBucket . "/" . $sourceObjectKey, "Key" => $targetObjectKey]);
                }
            }
            if (!empty($commands)) {
                $results = \Aws\CommandPool::batch($client, $commands);
                $index = 0;
                foreach ($results as $result) {
                    $objectPath = $objectsMigratedInBatch[$index++];
                    if ($result instanceof \Aws\ResultInterface) {
                        $this->addMigratedObject($objectPath);
                    } else {
                        if ($result instanceof \Aws\Exception\AwsException) {
                            $failedObjects[] = $objectPath;
                            $failureReasons[] = $result->getAwsErrorMessage() . " (key: " . $objectPath . "). ";
                        }
                    }
                }
            }
            if ($cutoffTime >= time()) {
            }
        }
        if ($failedObjects) {
            $uniqueFailureReasons = implode(", ", array_unique($failureReasons));
            throw new \WHMCS\Exception\Storage\AssetMigrationException(sprintf("Failed to migrate %d objects. %s", count($failedObjects), $uniqueFailureReasons));
        }
    }
}
