<?php

namespace WHMCS\File\Migration\Processor;

interface MigrationProcessorInterface
{
    public function migrate();

    public function getMigrationProgress();
}
