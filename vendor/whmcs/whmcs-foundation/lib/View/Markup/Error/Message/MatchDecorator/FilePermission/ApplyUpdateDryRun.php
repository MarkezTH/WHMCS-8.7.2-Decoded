<?php

namespace WHMCS\View\Markup\Error\Message\MatchDecorator\FilePermission;

class ApplyUpdateDryRun extends \WHMCS\View\Markup\Error\Message\MatchDecorator\AbstractMatchDecorator
{
    const MESSAGE = "Apply update dry-run permission issues:";
    const PATH_DELIMITER = "|";

    public function getPatterns()
    {
        return [sprintf("/%s/", static::MESSAGE)];
    }

    public static function getPathMessage($issuePaths)
    {
        return sprintf("Apply update dry-run permission issues:%s", implode(static::PATH_DELIMITER, $issuePaths));
    }

    public static function getErrorMessage($issuePaths)
    {
        return sprintf("Apply update dry-run detected %d permission issues", count($issuePaths));
    }

    protected function extractIssuePaths($messages)
    {
        $messages->rewind();
        $compactPathSet = "";
        while ($messages->valid()) {
            if (strpos($messages->current(), static::MESSAGE) !== false) {
                $compactPathSet = $messages->current();
            } else {
                $messages->next();
            }
        }
        $messages->rewind();
        return str_replace(static::MESSAGE, "", $compactPathSet);
    }

    public function issuePathsAsLines($paths)
    {
        return str_replace(static::PATH_DELIMITER, "\n", $paths);
    }

    public function __toString()
    {
        return sprintf("%s\n%s", static::MESSAGE, $this->issuePathsAsLines($this->extractIssuePaths($this->getParsedMessages())));
    }

    public function getTitle()
    {
        return "Insufficient File Permissions";
    }

    public function getHelpUrl()
    {
        return "https://docs.whmcs.com/Automatic_Updater#Permission_Errors";
    }

    protected function isKnown($data)
    {
        foreach ($this->getPatterns() as $pattern) {
            if (preg_match($pattern, $data) === 1) {
                return true;
            }
        }
        return false;
    }

    public function toHtml()
    {
        return $this->toGenericHtml($this->__toString());
    }

    public function toPlain()
    {
        return $this->toGenericPlain($this->__toString());
    }
}
