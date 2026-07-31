<?php
$log = file_get_contents('/var/log/apache2/error.log');
$lines = explode("\n", trim($log));
$last = array_slice($lines, -20);
echo implode("\n", $last);
