<?php
$line = trim(fgets(STDIN)); // reads one line from STDIN
$lists = explode(',',$line);
foreach ($lists as $value) {
    $listname = str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', $value))));
    echo 'listname: ' . $listname . "\n";
}
?>
