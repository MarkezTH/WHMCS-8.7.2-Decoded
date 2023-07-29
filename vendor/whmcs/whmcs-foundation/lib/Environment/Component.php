<?php

namespace WHMCS\Environment;

class Component implements ComponentInterface
{
    protected $name = NULL;
    protected $topics = [];

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function name()
    {
        return $this->name;
    }

    public function addTopic($name, $closure)
    {
        if ($closure instanceof \Closure || is_object($closure) && is_callable($closure) || is_array($closure) && count($closure) === 2 && is_object($closure[0]) && method_exists($closure[0], $closure[1])) {
            $this->topics[$name] = $closure;
            return $this;
        }
        throw new \RuntimeException("Component topic closure not callable");
    }

    public function report(Report $report)
    {
        return ["name" => $this->name(), "topics" => $this->data($report)];
    }

    protected function data(Report $report)
    {
        $data = [];
        foreach ($this->topics as $topic => $closure) {
            try {
                $data[] = ["name" => $topic, "data" => $closure($this, $report)];
            } catch (\Exception $e) {
                logActivity("Unexpected error during component data aggregation");
            }
        }
        return $data;
    }
}
