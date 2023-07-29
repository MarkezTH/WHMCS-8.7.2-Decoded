<?php

echo "<p>";
echo Lang::trans("twofadisableconfirmation");
echo "</p>\n\n<script>\n\$('.twofa-toggle-switch').bootstrapSwitch('state', false, true);\n\$('.twofa-config-link.disable').hide();\n\$('.twofa-config-link.enable').removeClass('hidden').show();\n</script>\n";
