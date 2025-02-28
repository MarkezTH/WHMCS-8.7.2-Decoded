<?php

namespace WHMCS\Utility\Error;

class Run implements \Whoops\RunInterface
{
    private $isRegistered = NULL;
    private $allowQuit = true;
    private $sendOutput = true;
    private $sendExitCode = 1;
    private $sendHttpCode = 500;
    private $handlerStack = [];
    private $silencedPatterns = [];
    private $system = NULL;
    private $canThrowExceptions = true;

    public function __construct(\Whoops\Util\SystemFacade $system = NULL)
    {
        $this->system = $system ?: new \Whoops\Util\SystemFacade();
    }

    public function pushHandler($handler)
    {
        if (is_callable($handler)) {
            $handler = new \Whoops\Handler\CallbackHandler($handler);
        }
        if (!$handler instanceof \Whoops\Handler\HandlerInterface) {
            throw new \InvalidArgumentException("Argument to WHMCS\\Utility\\Error\\Run::pushHandler must be a callable, or instance of Whoops\\Handler\\HandlerInterface");
        }
        $this->handlerStack[] = $handler;
        return $this;
    }

    public function popHandler()
    {
        return array_pop($this->handlerStack);
    }

    public function getHandlers()
    {
        return $this->handlerStack;
    }

    public function clearHandlers()
    {
        $this->handlerStack = [];
        return $this;
    }

    private function getInspector($exception)
    {
        return new \Whoops\Exception\Inspector($exception);
    }

    public function register()
    {
        if (!$this->isRegistered) {
            class_exists("\\Whoops\\Exception\\ErrorException");
            class_exists("\\Whoops\\Exception\\FrameCollection");
            class_exists("\\Whoops\\Exception\\Frame");
            class_exists("\\Whoops\\Exception\\Inspector");
            $this->system->setErrorHandler([$this, self::ERROR_HANDLER]);
            $this->system->setExceptionHandler([$this, self::EXCEPTION_HANDLER]);
            $this->system->registerShutdownFunction([$this, self::SHUTDOWN_HANDLER]);
            $this->isRegistered = true;
        }
        return $this;
    }

    public function unregister()
    {
        if ($this->isRegistered) {
            $this->system->restoreExceptionHandler();
            $this->system->restoreErrorHandler();
            $this->isRegistered = false;
        }
        return $this;
    }

    public function allowQuit($exit = NULL)
    {
        if (func_num_args() == 0) {
            return $this->allowQuit;
        }
        return $this->allowQuit = (bool) $exit;
    }

    public function silenceErrorsInPaths($patterns, $levels = 10240)
    {
        $this->silencedPatterns = array_merge($this->silencedPatterns, array_map(function ($pattern) use($levels) {
            return ["pattern" => $pattern, "levels" => $levels];
        }, (array) $patterns));
        return $this;
    }

    public function getSilenceErrorsInPaths()
    {
        return $this->silencedPatterns;
    }

    public function sendHttpCode($code = NULL)
    {
        if (func_num_args() == 0) {
            return $this->sendHttpCode;
        }
        if (!$code) {
            return $this->sendHttpCode = false;
        }
        if ($code === true) {
            $code = 500;
        }
        if ($code < 400 || 600 <= $code) {
            throw new \InvalidArgumentException("Invalid status code '" . $code . "', must be 4xx or 5xx");
        }
        return $this->sendHttpCode = $code;
    }

    public function writeToOutput($send = NULL)
    {
        if (func_num_args() == 0) {
            return $this->sendOutput;
        }
        return $this->sendOutput = (bool) $send;
    }

    public function handleException($exception)
    {
        \WHMCS\Log\ErrorLog::logException($exception);
        $inspector = $this->getInspector($exception);
        $this->system->startOutputBuffering();
        $handlerResponse = NULL;
        $handlerContentType = NULL;
        foreach (array_reverse($this->handlerStack) as $handler) {
            $handler->setRun($this);
            $handler->setInspector($inspector);
            $handler->setException($exception);
            $handlerResponse = $handler->handle();
            $handlerContentType = method_exists($handler, "contentType") ? $handler->contentType() : NULL;
            if (in_array($handlerResponse, [\Whoops\Handler\Handler::LAST_HANDLER, \Whoops\Handler\Handler::QUIT])) {
                $willQuit = $handlerResponse == \Whoops\Handler\Handler::QUIT && $this->allowQuit();
                $output = $this->system->cleanOutputBuffer();
                if ($this->writeToOutput()) {
                    if ($willQuit) {
                        while (0 < $this->system->getOutputBufferLevel()) {
                            $this->system->endOutputBuffering();
                        }
                        if (\Whoops\Util\Misc::canSendHeaders() && $handlerContentType) {
                            header("Content-Type: " . $handlerContentType);
                        }
                    }
                    $this->writeToOutputNow($output);
                }
                if ($willQuit) {
                    $this->system->flushOutputBuffer();
                    $this->system->stopExecution(1);
                }
                return $output;
            }
        }
    }

    public function handleError($level, $message, $file = NULL, $line = NULL)
    {
        $convertedException = NULL;
        if ($level & $this->system->getErrorReportingLevel()) {
            foreach ($this->silencedPatterns as $entry) {
                $pathMatches = (bool) preg_match($entry["pattern"], $file);
                $levelMatches = $level & $entry["levels"];
                if ($pathMatches && $levelMatches) {
                    return true;
                }
            }
            $convertedException = new \Whoops\Exception\ErrorException($message, $level, $level, $file, $line);
            $this->handleException($convertedException);
        } else {
            \WHMCS\Log\ErrorLog::logError($level, $message, $file, $line);
        }
        if ($convertedException) {
            return true;
        }
        return false;
    }

    public function handleShutdown()
    {
        $this->canThrowExceptions = false;
        $error = $this->system->getLastError();
        if ($error && \Whoops\Util\Misc::isLevelFatal($error["type"])) {
            $this->handleError($error["type"], $error["message"], $error["file"], $error["line"]);
        }
    }

    private function writeToOutputNow($output)
    {
        if ($this->sendHttpCode() && \Whoops\Util\Misc::canSendHeaders()) {
            $this->system->setHttpResponseCode($this->sendHttpCode());
        }
        echo $output;
        return $this;
    }

    public function sendExitCode($code)
    {
        if (func_num_args() == 0) {
            return $this->sendExitCode;
        }
        if ($code < 0 || 255 <= $code) {
            throw new \InvalidArgumentException("Invalid status code '" . $code . "', must be between 0 and 254");
        }
        return $this->sendExitCode = (int) $code;
    }
}
