<?php

namespace WHMCS\Scheduling\Jobs;

class Queue extends \WHMCS\Model\AbstractModel implements \WHMCS\Model\Contracts\SchemaVersionAware
{
    use \WHMCS\Model\Traits\SchemaVersionTrait;
    protected $table = "tbljobs_queue";
    protected $casts = ["async" => "boolean"];
    const SCHEMA_V840BETA1 = 1;

    public function createTable($drop = false)
    {
        $schemaBuilder = \WHMCS\Database\Capsule::schema();
        if ($drop) {
            $schemaBuilder->dropIfExists($this->getTable());
        }
        if (!$schemaBuilder->hasTable($this->getTable())) {
            $schemaBuilder->create($this->getTable(), function ($table) {
                $table->increments("id");
                $table->string("name", 255)->default("");
                $table->string("class_name", 255)->default("");
                $table->string("method_name", 255)->default("");
                $table->text("input_parameters");
                $table->timestamp("available_at");
                $table->string("digest_hash", 255)->default("");
                $table->boolean("async")->default(0);
                $table->timestamps();
            });
        }
    }

    public static function add($name, $class, $method, $inputParams = [], $delay = 0, $replaceExisting = false)
    {
        $queue = new self();
        $job = $queue->factoryJobInstance($name, $class, $method, $inputParams, $delay);
        if (!$job) {
            return NULL;
        }
        return $queue->addJob($job, false, $replaceExisting);
    }

    public static function addAsync($name, $class, $method, $inputParams = [], $delay = 0, $replaceExisting = false)
    {
        $queue = new self();
        $job = $queue->factoryJobInstance($name, $class, $method, $inputParams, $delay);
        if (!$job) {
            return NULL;
        }
        return $queue->addJob($job, true, $replaceExisting);
    }

    public static function addOrUpdate($name, $class, $method, $inputParams = 0, $delay = null)
    {
        return self::add($name, $class, $method, $inputParams, $delay, true);
    }

    public static function remove($name)
    {
        self::where("name", $name)->delete();
    }

    public static function exists($name)
    {
        return !is_null(self::where("name", $name)->first());
    }

    public function encryptArguments($data)
    {
        $key = sha1(\DI::make("config")->cc_encryption_hash);
        $encrypted = $this->aesEncryptValue(json_encode($data), $key);
        return $encrypted;
    }

    public function decryptArguments($data)
    {
        $key = sha1(\DI::make("config")->cc_encryption_hash);
        $decrypted = $this->aesDecryptValue($data, $key);
        $data = json_decode($decrypted, true);
        if (!is_array($data)) {
            $data = [];
        }
        return $data;
    }

    public function createDigestHash(\WHMCS\Scheduling\Contract\JobInterface $job)
    {
        $signatureKey = hash("sha256", \DI::make("config")->cc_encryption_hash, true);
        $jobSignatureBase = $job->jobClassName() . $job->jobMethodName() . safe_serialize($job->jobMethodArguments()) . $job->jobAvailableAt()->toDateTimeString();
        return hash_hmac("sha256", $jobSignatureBase, $signatureKey);
    }

    public function verifyDigestHash(\WHMCS\Scheduling\Contract\JobInterface $job)
    {
        $storedHash = $job->jobDigestHash();
        $verifyHash = $this->createDigestHash($job);
        if (empty($storedHash) || empty($verifyHash)) {
            return false;
        }
        return hash_equals($verifyHash, $storedHash);
    }

    protected function factoryJobFromClassName($className)
    {
        if (class_exists($className)) {
            try {
                $instance = NULL;
                if (method_exists($className, "jobFactory")) {
                    $instance = $className::jobFactory();
                } else {
                    $instance = new $className();
                }
                if ($instance instanceof \WHMCS\Scheduling\Contract\JobInterface) {
                    return $instance;
                }
            } catch (\Exception $e) {
                return NULL;
            }
        }
    }

    protected function factoryJobInstance($name, $class, $method, $inputParams, $delay = 0)
    {
        $job = $this->factoryJobFromClassName($class);
        if (!$job) {
            return NULL;
        }
        $job->jobName($name);
        $job->jobMethodName($method);
        $job->jobMethodArguments($inputParams);
        if ($delay) {
            $availableAt = \WHMCS\Carbon::now()->addMinutes($delay);
        } else {
            $availableAt = \WHMCS\Carbon::now();
        }
        $job->jobAvailableAt($availableAt);
        return $job;
    }

    public function executeJob()
    {
        $this->refresh();
        if ($this->started_at) {
            throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("This job is already in progress");
        }
        $className = $this->class_name;
        $methodName = $this->method_name;
        $encryptedArguments = $this->input_parameters;
        $storedHash = $this->digest_hash;
        $job = $this->factoryJobFromClassName($className);
        if (!$job) {
            throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("An empty job or an unsuitable class queued for execution");
        }
        if (empty($methodName) || !method_exists($job, $methodName)) {
            throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("Method does not exist in a job queued for execution");
        }
        $job->jobName($this->name);
        $job->jobMethodName($methodName);
        $methodArguments = $this->decryptArguments($encryptedArguments);
        $job->jobMethodArguments($methodArguments);
        $job->jobAvailableAt(new \WHMCS\Carbon($this->available_at));
        $job->jobDigestHash($storedHash);
        $this->validateJob($job);
        if (!$this->verifyDigestHash($job)) {
            throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("Job signature validation failed");
        }
        $this->started_at = \WHMCS\Carbon::now();
        if ($this->exists) {
            $this->save();
        }
        try {
            $job->{$methodName}(...$methodArguments);
        } finally {
            $this->started_at = NULL;
            if ($this->exists) {
                $this->save();
            }
        }
    }

