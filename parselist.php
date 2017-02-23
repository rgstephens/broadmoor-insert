<?php

function clean_name($value) {
    // drop text after &
    $name = trim($value);
    if (strpos($value, '&')) {
        $name = trim(substr($value, 0, strpos($name, '&')));
    }
    if (strpos($name, ' ')) {
        $name = trim(substr($value, 0, strpos($value, ' ')));
    }
    return str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', str_replace('""', '', str_replace('(', '', str_replace(')', '', $name)))))));
}

function first_middleinit($value) {
    // drop text after &
    $name = trim($value);
    if (strpos($value, '&')) {
        $name = trim(substr($value, 0, strpos($name, '&')));
    }
    return str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', str_replace('""', '', str_replace('(', '', str_replace(')', '', $name)))))));
}

function clean_string($value) {
    return str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', $value))));
}


$value = trim(fgets(STDIN)); // reads one line from STDIN

/*$clean = clean_name($value);
$middle = first_middleinit($value);
echo $clean . "\n";
echo $middle . "\n";
echo "-----" . "\n";

$firstinit_last = clean_string(clean_name($value)[0] . 'Stephens');
echo $firstinit_last . "\n";
$first_last = clean_string(clean_name($value) . 'Stephens');
echo $first_last . "\n";
$firstinit_middle_last = clean_string(first_middleinit($value) . 'Stephens');
echo $firstinit_middle_last . "\n";
$firstinit_last = clean_string(clean_name($value)[0] . 'Stephens') . 'bha';
echo $firstinit_last . "\n";
$first_last = clean_string(clean_name($value) . 'Stephens') . 'bha';
echo $first_last . "\n";
$firstinit_middle_last = clean_string(first_middleinit($value) . 'Stephens') . 'bha';
echo $firstinit_middle_last . "\n";
$num = 1;
$firstinit_last = clean_string(clean_name($value)[0] . 'Stephens') . $num;
echo $firstinit_last . "\n";
$num++;
$firstinit_last = clean_string(clean_name($value)[0] . 'Stephens') . $num;
echo $firstinit_last . "\n";*/

$tokens = explode(",", $value);
while (list ($key, $val) = each ($tokens) ) {
    echo $val . "\n";
}

/*if (strpos($value, '&')) {
    $name = trim(substr($value, 0, strpos($value, '&')));
} else {
    $name = trim($value);
}
echo str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', str_replace('""', '', str_replace('(', '', str_replace(')', '', $name)))))));*/
/*$tokens = explode(" ", $line);
echo ( $tokens[0] . '\r');*/
/*
$lists = explode(',',$line);
foreach ($lists as $value) {
    $listname = str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', $value))));
    echo 'listname: ' . $listname . "\n";
}*/
?>
