<?php

echo "<script>\n    var myDefaultWhiteList = \$.fn.tooltip.Constructor.DEFAULTS.whiteList;\n    myDefaultWhiteList.button = ['data-role'];\n\n    var tour = new Tour({\n        name: \"marketconnect\",\n        container: \"body\",\n        smartPlacement: true,\n        keyboard: true,\n        storage: window.localStorage,\n        steps: [\n            ";
foreach ($tourSteps as $step) {
    echo "            {\n                element: \"";
    echo $step["element"];
    echo "\",\n                title: \"";
    echo addslashes($step["title"]);
    echo "\",\n                content: \"";
    echo addslashes($step["content"]);
    echo "\",\n                backdrop: ";
    echo $step["backdrop"] ? "true" : "false";
    echo ",\n                placement: \"";
    echo $step["placement"];
    echo "\",\n            },\n            ";
}
echo "        ]});\n    tour.init();\n</script>\n\n<a href=\"#\" id=\"btnPlayTour\" onclick=\"tour.restart().start(true);return false\" class=\"btn btn-default\">\n    <i class=\"fas fa-play-circle fa-fw\"></i>\n    Watch the Tour Again\n</a>\n";
