<?php
$map_broadmoor_fields = array(
    "usr_account_id" => "Member #",
    "usr_cell_phone" => "Mobile Phone",
    "usr_gender" => "Gender",
    "account_type" => "ClubTec Account Type",
    "usr_title" => "Title",
    "usr_birthday" => "Birthday",
    "usr_phone" => "Phone (Primary)",
    "usr_home_phone" => "Home Phone",
    "usr_phone_a" => "Work Phone",
    "usr_address" => "Home Address",
    "usr_address2" => "Home Address 2",
    "usr_city" => "Home City",
    "usr_state" => "Home State",
    "usr_zip" => "Home Zip",
    "usr_fax" => "Home Fax",
    "usr_company" => "Company",
    "usr_jobtitle" => "Job Title",
    "usr_address_a" => "Work Address",
    "usr_address2_a" => "Work Address 2",
    "usr_city_a" => "Work City",
    "usr_state_a" => "Work State",
    "usr_zip_a" => "Work Zip",
    "usr_fax_a" => "Work Fax",
    "usr_logon_count" => "ClubTec Login Count",
    "usr_family_id" => "Family Id",
    "grp_name" => "Groups");
//echo "state: " . $map_broadmoor_fields["usr_state_a"];
if (array_key_exists("usr_state_a", $map_broadmoor_fields)) {
    echo "usr_state_a: " . $map_broadmoor_fields["usr_state_a"];
}
if (array_key_exists("usr_id", $map_broadmoor_fields)) {
    echo "usr_id: " . $map_broadmoor_fields["usr_id"];
}
$line = trim(fgets(STDIN)); // reads one line from STDIN
$tokens = explode(" ", $line);
echo ( $tokens[0] . '\r');
/*
$lists = explode(',',$line);
foreach ($lists as $value) {
    $listname = str_replace('.', '', str_replace('-', '', str_replace('\'', '', str_replace(' ', '', $value))));
    echo 'listname: ' . $listname . "\n";
}*/
?>
