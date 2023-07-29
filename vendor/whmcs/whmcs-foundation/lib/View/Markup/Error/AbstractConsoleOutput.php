<?php

namespace WHMCS\View\Markup\Error;

abstract class AbstractConsoleOutput implements \IteratorAggregate, \WHMCS\View\Markup\TransformInterface
{
    private $text = "";
    private $firstErrorOnly = true;
    private $showNonErrorAsError = true;

    public function __construct($firstErrorOnly = false, $showNonErrorAsError = false)
    {
        $this->firstErrorOnly = (bool) $firstErrorOnly;
        $this->showNonErrorAsError = (bool) $showNonErrorAsError;
    }
    public abstract function getIterator($Traversable);

    public function transform($text, $markupFormat = NULL, $emailFriendly = false)
    {
        $this->text = $text;
        $parsedTextIterator = $this->getIterator();
        $messageIterator = $this->getMessageIterator($parsedTextIterator);
        switch ($markupFormat) {
            case \WHMCS\View\Markup\TransformInterface::FORMAT_PLAIN:
                $formatted = $this->transformPlain($messageIterator);
                break;
            case \WHMCS\View\Markup\TransformInterface::FORMAT_HTML:
            case NULL:
            case "":
                $formatted = $this->transformHtml($messageIterator);
                return $formatted;
                break;
            default:
                throw new \InvalidArgumentException("Unsupported format for transformation");
        }
    }

    public function getText()
    {
        return $this->text;
    }

    protected function transformPlain(\Iterator $iterator)
    {
        $text = "";
        $iterator->rewind();
        while ($iterator->valid()) {
            $message = $iterator->current();
            if (!$message instanceof Message\MatchDecorator\MatchDecoratorInterface) {
                $iterator->next();
            } else {
                $text .= $message->toPlain();
                if (!$this->firstErrorOnly) {
                    $iterator->next();
                    if ($iterator->valid()) {
                        $text .= "\n";
                    }
                }
            }
        }
        $iterator->rewind();
        return $text;
    }

    protected function transformHtml(\Iterator $iterator)
    {
        $text = "";
        $iterator->rewind();
        while ($iterator->valid()) {
            $message = $iterator->current();
            if (!$message instanceof Message\MatchDecorator\MatchDecoratorInterface) {
                $iterator->next();
            } else {
                $text .= $message->toHtml();
                if (!$this->firstErrorOnly) {
                    $iterator->next();
                    if ($iterator->valid()) {
                        $text .= "<br/>";
                    }
                }
            }
        }
        $iterator->rewind();
        return $text;
    }

    protected function getMessageIterator(\Iterator $parsedTextIterator)
    {
        $errorMessages = new \SplQueue();
        $nonErrorMessages = new \SplQueue();
        $matchDecorators = $this->getMatchDecorators();
        foreach ($matchDecorators as $class) {
            $matcher = $class->wrap($parsedTextIterator);
            if ($matcher->hasMatch()) {
                if ($matcher->isAnError() || $this->showNonErrorAsError) {
                    $errorMessages->enqueue($matcher);
                } else {
                    $nonErrorMessages->enqueue($matcher);
                }
            }
        }
        if ($errorMessages->count()) {
            foreach ($nonErrorMessages as $matcher) {
                $errorMessages->enqueue($matcher);
            }
        } else {
            if (!$nonErrorMessages->count()) {
                $noMatchDecorator = new Message\MatchDecorator\NoMatchDecorator();
                $errorMessages->enqueue($noMatchDecorator->wrap($parsedTextIterator));
            }
        }
        return $errorMessages;
    }
    protected abstract function getMatchDecorators();
}
