<?php

namespace WHMCS\File;

class Filesystem extends \League\Flysystem\Filesystem
{
    use StorageErrorHandlingTrait;

    public function isLocalAdapter()
    {
        return $this->getAdapter() instanceof \League\Flysystem\Adapter\Local;
    }

    private function withoutAsserts($action)
    {
        $previousAssertSetting = $this->config->get("disable_asserts");
        $this->config->set("disable_asserts", true);
        try {
            $result = $action();
        } finally {
            $this->config->set("disable_asserts", $previousAssertSetting);
        }
    }

    public function deleteAllowNotPresent($path)
    {
        return $this->withoutAsserts(function () use($path) {
            return $this->delete($path);
        });
    }

    public function getSizeStrict($path)
    {
        return $this->withoutAsserts(function () use($path) {
            return $this->getSize($path);
        });
    }
}
