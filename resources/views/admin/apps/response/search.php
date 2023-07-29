<?php

foreach ($apps->all() as $app) {
    echo "    ";
    $this->insert("apps/shared/app", ["app" => $app, "searchDisplay" => true]);
}
