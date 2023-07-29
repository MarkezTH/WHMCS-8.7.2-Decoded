<?php

namespace WHMCS\Log;

class ErrorLog extends \WHMCS\Model\AbstractModel
{
    protected $table = "tblerrorlog";
    private static $callDepth = 0;
    const SEVERITY_NOTICE = "notice";
    const SEVERITY_WARNING = "warning";
    const SEVERITY_ERROR = "error";
    const SEVERITY_MAP = NULL;
    const IGNORED_ERROR_LEVELS = NULL;
    const IGNORED_EXCEPTIONS = ["WHMCS\\Exception\\ProgramExit"];
    const IGNORED_MESSAGES = ["/DateTime::modify\\(\\):(.*)Unexpected character/i", "/DateTime::modify\\(\\):(.*)Failed to parse time string/i", "/fsockopen\\(\\): Unable to connect to/i", "/unlink\\(.*__tcpdf_/", "/Warning: hex2bin\\(\\):/"];
    const IGNORED_PHP8_MESSAGES = ["/^Undefined array key/", "/^Undefined variable/", "/^Undefined global variable/", "/^Trying to access array offset on value of type.*/", "/^Attempt to read property \\\".*\\\" on.*/", "/^Uninitialized string offset/", "/^Constant.* already defined/"];

    public function setFilenameAttribute($filename)
    {
        if (is_string($filename)) {
            if (strpos($filename, ROOTDIR) === 0) {
                $filename = substr($filename, strlen(ROOTDIR) + 1);
            }
            if (255 < strlen($filename)) {
                $filename = "..." . substr($filename, -252, 252);
            }
        }
        $this->attributes["filename"] = $filename;
    }

    public function setMessageAttribute($message)
    {
        if (255 < strlen($message)) {
            $message = substr($message, 0, 255);
        }
        $this->attributes["message"] = $message;
    }

    private static function isMessageIgnored($message)
    {
        foreach (static::IGNORED_MESSAGES as $ignoredPattern) {
            if (preg_match($ignoredPattern, $message)) {
                return true;
            }
        }
        if (!class_exists("DI") || empty(\DI::make("config")->disable_php8_warning_suppression)) {
            foreach (static::IGNORED_PHP8_MESSAGES as $ignoredPattern) {
                if (preg_match($ignoredPattern, $message)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected static function isLevelSilenced($level)
    {
        return in_array($level, static::IGNORED_ERROR_LEVELS);
    }

    protected static function isExceptionSilenced($throwable)
    {
        return in_array(get_class($throwable), static::IGNORED_EXCEPTIONS);
    }

    public static function logException(\Throwable $error)
    {
        if (0 < static::$callDepth || static::isExceptionSilenced($error) || static::isMessageIgnored($error->getMessage())) {
            return NULL;
        }
        try {
            static::$callDepth++;
            $error instanceof \Whoops\Exception\ErrorException && static::isLevelSilenced($error->getCode()) ? exit : $log;
        } catch (\Throwable $e) {
        } finally {
            static::$callDepth--;
        }
    }

    public static function logError($level, $message, $file = NULL, $line = NULL, $context = NULL)
    {
        if (0 < static::$callDepth || static::isLevelSilenced($level) || static::isMessageIgnored($message)) {
            return NULL;
        }
        $log = NULL;
        try {
            static::$callDepth++;
            $log = new static();
            $log->severity = static::SEVERITY_MAP[$level] ?? static::SEVERITY_ERROR;
            $log->message = $message;
            $log->filename = $file;
            $log->line = !is_null($line) && is_int($line) ? $line : NULL;
            $log->details = $context;
            $log->save();
        } catch (\Throwable $e) {
        } finally {
            static::$callDepth--;
        }
    }

    public static function prune($olderThanDays = 7, $maxRows = 10000)
    {
        try {
            $config = \DI::make("config");
            if (is_numeric($config->internalErrorLogMaxRows)) {
                $maxRows = $config->internalErrorLogMaxRows;
            }
            if (is_numeric($config->internalErrorLogMaxDays)) {
                $olderThanDays = $config->internalErrorLogMaxDays;
            }
            if (0 < $maxRows && $maxRows < static::query()->count()) {
                $deleteIdLessThan = static::orderBy("id", "desc")->limit(1)->skip($maxRows)->value("id");
                static::where("id", "<=", $deleteIdLessThan)->delete();
            }
            if (0 < $olderThanDays) {
                static::where("created_at", "<", \WHMCS\Carbon::now()->subDays($olderThanDays))->delete();
            }
        } catch (\Throwable $e) {
        }
    }

    protected static function errorsAreExceptions()
    {
        if (method_exists("WHMCS\\Utility\\ErrorManagement", "errorsAsExceptions")) {
            return \WHMCS\Utility\ErrorManagement::errorsAsExceptions(\DI::make("config"));
        }
        return false;
    }
}
