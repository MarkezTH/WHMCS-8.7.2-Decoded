<?php

namespace WHMCS\Exception\Handler;

class CriticalHtmlHandler extends \Whoops\Handler\Handler
{
    use ExceptionLoggingTrait;

    protected function getErrorOutputForCli()
    {
        $e = $this->getException();
        $output = sprintf("%s: %s in %s:%s", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        $output .= "\n\n";
        $output .= $e->getTraceAsString();
        return $output;
    }

    protected function getHtmlErrorOutput()
    {
        $errorPage = new \WHMCS\View\HtmlErrorPage();
        $knownIssues = $stackTrace = $adminHelp = "";
        if (\WHMCS\Utility\ErrorManagement::isDisplayErrorCurrentlyVisible()) {
            $stackTrace = \WHMCS\View\HtmlErrorPage::getHtmlStackTrace($this->getException());
        }
        if (\WHMCS\Admin::getId()) {
            $knownIssues = \WHMCS\View\HtmlErrorPage::getHtmlAnyEnvironmentIssues();
            $adminHelp = \WHMCS\View\HtmlErrorPage::getHtmlAdminHelp((bool) $stackTrace);
        }
        if ($adminHelp || $stackTrace || $knownIssues) {
            $errorPage->body .= $knownIssues;
            if ($stackTrace) {
                $adminHelp .= "<br/>" . $stackTrace;
            }
            if ($adminHelp) {
                $errorPage->body .= "<p class=\"debug\">" . $adminHelp . "</p>";
            }
        }
        return $errorPage->getHtmlErrorPage();
    }

    public function handle()
    {
        if ($this->isActuallyError() && !$this->isActuallyFatalError()) {
            return \Whoops\Handler\Handler::LAST_HANDLER;
        }
        $this->log($this->getException());
        if (\WHMCS\Environment\Php::isCli()) {
            $output = $this->getErrorOutputForCli();
        } else {
            if (!headers_sent()) {
                header("HTTP/1.1 500 Internal Server Error");
            }
            $output = $this->getHtmlErrorOutput();
        }
        echo $output;
        return \Whoops\Handler\Handler::QUIT;
    }

    protected function isActuallyError()
    {
        $e = $this->getException();
        if ($e && ($e instanceof \ErrorException || $e instanceof \Error)) {
            return true;
        }
        return false;
    }

    protected function isActuallyFatalError()
    {
        $e = $this->getException();
        if ($e) {
            if ($e instanceof \Error) {
                return true;
            }
            if ($e instanceof \ErrorException && (\Whoops\Util\Misc::isLevelFatal($e->getSeverity()) || class_exists("DI") && \WHMCS\Utility\ErrorManagement::errorsAsExceptions(\DI::make("config")))) {
                return true;
            }
        }
        return false;
    }
}
