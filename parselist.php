<?php
$value = trim(fgets(STDIN)); // reads one line from STDIN
if (strpos($value, '&')) {
    $name = trim(substr($value, 0, strpos($value, '&')));
} else {
    $name = trim($value);
}
echo str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', str_replace('""', '', str_replace('(', '', str_replace(')', '', $name)))))));
/*$tokens = explode(" ", $line);
echo ( $tokens[0] . '\r');*/
/*
$lists = explode(',',$line);
foreach ($lists as $value) {
    $listname = str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', $value))));
    echo 'listname: ' . $listname . "\n";
}*/
?>