    public function validateJob(\WHMCS\Scheduling\Contract\JobInterface $job)
    {
        $msg = "";
        if (!$job->jobName()) {
            $msg = "Job missing \"name\" attribute";
        } else {
            if (!$job->jobClassName()) {
                $msg = "Job missing \"ClassName\" attribute";
            } else {
                if (!$job->jobMethodName()) {
                    $msg = "Job missing \"MethodName\" attribute";
                } else {
                    if (!$job->jobAvailableAt()) {
                        $msg = "Job missing \"AvailableAt\" attribute";
                    } else {
                        if (!$job->jobDigestHash()) {
                            $msg = "Job missing \"DigestHash\" attribute";
                        }
                    }
                }
            }
        }
        if ($msg) {
            throw new \WHMCS\Exception\Model\EmptyValue($msg);
        }
        return true;
    }

    public function addJob($job, $isAsync = false, $replaceExisting)
    {
        try {
            $queue = new static();
            if (!$job->jobDigestHash()) {
                $job->jobDigestHash($queue->createDigestHash($job));
            }
            $queue->validateJob($job);
            $queue->name = $job->jobName();
            $queue->class_name = $job->jobClassName();
            $queue->method_name = $job->jobMethodName();
            $queue->input_parameters = $queue->encryptArguments($job->jobMethodArguments());
            $queue->available_at = $job->jobAvailableAt()->toDateTimeString();
            $queue->digest_hash = $job->jobDigestHash();
            if (self::isAtLeastSchemaVersion(self::SCHEMA_V840BETA1)) {
                $queue->async = $isAsync;
            }
            if ($replaceExisting) {
                self::where("name", $job->jobName())->delete();
            }
            $queue->save();
            return $queue;
        } catch (\Exception $e) {
            logActivity("Exception thrown when adding job to queue - " . $e->getMessage());
        }
    }

    public function scopeAvailableOn(\Illuminate\Database\Eloquent\Builder $query, \WHMCS\Carbon $timestamp)
    {
        return $query->where("available_at", "<=", $timestamp->toDateTimeString());
    }

    public function scopeIsAsync(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("async", "!=", 0);
    }

    public function scopeIsNotAsync(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->where("async", "=", 0);
    }

    public function scopeNotStarted(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->whereNull("started_at");
    }

    private function getAuthHash()
    {
        if (is_null($this->digest_hash)) {
            throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("Invalid state, cannot calculate auth hash without digest hash");
        }
        return hash("sha256", $this->digest_hash);
    }

    public function validateAuthHash($authHash)
    {
        return hash_equals($this->getAuthHash(), $authHash);
    }

    public function runAsync()
    {
        if (!$this->exists) {
            throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("Job must be saved first");
        }
        if (!$this->async) {
            throw new \WHMCS\Exception\Scheduling\Jobs\QueueException("Job is not marked as async");
        }
        if (\WHMCS\Environment\WebServer::isIis()) {
            $this->executeJob();
        } else {
            $guzzle = new \GuzzleHttp\Client([\GuzzleHttp\RequestOptions::TIMEOUT => 2]);
            $requestOptions = [\GuzzleHttp\RequestOptions::FORM_PARAMS => ["authHash" => $this->getAuthHash()], \GuzzleHttp\RequestOptions::HTTP_ERRORS => false, \GuzzleHttp\RequestOptions::TIMEOUT => 2];
            $host = parse_url(\App::getSystemURL(), PHP_URL_HOST);
            if ($host) {
                $cookies = [session_name() => session_id()];
                if (in_array($host, ["whmcs.test", "whmcs-dev.test"], true) && !empty($_COOKIE["XDEBUG_SESSION"])) {
                    $cookies["XDEBUG_SESSION"] = $_COOKIE["XDEBUG_SESSION"];
                }
                $requestOptions[\GuzzleHttp\RequestOptions::COOKIES] = \GuzzleHttp\Cookie\CookieJar::fromArray($cookies, $host);
            }
            $response = NULL;
            try {
                $response = $guzzle->post(fqdnRoutePath("run-async-job", $this->id), $requestOptions);
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
                foreach (["curl error 28", "operation timed out"] as $ignoredMessage) {
                    if (stripos($errorMessage, $ignoredMessage) !== false) {
                        $errorMessage = NULL;
                        if (!is_null($errorMessage)) {
                            logActivity(sprintf("Async job ID %d failed to start: %s", $this->id, $errorMessage));
                        }
                    }
                }
            }
            if ($response) {
                $responseData = json_decode($response->getBody()->getContents(), true) ?? [];
                if ($response->getStatusCode() !== 200) {
                    logActivity(($responseData["message"] ?? "An error occurred while starting an async job") . ", job: " . $this->name);
                }
            }
        }
    }

    public function prune(int $maxDays = 14)
    {
        static::isAsync()->where("created_at", "<", \WHMCS\Carbon::now()->subDays($maxDays))->delete();
    }

    public static function latestSchemaVersion()
    {
        return self::SCHEMA_V840BETA1;
    }
}
