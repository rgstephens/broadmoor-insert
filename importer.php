<?php

class Logger
{
	protected $file;
	protected $content;
	protected $writeFlag;
	protected $endRow;

	public function __construct($file, $endRow = "\n", $writeFlag = FILE_APPEND)
	{
		$this->file = $file;
		//$this->writeFlag = $writeFlag;
		$this->endRow = $endRow;
	}

	public function AddRow($content = "", $newLines = 1)
	{
		$newRow = '';
		for ($m = 0; $m < $newLines; $m++) {
			$newRow .= $this->endRow;
		}
		$this->content .= date("Y-m-d H:i:s") . " " . $content . $newRow;
	}

	public function Commit()
	{
		return file_put_contents($this->file, $this->content, $this->writeFlag);
	}

	public function LogError($error, $newLines = 1)
	{
		if ($error != "") {
			$this->AddRow($error, $newLines);
			echo $error;
		}
	}
}

class MC4WP_Debug_Log_Reader {

	/**
	 * @var resource|null
	 */
	private $handle;

	/**
	 * @var string
	 */
	private static $regex = '/^(\[[\d \-\:]+\]) (\w+\:) (.*)$/S';

	/**
	 * @var string
	 */
	private static $html_template = '<span class="time">$1</span> <span class="level">$2</span> <span class="message">$3</span>';

	/**
	 * @var string The log file location.
	 */
	private $file;

	/**
	 * MC4WP_Debug_Log_Reader constructor.
	 *
	 * @param $file
	 */
	public function __construct( $file ) {
		$this->file = $file;
	}

	/**
	 * @return string
	 */
	public function all() {
		return file_get_contents( $this->file );
	}

	/**
	 * Sets file pointer to $n of lines from the end of file.
	 *
	 * @param int $n
	 */
	private function seek_line_from_end( $n ) {
		$line_count = 0;

		// get line count
		while( ! feof( $this->handle ) ) {
			fgets( $this->handle );
			$line_count++;
		}

		// rewind to beginning
		rewind( $this->handle );

		// calculate target
		$target = $line_count - $n;
		$target = $target > 1 ? $target : 1; // always skip first line because oh PHP header
		$current = 0;

		// keep reading until we're at target
		while( $current < $target ) {
			fgets( $this->handle );
			$current++;
		}
	}

	/**
	 * @return string|null
	 */
	public function read() {

		// open file if not yet opened
		if( ! is_resource( $this->handle ) ) {

			// doesn't exist?
			if( ! file_exists( $this->file ) ) {
				return null;
			}

			$this->handle = @fopen( $this->file, 'r' );

			// unable to read?
			if( ! is_resource( $this->handle ) ) {
				return null;
			}

			// set pointer to 1000 files from EOF
			$this->seek_line_from_end( 1000 );
		}

		// stop reading once we're at the end
		if( feof( $this->handle ) ) {
			fclose( $this->handle );
			$this->handle = null;
			return null;
		}

		// read line, up to 8kb
		$text = fgets( $this->handle );

		return $text;
	}

	/**
	 * @return string
	 */
	public function read_as_html() {
		$line = $this->read();

		if( is_null( $line ) ) {
			return null;
		}

		$line = preg_replace( self::$regex, self::$html_template, $line );
		return $line;
	}

	/**
	 * Reads X number of lines.
	 *
	 * If $start is negative, reads from end of log file.
	 *
	 * @param int $start
	 * @param int $number
	 * @return string
	 */
	public function lines( $start, $number ) {
		$handle = fopen( $start, 'r' );
		$lines = '';

		$current_line = 0;
		while( $current_line < $number ) {
			$lines .= fgets( $handle );
		}

		fclose( $handle );
		return $lines;
	}

}

if ( ! defined( 'ABSPATH' ) ) exit;

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

function write_import_summary_line($change_type, $memnum, $name) {
	$styles = "";
	if ($change_type == 'i') {
		$styles = "background-color:LightGreen; color:black;";
	} elseif ($change_type == 'd') {
		$styles = "background-color:red; color:white;";
	}

	echo "<tr style='$styles' >";
	if ($change_type == 'i') {
		echo "<td>New</td>";
	} elseif ($change_type == 'd') {
		echo "<td>Delete</td>";
	}
	echo "<td>" . $memnum . "</td>" . "<td>" . $name . "</td>";
	echo "</tr>\n";
}

function echo_deleted_members($deleted_members) {
	// Display the members to be deleted
	foreach ($deleted_members as &$value) {
		$args = array(
			'meta_key'     => 'membership_number',
			'meta_value'   => $value
		);
		$usersfound = get_users( $args );
		error_log(print_r("--- Delete " . $value . ", " . $usersfound[0]->data->display_name, true));
		write_import_summary_line('d', $value, $usersfound[0]->data->display_name);
	}
}

function composeUsername($data) {
	// Try first initial, last name
	$name = clean_string(clean_name($data[2])[0] . $data[3]);
	if (!username_exists( $name ) ) {
		return $name;
	}
	// Try first name, last name
	$name = clean_string(clean_name($data[2]) . $data[3]);
	if (!username_exists( $name ) ) {
		return $name;
	}
	// Try first name, middle init, last name
	$name = clean_string(first_middleinit($data[2]) . $data[3]);
	if (!username_exists( $name ) ) {
		return $name;
	}

	$wp_role = type_to_role($data[7]);
	if ($wp_role == 'Homeowner' || $wp_role == 'Homowner') {
		// Try first initial, last name
		$name = clean_string(clean_name($data[2])[0] . $data[3]) . 'bha';
		if (!username_exists( $name ) ) {
			return $name;
		}
		// Try first name, last name
		$name = clean_string(clean_name($data[2]) . $data[3]) . 'bha';
		if (!username_exists( $name ) ) {
			return $name;
		}
		// Try first name, middle init, last name
		$name = clean_string(first_middleinit($data[2]) . $data[3]) . 'bha';
		if (!username_exists( $name ) ) {
			return $name;
		}
	}

	// Try numbers after first initial, last name
	$num = 1;
	$name = clean_string(clean_name($data[2])[0] . $data[3]) . $num;
	if (!username_exists( $name ) ) {
		return $name;
	}
	$num++;
	$name = clean_string(clean_name($data[2])[0] . $data[3]) . $num;
	if (!username_exists( $name ) ) {
		return $name;
	}
	$num++;
	$name = clean_string(clean_name($data[2])[0] . $data[3]) . $num;
	if (!username_exists( $name ) ) {
		return $name;
	}
}

function userChanged($data, $userdata) {
	return false;
}

//userMetaFieldChanged($user_id, "role", $all_user_meta, strtolower($wp_role), false);
function userMetaFieldChanged($user_id, $field, $all_user_meta, $newval, $preview) {
	if ($all_user_meta[$field][0])
	update_user_meta( $user_id, "role", strtolower($wp_role) );
	return false;
}

function userMetaChanged($data, $userdata) {
	return false;
}

function existing_email($existing_email, $data) {
	//error_log(print_r("check existing_email: " . $existing_email, true));
	error_log(print_r(" emails Home: " . $data[17] . ", Work: " . $data[27] . ", Corr: " . $data[33], true));
	if ($existing_email == $data[17]) {
		//error_log(print_r("  match 17: " . $data[17], true));
		return true;
	} else if ($existing_email == $data[27]) {
		//error_log(print_r("  match 27: " . $data[27], true));
		return true;
	} else if ($existing_email == $data[33]) {
		//error_log(print_r("  match 33: " . $data[33], true));
		return true;
	}
	return false;
}

function grab_email($data) {
	if ($data[17]) {
		//error_log(print_r("data[17]: " . $data[17], true));
		return $data[17];
	} elseif ($data[27]) {
		//error_log(print_r("data[27]: " . $data[27], true));
		return $data[27];
	} elseif ($data[33]) {
		//error_log(print_r("data[33]: " . $data[33], true));
		return $data[33];
	} else {
		return false;
	}
/*	if ($data[11]) {
		return $data[11];
	} elseif ($data[12]) {
		return $data[12];
	} else {
		return false;
	}*/
}

function type_to_role($clubtec_type) {
	$tokens = explode(" ", $clubtec_type);
	//error_log(print_r("type_to_role: " . $clubtec_type . " token: " . $tokens[0], true));
	switch ($tokens[0]) {
		case '30-34':
		case '35':
		case 'Honorary':
		case 'Lifetime':
		case 'Social':
		case 'Adv':
		case 'Adv.':
		case 'Men':
		case 'New':
		case 'Senior':
		case 'Under':
		case 'W.':
		case 'Women':
			return 'Member';
			break;
		case 'Employee':
			return 'Employee';
			break;
		case 'Homeowner':
		case 'Homowner':
			return 'Homeowner';
			break;
		case 'Non-Owner':
			return 'Non-Owner';
			break;
		case 'Renters':
			return 'Renters';
		    break;
	}
}

function type_to_type($clubtec_type) {
	$tokens = explode(" ", $clubtec_type);
	//error_log(print_r("type_to_type: " . $clubtec_type . " token: " . $tokens[0], true));
	switch ($tokens[0]) {
		case '30-34':
		case '35':
		case 'Homowner':
		case 'Homeowner':
		case 'Honorary':
		case 'Lifetime':
		case 'Non-Owner':
		case 'Renters':
		case 'Social':
			return $tokens[0];
			break;
		case 'Adv':
		case 'Adv.':
			return 'Adv. Intermediate';
			break;
		case 'Employee':
			return 'Employee';
			break;
		case 'Men':
			return 'Men S/H';
			break;
		case 'New':
			return 'Lifetime';
			break;
		case 'Senior':
			return 'Senior Social Golf';
			break;
		case 'Under':
			return 'Under 30 Non-Stock Member';
			break;
		case 'W.':
		case 'Women':
			return 'Women S/H';
			break;
	}
}

function type_to_list($user_id, $clubtec_type, $gender, $relationship)
{
	$tokens = explode(" ", $clubtec_type);
	//error_log(print_r("type_to_list: " . $clubtec_type . " token: " . $tokens[0] . ", gender: " . $gender, true));
	switch ($tokens[0]) {
		case '30-34':
		case '35':
		case 'Under':
			if ($gender == 'F') {
				update_user_meta($user_id, "men_golfing", "False");
				update_user_meta($user_id, "women_golfing", "True");
			} else {
				update_user_meta($user_id, "men_golfing", "True");
				update_user_meta($user_id, "women_golfing", "False");
			}
			update_user_meta($user_id, "all_homeowner", "False");
			update_user_meta($user_id, "all_golf_club", "True");
			update_user_meta($user_id, "employee", "False");
			update_user_meta($user_id, "under_40", "True");
			update_user_meta($user_id, "guest", "False");
			update_user_meta($user_id, "junior", "False");
			update_user_meta($user_id, "adv_intermediate", "False");
			update_user_meta($user_id, "opt_in_bha", "False");
			update_user_meta($user_id, "opt_in_bgc", "True");
		case 'Honorary':
		case 'Lifetime':
		case 'Social':
		case 'Adv':
		case 'Adv.':
		case 'Men':
		case 'New':
		case 'Senior':
		case 'W.':
		case 'Women':
			update_user_meta($user_id, "all_golf_club", "True"); // All Golf Club
			update_user_meta($user_id, "all_homeowner", "False");
			update_user_meta($user_id, "employee", "False");
			update_user_meta($user_id, "under_40", "False");
			update_user_meta($user_id, "guest", "False");
			update_user_meta($user_id, "junior", "False");
			update_user_meta($user_id, "adv_intermediate", "False");
			update_user_meta($user_id, "opt_in_bha", "False");
			update_user_meta($user_id, "opt_in_bgc", "True");  // Get the newsletter
			if ($relationship == '1') {
				if ($gender == 'F') {
					update_user_meta($user_id, "men_golfing", "False");
					update_user_meta($user_id, "women_golfing", "True");
				} else {
					update_user_meta($user_id, "men_golfing", "True");
					update_user_meta($user_id, "women_golfing", "False");
				}
			} else {
				// This is a spouse, is it paid or not paid spouse
				if (strpos($clubtec_type, 'Spouse')) {
					// paid spouse, add to appropriate list
					//error_log(print_r("type_to_list found Spouse of member, type: " . $clubtec_type . ", relationship: " . $relationship . ", gender: " . $gender, true));
					if ($gender == 'F') {
						update_user_meta($user_id, "men_golfing", "False");
						update_user_meta($user_id, "women_golfing", "True");
					} else {
						update_user_meta($user_id, "men_golfing", "True");
						update_user_meta($user_id, "women_golfing", "False");
					}
				} else {
					// not a paid spouse does not get newsletter
					update_user_meta($user_id, "men_golfing", "False");
					update_user_meta($user_id, "women_golfing", "False");
				}
			}
			break;
		case 'Employee':
			update_user_meta($user_id, "men_golfing", "True");
			update_user_meta($user_id, "women_golfing", "True");
			update_user_meta($user_id, "all_golf_club", "False");
			update_user_meta($user_id, "all_homeowner", "False");
			update_user_meta($user_id, "employee", "True");
			update_user_meta($user_id, "under_40", "False");
			update_user_meta($user_id, "guest", "False");
			update_user_meta($user_id, "junior", "False");
			update_user_meta($user_id, "adv_intermediate", "False");
			update_user_meta($user_id, "opt_in_bha", "False");
			update_user_meta($user_id, "opt_in_bgc", "False");
			break;
		case 'Homowner':
		case 'Homeowner':
		    //error_log(print_r("setting all_homeowner to true for user: " . $user_id, true));
			update_user_meta($user_id, "all_resident", "True");
			update_user_meta($user_id, "all_homeowner", "True");
			update_user_meta($user_id, "non_owner", "False");
			update_user_meta($user_id, "renter", "False");
			update_user_meta($user_id, "men_golfing", "False");
			update_user_meta($user_id, "women_golfing", "False");
			update_user_meta($user_id, "all_golf_club", "False");
			update_user_meta($user_id, "employee", "False");
			update_user_meta($user_id, "under_40", "False");
			update_user_meta($user_id, "guest", "False");
			update_user_meta($user_id, "junior", "False");
			update_user_meta($user_id, "adv_intermediate", "False");
			update_user_meta($user_id, "opt_in_bha", "True");
			update_user_meta($user_id, "opt_in_bgc", "False");
			break;
		case 'Non-Resident':
		case 'Non-Owner':
			update_user_meta($user_id, "all_resident", "True");
			update_user_meta($user_id, "all_homeowner", "False");
			update_user_meta($user_id, "non_owner", "True");
			update_user_meta($user_id, "renter", "False");
			update_user_meta($user_id, "men_golfing", "False");
			update_user_meta($user_id, "women_golfing", "False");
			update_user_meta($user_id, "all_golf_club", "False");
			update_user_meta($user_id, "employee", "False");
			update_user_meta($user_id, "under_40", "False");
			update_user_meta($user_id, "guest", "False");
			update_user_meta($user_id, "junior", "False");
			update_user_meta($user_id, "adv_intermediate", "False");
			update_user_meta($user_id, "opt_in_bha", "True");
			update_user_meta($user_id, "opt_in_bgc", "False");
			break;
		case 'Renters':
			error_log(print_r("setting renter to true for user: " . $user_id, true));
			update_user_meta($user_id, "all_resident", "True");
			update_user_meta($user_id, "all_homeowner", "False");
			update_user_meta($user_id, "non_owner", "False");
			update_user_meta($user_id, "renter", "True");
			update_user_meta($user_id, "men_golfing", "False");
			update_user_meta($user_id, "women_golfing", "False");
			update_user_meta($user_id, "all_golf_club", "False");
			update_user_meta($user_id, "employee", "False");
			update_user_meta($user_id, "under_40", "False");
			update_user_meta($user_id, "guest", "False");
			update_user_meta($user_id, "junior", "False");
			update_user_meta($user_id, "adv_intermediate", "False");
			update_user_meta($user_id, "opt_in_bha", "True");
			update_user_meta($user_id, "opt_in_bgc", "False");
			break;
	}
	//error_log(print_r("all_homeowner set to: " . get_user_meta($user_id, 'all_homeowner'), true));
}

function grp_name_to_list($user_id, $grp_name, $clubtec_type)
{
	error_log(print_r("grp_name_to_list, grp_name: " . $grp_name, true));
	update_user_meta($user_id, "men_golfing", "False");
	update_user_meta($user_id, "women_golfing", "False");
	update_user_meta($user_id, "all_resident", "False");
	update_user_meta($user_id, "all_homeowner", "False");
	update_user_meta($user_id, "non_owner", "False");
	update_user_meta($user_id, "renter", "False");
	update_user_meta($user_id, "all_golf_club", "False");
	update_user_meta($user_id, "employee", "False");
	update_user_meta($user_id, "under_40", "False");
	update_user_meta($user_id, "guest", "False");
	update_user_meta($user_id, "junior", "False");
	update_user_meta($user_id, "adv_intermediate", "False");
	update_user_meta($user_id, "opt_in_bha", "False");
	update_user_meta($user_id, "opt_in_bgc", "False");

	$tokens = explode(",", $grp_name);
	while (list ($key, $val) = each ($tokens) ) {
		//echo $val . "\n";
		//error_log(print_r("grp_name_to_list, val: " . $val, true));
		switch ($val) {
			case 'Staff':
			case 'Broadmoor Employee Team':
				update_user_meta($user_id, "employee", "True");
				break;
			case 'All Golf Club':
				update_user_meta($user_id, "all_golf_club", "True");
				break;
			case 'All Homeowners':
				update_user_meta($user_id, "all_resident", "True");
				$tokens = explode(" ", $clubtec_type);
				// If it says homeower but the ClubTec type is something other than Homeowner, don't set all_homeower
				if ($tokens[0] == 'Homeowner' || $tokens[0] == 'Homowner') {
					update_user_meta($user_id, "all_homeowner", "True");
				} else {
					update_user_meta($user_id, "all_homeowner", "False");
				}
				break;
			case 'Renters':
				//update_user_meta($user_id, "all_homeowner", "True");
				update_user_meta($user_id, "all_resident", "True");
				update_user_meta($user_id, "renter", "True");
				break;
			case 'Non-Owner':
			case 'Non-Owner Residents':
				//update_user_meta($user_id, "all_homeowner", "True");
				update_user_meta($user_id, "all_resident", "True");
				update_user_meta($user_id, "non_owner", "True");
				break;
			case 'Guest':
				update_user_meta($user_id, "guest", "True");
				break;
			case 'Mens Golfing':
				update_user_meta($user_id, "men_golfing", "True");
				break;
			case 'Women\'s Golfing':
				update_user_meta($user_id, "women_golfing", "True");
				break;
			case 'AI Only':
			case 'AI - No Spouse':
				update_user_meta($user_id, "adv_intermediate", "True");
				break;
			case 'Junior Golf':
				update_user_meta($user_id, "junior", "True");
				break;
			case 'U-40 Only':
			case 'U-40 - No spouse':
				update_user_meta($user_id, "under_40", "True");
				break;
			case 'Opt In - Newsletter - BHA':
				update_user_meta($user_id, "opt_in_bha", "True");
				break;
			case 'Opt-in Newsletter - BGC':
				update_user_meta($user_id, "opt_in_bgc", "True");
				break;
			case 'Opt In-Catering':
			case 'Opt In-Club Information':
			case 'Opt In-Golf':
			case 'Opt In-Main Dining Room':
			case 'Opt In-Special Events':
			case 'Junior Parents':
				break;
		}
	}
}

function add_children($user_id, $file, $row, $memberbase) {
	//error_log(print_r("add_children, row: " . $row . ", memberBase: " . $memberbase, true));
	$childrenNames = '';
	$fileloc = new SplFileObject( $file );
	$fileloc->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
	$rowNum = intval($row);
	$startRowNum = $rowNum;
	$fileloc->seek($rowNum);
	$currentLine = $fileloc->current();
	$mybase = explode("-", $currentLine[1]);
	while (($currentLine[5] == '2' || $currentLine[5] == '3') && $mybase[0] == $memberbase) {
		//error_log(print_r("add_children, we have a spouse or child, currentLine: " . $currentLine[2], true));
		if ($currentLine[5] == '3') {
			if (strlen($childrenNames) > 0) {
				$childrenNames = $childrenNames . ', ' . $currentLine[2];
			} else {
				$childrenNames = $currentLine[2];
			}
		}
		//error_log(print_r("add_children, children_names: " . $childrenNames, true));
		// go to next row
		$rowNum = $rowNum + 1;
		$fileloc->seek($rowNum);
		$currentLine = [];
		$currentLine = $fileloc->current();
		$mybase = explode("-", $currentLine[1]);
		//error_log(print_r("next currentLine: " . $currentLine[1] . ", count: " . count($currentLine) . ", eof: " . $fileloc->eof(), true));
		if ($fileloc->eof()) {
			if ($currentLine[5] == '3') {
				if (strlen($childrenNames) > 0) {
					$childrenNames = $childrenNames . ', ' . $currentLine[2];
				} else {
					$childrenNames = $currentLine[2];
				}
			}
			//error_log(print_r("add_children, EOF: " . $childrenNames, true));
			break;
		}
		if ($rowNum > ($startRowNum + 5)) {  // don't keep going after this many
			//error_log(print_r("add_children, break: " . $childrenNames, true));
			break;
		}
	}
	//error_log(print_r(">> add_children, children_names: " . $childrenNames, true));
	update_user_meta($user_id, "children", $childrenNames);
/*	error_log(print_r("add_children, got current line", true));
	if ($currentLine) {
		error_log(print_r("add_children, next row: ", true));
		error_log(print_r($currentLine, true));
	} else {
		error_log(print_r("add_children, current line false", true));
	}*/
}

function add_spouse($user_id, $file, $row, $data) {
	if ($data[5] == '2') {
		// we've got a spouse entry
		// ******************************************
		// get primary member link and add to spouse (this user)
		// ******************************************
		error_log(print_r("add_spouse #2, row: " . $row, true));
		$fileloc = new SplFileObject( $file );
		$fileloc->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
		$rowNum = intval($row) - 2;
		$fileloc->seek($rowNum);
		$currentLine = $fileloc->current();
		//error_log(print_r("add_spouse, spouse: " . $currentLine[2], true));
		//error_log(print_r($currentLine, true));
		$member_base = explode("-", $data[1]);
		//error_log(print_r("add_spouse, membership_number: " . $member_base[0] . '-000', true));
		$args = array(
			'meta_key'     => 'membership_number',
			'meta_value'   => $member_base[0] . '-000',
		);
		$member_list = get_users($args);
		$member = $member_list[0];
		//error_log(print_r("add_spouse, member: ", true));
		//error_log(print_r($member, true));
		if ( !function_exists( 'um_user_profile_url' ) ) {
			//error_log(print_r("add_spouse, loading um-short-functions.php", true));
			require_once '/core/um-short-functions.php';
		}

		//error_log(print_r("add_spouse, um_fetch_user", true));
		um_fetch_user($member->ID);
		//error_log(print_r("add_spouse, profile url: " . um_user_profile_url(), true));
		$member_url = um_user_profile_url();
		// Update spouse record with member info
		update_user_meta($user_id, "spouse_url", $member_url);
		update_user_meta($user_id, "spouse_name", $member->display_name);
		um_reset_user();

		// ******************************************
		// add spouse link (this user) to primary member
		// ******************************************
		um_fetch_user($user_id);
		$spouse_url = um_user_profile_url();
		um_reset_user();
		$spouse_displayname = clean_name($data[2]) . ' ' . $data[3];
		// Update member info with spouse details
		update_user_meta($member->ID, "spouse_url", $spouse_url);
		update_user_meta($member->ID, "spouse_name", $spouse_displayname);

		//error_log(print_r("add_spouse, spouse_url: " . $spouse_url, true));
	}
}

function acui_import_users( $file, $form_data, $attach_id = 0, $is_cron = false ){?>
	<div class="wrap">
		<h2>Importing users</h2>	
		<?php
		$logFileDirectoryAndName="/var/www/html/wp-content/import.log";
		$logger = new Logger($logFileDirectoryAndName);

			set_time_limit(0);
			
			do_action( 'before_acui_import_users' );

			global $wpdb;
			global $wp_users_fields;
			global $wp_min_fields;
		$report_fields = array('MemberNumber', 'FirstName', 'LastName', 'TypeDesc', 'HomeEmail');
		$map_broadmoor_fields = array(
			"MemberNumber" => "membership_number",
			"MobilePhone" => "mobile_phone",
			"Gender" => "gender",
			"Title" => "title",
			"Relationsip" => "relationship",
			"Birthdate" => "birthday",
			"HomeEmail" => "home_email",
			"BusinessEmail" => "business_email",
			"CorrEmail" => "corr_email",
			"HomePhone" => "home_phone",
			"BusinessPhone" => "work_phone",
			"HomeAddress1" => "home_address",
			"HomeAddress2" => "home_address_2",
			"HomeCity" => "home_city",
			"HomeState" => "home_state",
			"HomeZip" => "home_zip",
			"HomeFax" => "home_fax",
			"BusinessCompany" => "company",
			"usr_jobtitle" => "job_title",
			"BusinessAddress1" => "work_address",
			"BusinessAddress2" => "work_address_2",
			"BusinessCity" => "work_city",
			"BusinessState" => "work_state",
			"BusinessZip" => "work_zip",
			"BusinessFax" => "work_fax",
			"TypeDesc" => "clubtec_account_type"
			//"usr_logon_count" => "ClubTec Login Count",
			//"usr_family_id" => "Family Id",
			//"grp_name" => "Groups"
		);

			if( is_plugin_active( 'wp-access-areas/wp-access-areas.php' ) ){
				$wpaa_labels = WPAA_AccessArea::get_available_userlabels(); 
			}

			$buddypress_fields = array();

			if( is_plugin_active( 'buddypress/bp-loader.php' ) ){
				$profile_groups = BP_XProfile_Group::get( array( 'fetch_fields' => true	) );

				if ( !empty( $profile_groups ) ) {
					 foreach ( $profile_groups as $profile_group ) {
						if ( !empty( $profile_group->fields ) ) {				
							foreach ( $profile_group->fields as $field ) {
								$buddypress_fields[] = $field->name;
							}
						}
					}
				}
			}

			//$broadmoor_users_fields = array( "usr_login", "usr_email", "account_type", "usr_firstname", "usr_lastname" );
			$broadmoor_users_fields = array("HomeEmail", "TypeDesc", "FirstName", "LastName" );
			$users_registered = array();
			$headers = array();
			$headers_filtered = array();
			$broadmoor_fields_filtered = array();
			$update_existing_users = $form_data["update_existing_users"];
			//$role = $form_data["role"];
			//$update_roles_existing_users = $form_data["update_roles_existing_users"];
			$update_roles_existing_users = 'no';
			$empty_cell_action = $form_data["empty_cell_action"];

			if( empty( $form_data["activate_users_wp_members"] ) )
				$activate_users_wp_members = "no_activate";
			else
				$activate_users_wp_members = $form_data["activate_users_wp_members"];

		$allow_multiple_accounts = "not_allowed";
/*			if( empty( $form_data["allow_multiple_accounts"] ) )
				$allow_multiple_accounts = "not_allowed";
			else
				$allow_multiple_accounts = $form_data["allow_multiple_accounts"];*/

			//echo "<h3>" . __('Ready to registers','import-users-from-csv-with-meta') . "</h3>";
			//echo "<p>" . __('First row represents the form of sheet','import-users-from-csv-with-meta') . "</p>";
			$row = 0;
			$positions = array();

			ini_set('auto_detect_line_endings',TRUE);

			$delimiter = acui_detect_delimiter( $file );

			$manager = new SplFileObject( $file );
			$csv_member_numbers = array();
			while ( $data = $manager->fgetcsv( $delimiter ) ):
				if( empty($data[0]) )
					continue;

				if( count( $data ) == 1 )
					$data = $data[0];
				
				foreach ($data as $key => $value){
					$data[ $key ] = trim( $value );
				}

				for($i = 0; $i < count($data); $i++){
					$data[ $i ] = acui_string_conversion( $data[$i] );
				}
				
				if($row == 0):
					// check min columns username - email
					if(count( $data ) < 2){
						echo "<div id='message' class='error'>" . __( 'File must contain at least 2 columns: username and email', 'import-users-from-csv-with-meta' ) . "</div>";
						break;
					}

					$i = 0;
					$password_position = false;
					$id_position = false;
					
					foreach ( $wp_users_fields as $wp_users_field ) {
						$positions[ $wp_users_field ] = false;
					}

					foreach($data as $element){
						$headers[] = $element;

						if( in_array( strtolower($element) , $wp_users_fields ) )
							$positions[ strtolower($element) ] = $i;

/*						if( !in_array( strtolower( $element ), $wp_users_fields ) && !in_array( $element, $wp_min_fields ) && !in_array( $element, $buddypress_fields ) )
							$headers_filtered[] = $element;*/

						if( !in_array( strtolower( $element ), $wp_users_fields ) && !in_array( $element, $wp_min_fields ) && !in_array( $element, $buddypress_fields ) )
							if (array_key_exists($element, $map_broadmoor_fields)) {
								//error_log(print_r("mapping element: " . $element . " to broadmoor: " . $map_broadmoor_fields[$element], true));
								$headers_filtered[] = $map_broadmoor_fields[$element];
							}

						$i++;
					}
		$headers_filtered[] = 'opt_in_bgc';
		$headers_filtered[] = 'opt_in_bha';
		$headers_filtered[] = 'all_golf_club';
		$headers_filtered[] = 'men_golfing';
		$headers_filtered[] = 'women_golfing';
		$headers_filtered[] = 'under_40';
		$headers_filtered[] = 'junior';
		$headers_filtered[] = 'adv_intermediate';
		$headers_filtered[] = 'all_homeowner';
		$headers_filtered[] = 'all_resident';
		$headers_filtered[] = 'non_owner';
		$headers_filtered[] = 'renter';
		$headers_filtered[] = 'employee';
		$headers_filtered[] = 'guest';
		$headers_filtered[] = 'children';
		$headers_filtered[] = 'spouse_name';
		$headers_filtered[] = 'spouse_url';

					$columns = count( $data );

		//error_log(print_r("calling acui_columns: ", true));
		//error_log(print_r($headers_filtered, true));
		update_option( "acui_columns", $headers_filtered );
		if (isset($form_data["preview"])) {
			echo "<h3><span style='background-color:red; color:white;'>Preview</span> Inserting and updating data <span style='background-color:red; color:white;'>Preview</span></h3>";
		} else {
			echo "<h3>Inserting and updating data</h3>";
		}
		?>
					<table>
						<tr><th>Action</th><th>Member#</th><th>Name</th></tr>
					<?php
					$row++;
				else:
					if( count( $data ) != $columns ): // if number of columns is not the same that columns in header
						echo '<script>alert("' . __( 'Row number', 'import-users-from-csv-with-meta' ) . " $row " . __( 'does not have the same columns than the header, we are going to skip', 'import-users-from-csv-with-meta') . '");</script>';
						continue;
					endif;

					$email = grab_email($data);  // check $data[11] & $data[12]

					//*********************************************
					// Preview - check for new and modified
					//*********************************************
					if (isset($form_data["preview"])) {
						if ($data[5] != '3') {
							array_push($csv_member_numbers, $data[1]);
							//$csv_member_numbers[] = array('membership_number' => $data[1], 'first_name' => $data[2], 'last_name' => $data[3]);
							$args = array(
								'meta_key'     => 'membership_number',
								'meta_value'   => $data[1]
							);
							$usersfound = get_users($args);
							error_log(print_r("Preview lookup Member #: " . $data[1] . ", " . $data[2] . " " . $data[3] . ", found: " . count($usersfound), true));
							//error_log(print_r("  usersfound: " . count($usersfound), true));
							//$usersfound = get_users('membership_number=' . $data[1]);
							if (count($usersfound) > 0) {
								if (userChanged($data, $usersfound[0]) || userMetaChanged($data, $usersfound[0])) {
									error_log(print_r(">>> Updated Member #: " . $data[1] . ", " . $usersfound[0]->data->display_name, true));
								}
							} else {
								if ($data[5] != '3' || $email) {
									error_log(print_r("+++ New Member #: " . $data[1] . ", " . $data[2] . " " . $data[3], true));
								}
							}
						}
						continue;
					}

					do_action('pre_acui_import_single_user', $headers, $data );
					$data = apply_filters('pre_acui_import_single_user_data', $data, $headers);

					//$doing_create = false;
					$send_email = true;
					$username = composeUsername($data);  // usr_login from ClubTec export
					error_log(print_r("=========================================", true));
					error_log(print_r("CSV Name: " . $data[2] . " " . $data[3] . ", memberNum: " . $data[1], true));
					$user_id = 0;
					$problematic_row = false;
					$password_position = $positions["password"];
					$password = "";

					$id = "";

					$created = true;

					if( $password_position === false )
						$password = wp_generate_password();
					else
						$password = $data[ $password_position ];

					// skip children
					if ($data[5] == '3' && !$email) {
						$row++;
						error_log(print_r("skipping child: " . $data[2] . " " . $data[3], true));
						continue;
					}

					//*********************************************
					// Lookup member number in WordPress
					//*********************************************
					//error_log(print_r("  Lookup memberNum: " . $data[1], true));
					$args = array(
						'meta_key'     => 'membership_number',
						'meta_value'   => $data[1]
					);
					$usersfound = get_users( $args );
					//error_log(print_r("usersfound: ", true));
					//error_log(print_r($usersfound, true));

					if (count($usersfound) > 0) {
						//*********************************************
						// Member exists, update email address
						//*********************************************
						$user_object = $usersfound[0];
						$user_id = $user_object->ID;
						error_log(print_r("  Found memberNum: " . $data[1] . ", user_email: " . $user_object->user_email, true));
						if( $update_existing_users == 'no' ){
							continue;
						}
						$all_user_meta = get_user_meta( $user_id );
						if( $password !== "" )
							wp_set_password( $password, $user_id );
						$created = false;
					}
					elseif( !empty( $id ) ){ // if user have used id
						error_log(print_r("id was supplied: " . $id . ", type: " . gettype($id), true));
						//*********************************************
						// ClubTec import never has WordPress user id
						//*********************************************
						if( acui_user_id_exists( $id ) ){
							if( $update_existing_users == 'no' ){
								continue;
							}
						// we check if username is the same than in row
							$user = get_user_by( 'ID', $id );
							if( $user->user_login == $username ){
								$user_id = $id;
								if( $password !== "" )
									wp_set_password( $password, $user_id );
								if( !empty( $email ) ) {
									$updateEmailArgs = array(
										'ID'         => $user_id,
										'user_email' => $email
									);
									wp_update_user( $updateEmailArgs );
								}
								$created = false;
							}
							else{
								echo '<script>alert("' . __( 'Problems with ID', 'import-users-from-csv-with-meta' ) . ": $id , " . __( 'username is not the same in the CSV and in database, we are going to skip.', 'import-users-from-csv-with-meta' ) . '");</script>';
								continue;
							}
						}
						else{
							$userdata = array(
								'ID'		  =>  $id,
							    'user_login'  =>  $username,
							    'user_email'  =>  $email,
								'user_pass'   =>  $password,
								'description' =>  $data[1]
							);
							error_log(print_r("calling wp_insert_user: ", true));
							error_log(print_r($userdata, true));
							$user_id = wp_insert_user( $userdata );

							$created = true;
						}
					}
					elseif( username_exists( $username ) ){ // if user exists, we take his ID by login, we will update his mail if it has changed
						error_log(print_r("username exists: " . $username, true));
						//*********************************************
						// username exists
						//*********************************************
						if( $update_existing_users == 'no' ){
							continue;
						}

						$user_object = get_user_by( "login", $username );
						$user_id = $user_object->ID;

						if( $password !== "" )
							wp_set_password( $password, $user_id );

						$created = false;
					}
					elseif( email_exists( $email ) && $allow_multiple_accounts == "not_allowed" ){ // if the email is registered, we take the user from this and we don't allow repeated emails
						//*********************************************
						// Username exists, multiple accounts not allowed
						//*********************************************
						error_log(print_r("duplicate email NOT allowed", true));
						if( $update_existing_users == 'no' ){
							continue;
						}

	                    $user_object = get_user_by( "email", $email );
	                    $user_id = $user_object->ID;
	                    
	                    $data[0] = __( 'User already exists as:', 'import-users-from-csv-with-meta' ) . $user_object->user_login . '<br/>' . __( '(in this CSV file is called:', 'import-users-from-csv-with-meta' ) . $username . ")";
	                    $problematic_row = true;

	                    if( $password !== "" )
	                        wp_set_password( $password, $user_id );

	                    $created = false;
					}
					elseif( email_exists( $email ) && $allow_multiple_accounts == "allowed" ){ // if the email is registered and repeated emails are allowed
						//*********************************************
						// Username exists, multiple accounts ARE allowed (should not be here)
						//*********************************************
						error_log(print_r("doing_create, duplicate email but we are allowing that", true));
						$hacked_email = acui_hack_email( $email );
						$user_id = wp_create_user( $username, $password, $hacked_email );
						acui_hack_restore_remapped_email_address( $user_id, $email );
					}
					else{
						error_log(print_r("email & username not found, memberNum: " . $data[1] . ", email: " . $email . ", username: " . $username, true));
						error_log(print_r("username exists: " . username_exists($username), true));
						error_log(print_r("email exists: " . email_exists( $email ), true));

						//*********************************************
						// This is where the new user create happens
						//*********************************************
						//$doing_create = true;
						write_import_summary_line('i', $data[1], $data[2] . " " . $data[3]);
						$email = grab_email($data);
						error_log(print_r("wp_create_user, username: " . $username . ", email: " . $email . ", TypeDesc: " . $data[7], true));
						//$user_id = wp_create_user( $username, $password, $email );
						$userdata = array(
							'ID'		  =>  $id,
							'user_login'  =>  $username,
							'user_email'  =>  $email,
							'user_pass'   =>  $password,
							'description'    =>  $data[1]
						);
						//error_log(print_r("calling wp_insert_user 2: ", true));
						//error_log(print_r($userdata, true));
						$user_id = wp_insert_user( $userdata );
					}

					if( is_wp_error( $user_id ) ){ // in case the user is generating errors after this checks
						$error_string = $user_id->get_error_message();
						echo '<script>alert("' . __( 'Problems with user:', 'import-users-from-csv-with-meta' ) . $username . __( ', we are going to skip. \r\nError: ', 'import-users-from-csv-with-meta') . $error_string . '");</script>';
						continue;
					}

					$users_registered[] = $user_id;
					$user_object = new WP_User( $user_id );

					if( $created || $update_roles_existing_users == 'yes'){
						if(!( in_array("administrator", acui_get_roles($user_id), FALSE) || is_multisite() && is_super_admin( $user_id ) )){
							
							$default_roles = $user_object->roles;
							foreach ( $default_roles as $default_role ) {
								$user_object->remove_role( $default_role );
							}
							
/*							if( !empty( $role ) ){
								if( is_array( $role ) ){
									foreach ($role as $single_role) {
										$user_object->add_role( $single_role );
									}	
								}
								else{
									$user_object->add_role( $role );
								}
							}*/
						}
					}

					if($columns > 2) {
						//*********************************************
						// Walk the columns of each row
						//*********************************************
						for( $i=0 ; $i<$columns; $i++ ):
							if( !empty( $data ) ){
								if( strtolower( $headers[ $i ] ) == "password" ){ // passwords -> continue
									continue;
								}
								elseif( strtolower( $headers[ $i ] ) == "user_pass" ){ // hashed pass
							        $wpdb->update( $wpdb->users, array( 'user_pass' => $data[ $i ] ), array( 'ID' => $user_id ) );
								}
								elseif( in_array( $headers[ $i ], $wp_users_fields ) ){ // wp_user data
									if( empty( $data[ $i ] ) && $empty_cell_action == "leave" )
										continue;
									else
										//$wp_users_fields = array( "id", "user_nicename", "user_url", "display_name", "nickname", "first_name", "last_name", "description", "jabber", "aim", "yim", "user_registered", "password", "user_pass" );
										//****************************************************************
										// Is this a user data field (not a user meta field), handle here
										//****************************************************************
										error_log(print_r("wp_update_user: " . $headers[$i] . " => ". $data[$i], true));
										wp_update_user( array( 'ID' => $user_id, $headers[$i] => $data[$i] ) );
								}
								// $broadmoor_users_fields = array("HomeEmail", "TypeDesc", "FirstName", "LastName" );
								elseif( in_array( $headers[ $i ], $broadmoor_users_fields ) ){ // wp_user data
									if( empty( $data[ $i ] ) && $empty_cell_action == "leave" )
										continue;
									else
										//*****************************************************
										// Process user meta column (non meta is handled above)
										//*****************************************************
										//error_log(print_r(">> user_fields switch: " . $headers[$i] . ", value: " . $data[$i], true));
										switch ( $headers[ $i ] ){
											case 'TypeDesc':
												$wp_role = type_to_role($data[ $i ]);
												if( !in_array( 'grp_name', $headers ) ) {
													type_to_list($user_id, $data[$i], $data[31], $data[5]);
												}
/*												switch ($wp_role) {
													case 'Non-Owner':
													case 'Renters':
														$send_email = false;
														break;
												}*/
												error_log(print_r("setting role to " . $wp_role . ", for userid: " . $user_id . ", send_email: " . $send_email . ", input role: " . $data[$i], true));
												wp_update_user( array( 'ID' => $user_id, 'role' => $wp_role) );
												//wp_update_user( array( 'ID' => $user_id, 'role' => strtolower($wp_role)) );
												//$user_id_role = new WP_User($user_id);
												//$user_id_role->set_role(strtolower($wp_role));												//userMetaFieldChanged($user_id, "role", $all_user_meta, strtolower($wp_role), false);
												update_user_meta( $user_id, "role", strtolower($wp_role) );
												update_user_meta($user_id, "clubtec_account_type", $data[$i]);
												$member_base = explode("-", $data[1]);
												add_children($user_id, $file, $row, $member_base[0]);
												add_spouse($user_id, $file, $row, $data);
												break;
											case 'FirstName':
												//error_log(print_r("FirstName #1 " . $data[$i] . ", nickname: " . $nickname . ", display_name: " . $displayname . ", composeUsername: " . composeUsername($data), true));
												//error_log(print_r($user_object, true));
												//wp_update_user( array( 'ID' => $user_id, 'first_name' => clean_name($data[$i]) ) );
												if ($user_object->first_name != $data[$i]) {
													wp_update_user( array( 'ID' => $user_id, 'first_name' => $data[ $i ] ) );
												}
												break;
											case 'LastName':
												//error_log(print_r("LastName: " . $data[ $i ], true ));
												if ($user_object->last_name != $data[$i]) {
													wp_update_user( array( 'ID' => $user_id, 'last_name' => $data[ $i ] ) );
												}
												$nickname = $data[$i-1][0] . $data[$i];
												$displayname = clean_name($data[ $i-1 ]) . ' ' . $data[$i];
												//error_log(print_r("FirstName #2 " . $data[$i-1] . ", nickname: " . $nickname . ", display_name: " . $displayname . ", composeUsername: " . composeUsername($data), true));
												//wp_update_user( array( 'ID' => $user_id, 'first_name' => clean_name($data[$i-1])) );
												if ($user_object->display_name != $displayname) {
													wp_update_user( array( 'ID' => $user_id, 'display_name' => $displayname ) );
												}
												if ($user_object->nickname != $nickname) {
													wp_update_user( array( 'ID' => $user_id, 'nickname' => $nickname ) );
												}
												$username = composeUsername($data);
												if ($user_object->user_login != $username) {
													wp_update_user( array( 'ID' => $user_id, 'user_login' => $username ) );
												}
												break;
											case 'HomeEmail':
												// if one of the existing user emails is NOT already used as the primary email, set home email to primary
												error_log(print_r(" HomeEmail, existing user_email: " . $user_object->user_email, true));
												if (!existing_email($user_object->user_email, $data)) {
													wp_update_user( array( 'ID' => $user_id, 'user_email' => grab_email($data) ) );
												}
												update_user_meta($user_id, "home_email", $data[$i]);
												break;
											case 'account_type':
												$wp_role = type_to_role($data[ $i ]);
												wp_update_user( array( 'ID' => $user_id, 'role' => $wp_role) );
												update_user_meta( $user_id, "role", strtolower($wp_role) );
												update_user_meta($user_id, "clubtec_account_type", $data[$i]);
												//userMetaFieldChanged($user_id, "role", $all_user_meta, strtolower($wp_role), false);
												//userMetaFieldChanged($user_id, "clubtec_account_type", $all_user_meta, $data[$i], false);
												break;
											case 'usr_email':
												error_log(print_r(" usr_email, existing user_email: " . $user_object->user_email, true));
												if (!existing_email($user_object->user_email, $data)) {
													wp_update_user( array( 'ID' => $user_id, 'user_email' => grab_email($data) ) );
												}
												break;
											case 'usr_login':
												wp_update_user( array( 'ID' => $user_id, 'user_login' => $data[ $i ] ) );
												break;
											case 'usr_firstname':
												wp_update_user( array( 'ID' => $user_id, 'first_name' => $data[ $i ] ) );
												wp_update_user( array( 'ID' => $user_id, 'display_name' => clean_name($data[ $i ]) . ' ' . $data[$i + 1] . ' ' . $data[$i + 2] ) );
												wp_update_user( array( 'ID' => $user_id, 'nickname' => $data[ $i ][0] . $data[$i + 2] ) );
												break;
											case 'usr_lastname':
												wp_update_user( array( 'ID' => $user_id, 'last_name' => $data[ $i ] ) );
												break;
										}
								}
								elseif( strtolower( $headers[ $i ] ) == "wp-access-areas" && is_plugin_active( 'wp-access-areas/wp-access-areas.php' ) ){ // wp-access-areas
									$active_labels = array_map( 'trim', explode( "#", $data[ $i ] ) );

									foreach( $wpaa_labels as $wpa_label ){
										if( in_array( $wpa_label->cap_title , $active_labels )){
											acui_set_cap_for_user( $wpa_label->capability , $user_object , true );
										}
										else{
											acui_set_cap_for_user( $wpa_label->capability , $user_object , false );
										}
									}
								}
								elseif( in_array( $headers[ $i ], $buddypress_fields ) ){ // buddypress
									xprofile_set_field_data( $headers[ $i ], $user_id, $data[ $i ] );
								} 
								else{ // wp_usermeta data
									
									if( $data[ $i ] === '' ){
										if( $empty_cell_action == "delete" )
											delete_user_meta( $user_id, $headers[ $i ] );
										else
											continue;	
									} else {
										//error_log(print_r(">> Misc fields " . $headers[$i] . ', data: ' . $data[$i], true));
										switch ($headers[$i]) {
											case "MemberNumber":
											    //error_log(print_r("MemberNumber " . $data[$i], true));
												update_user_meta($user_id, "membership_number", $data[$i]);
												update_user_meta($user_id, "show_admin_bar_front", "false");
												break;
											case "grp_name":
												//error_log(print_r("grp_name found", true));
												grp_name_to_list($user_id, $data[$i], $data[7]);
												break;
											case "Gender":
												$gender = 'male';
												$genderCaps = 'Male';
												$genderEnum = '"a:1:{i:0;s:4:"Male";}"';
												if ($data[$i] == 'F') {
													$gender = 'female';
													$genderCaps = 'Female';
													$genderEnum = 'a:1:{i:0;s:6:"Female";}';
												}
												//error_log(print_r("Gender " . $data[$i] . " convert to: " . $genderCaps, true));
												update_user_meta($user_id, "gender", $genderCaps);
												//update_user_meta($user_id, "Gender", $genderCaps);
												break;
											case 'BusinessEmail':
												update_user_meta($user_id, "business_email", $data[$i]);
												break;
											case 'CorrEmail':
												update_user_meta($user_id, "corr_email", $data[$i]);
												break;
											case "Relationship":
												update_user_meta($user_id, "relationship", $data[$i]);
												break;
											case "Title":
												update_user_meta($user_id, "title", $data[$i]);
												break;
											case "Birthdate":
												update_user_meta($user_id, "birthday", $data[$i]);
												break;
											case "HomePhone":
												update_user_meta($user_id, "home_phone", $data[$i]);
												break;
											case "usr_cell_phone":
											case "MobilePhone":
												update_user_meta($user_id, "mobile_phone", $data[$i]);
												break;
											case "usr_fax":
											case "HomeFax":
												update_user_meta($user_id, "home_fax", $data[$i]);
												break;
											case "usr_company":
											case "BusinessCompany":
												update_user_meta($user_id, "company", $data[$i]);
												break;
											case "usr_jobtitle":
											case "Occupation":
												update_user_meta($user_id, "job_title", $data[$i]);
												break;
											case "usr_address":
											case "HomeAddress1":
												update_user_meta($user_id, "home_address", $data[$i]);
												break;
											case "usr_address2":
											case "HomeAddress2":
												update_user_meta($user_id, "home_address_2", $data[$i]);
												break;
											case "usr_state":
											case "HomeState":
												update_user_meta($user_id, "home_state", $data[$i]);
												break;
											case "usr_city":
											case "HomeCity":
												update_user_meta($user_id, "home_city", $data[$i]);
												break;
											case "usr_zip":
											case "HomeZip":
												update_user_meta($user_id, "home_zip", $data[$i]);
												break;
											case "usr_address_a":
											case "BusinessAddress1":
												update_user_meta($user_id, "work_address", $data[$i]);
												break;
											case "usr_address2_a":
											case "BusinessAddress2":
												update_user_meta($user_id, "work_address 2", $data[$i]);
												break;
											case "usr_state_a":
											case "BusinessState":
												update_user_meta($user_id, "work_state", $data[$i]);
												break;
											case "usr_city_a":
											case "BusinessCity":
												update_user_meta($user_id, "work_city", $data[$i]);
												break;
											case "usr_zip_a":
											case "BusinessZip":
												update_user_meta($user_id, "work_zip", $data[$i]);
												break;
											case "usr_phone_a":
											case "BusinessPhone":
												update_user_meta($user_id, "work_phone", $data[$i]);
												break;
											case "usr_fax_a":
											case "BusinessFax":
												update_user_meta($user_id, "work_fax", $data[$i]);
												break;
											case "usr_logon_count":
												update_user_meta($user_id, "clubtec_login_count", $data[$i]);
												break;
											case "usr_family_id":
												update_user_meta($user_id, "family_id", $data[$i]);
												break;
											case "grp_name":
												update_user_meta($user_id, "groups", $data[$i]);
												break;
										}  // switch
										//update_user_meta( $user_id, $headers[ $i ], $data[ $i ] );
									}
								}
							}
						endfor;
					}

/*					$styles = "";
					if( $problematic_row )
						$styles = "background-color:red; color:white;";

					echo "<tr style='$styles' ><td>" . ($row - 1) . "</td>";
					$logmsg = '';
					foreach ($data as $key => $element) {
						//error_log(print_r("Report, key " . $headers[$key] . ', element: ' . $element, true));
						if (in_array($headers[$key], $report_fields)) {
							//error_log(print_r("Match >> " . $headers[$key] . ', element: ' . $element, true));
							echo "<td>$element</td>";
							$logmsg .= $element . " ";
						}
					}
					echo "</tr>\n";

					$logger->AddRow($logmsg);
					$logger->Commit();
					$logmsg = '';*/

					// write insert record
/*					if ($doing_create) {
						write_import_summary_line('i', $data[1], $data[2] . " " . $data[3]);
					}*/

					flush();

					$mail_for_this_user = false;
					if( $created )
						$mail_for_this_user = true;
					else{
						if( !$is_cron && isset( $form_data["send_email_updated"] ) && $form_data["send_email_updated"] )
							$mail_for_this_user = true;
						else if( $is_cron && get_option( "acui_send_mail_cron" ) )
							$mail_for_this_user = true;
					}
						
					// send mail
					if( isset( $form_data["sends_email"] ) && $form_data["sends_email"] && $mail_for_this_user ):
						$key = get_password_reset_key( $user_object );
						$user_login= $user_object->user_login;
						
						$body_mail = get_option( "acui_mail_body" );
						$subject = get_option( "acui_mail_subject" );
												
						$body_mail = str_replace( "**loginurl**", "<a href='" . home_url() . "/wp-login.php" . "'>" . home_url() . "/wp-login.php" . "</a>", $body_mail );
						$body_mail = str_replace( "**username**", $user_login, $body_mail );
						$body_mail = str_replace( "**lostpasswordurl**", wp_lostpassword_url(), $body_mail );
						
						if( !is_wp_error( $key ) )
							$body_mail = str_replace( "**passwordreseturl**", network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ), 'login' ), $body_mail );
						
						if( empty( $password ) && !$created ) 
							$password = __( 'Password has not been changed', 'import-users-from-csv-with-meta' );

						$body_mail = str_replace("**password**", $password, $body_mail);
						$body_mail = str_replace("**email**", $email, $body_mail);

						foreach ( $wp_users_fields as $wp_users_field ) {								
							if( $positions[ $wp_users_field ] != false && $wp_users_field != "password" ){
								$body_mail = str_replace("**" . $wp_users_field .  "**", $data[ $positions[ $wp_users_field ] ] , $body_mail);							
							}
						}

						for( $i = 0 ; $i < count( $headers ); $i++ ) {
							$body_mail = str_replace("**" . $headers[ $i ] .  "**", $data[ $i ] , $body_mail);							
						}

						if( !get_option('acui_automattic_wordpress_email') ){
							add_filter( 'send_email_change_email', '__return_false' );
							add_filter( 'send_password_change_email', '__return_false' );
						}
						
						$body_mail = wpautop( $body_mail );

						add_filter( 'wp_mail_content_type', 'cod_set_html_content_type' );

						if( get_option( "acui_settings" ) == "plugin" ){
							add_action( 'phpmailer_init', 'acui_mailer_init' );
							add_filter( 'wp_mail_from', 'acui_mail_from' );
							add_filter( 'wp_mail_from_name', 'acui_mail_from_name' );

							//error_log(print_r("about to call wp_mail(1), send_email:  " . $send_email, true));
							if ($send_email) {
								wp_mail( $email, $subject, $body_mail );
							}

							remove_filter( 'wp_mail_from', 'acui_mail_from' );
							remove_filter( 'wp_mail_from_name', 'acui_mail_from_name' );
							remove_action( 'phpmailer_init', 'acui_mailer_init' );
						}
						else
							//error_log(print_r("about to call wp_mail(2), send_email:  " . $send_email, true));
							if ($send_email) {
								//error_log(print_r("doing send...", true));
								wp_mail( $email, $subject, $body_mail );
							}

						remove_filter( 'wp_mail_content_type', 'cod_set_html_content_type' );

						if( !get_option('acui_automattic_wordpress_email') ){
							remove_filter( 'send_email_change_email', '__return_false' );
							remove_filter( 'send_password_change_email', '__return_false' );
						}

					endif;

				endif;

				$row++;						
			endwhile;

			//*********************************************
			// Preview - check for deletes
			//*********************************************
			// Create array of member_numbers in WordPress DB
			$wp_member_numbers = [];
			$all_users = get_users(array('fields' => array('ID')));
			foreach ($all_users as &$value) {
				$meta = get_user_meta($value->ID, 'membership_number');
				//error_log(print_r($value->ID . " > " . $meta[0], true));
				if ($meta && $meta[0]) {
					array_push($wp_member_numbers, $meta[0]);
				}
			}
			$deleted_members = array_diff($wp_member_numbers, $csv_member_numbers);

			if (isset($form_data["preview"])) {
				$new_members = array_diff($csv_member_numbers, $wp_member_numbers);

				echo "<table><tbody>";
				// Display the new member entries
				foreach ($new_members as $key => $value) {
					$manager->seek($key);
					$data = $manager->fgetcsv( $delimiter );
					$logger->AddRow("+++ New " . $value . ", " . $data[2] . " " . $data[3]);
					error_log(print_r("+++ New " . $value . ", " . $data[2] . " " . $data[3], true));
					write_import_summary_line('i', $value, $data[2] . " " . $data[3]);
				}
				$logger->Commit();

				echo_deleted_members($deleted_members);

				echo "</tbody></>";
			} else {
				// display deleted members for normal (non-preview) run
				$deleted_members = array_diff($wp_member_numbers, $csv_member_numbers);
				echo_deleted_members($deleted_members);
			}

			if( $attach_id != 0 )
				wp_delete_attachment( $attach_id );

			// delete all users that have not been imported
/*			if( $is_cron && get_option( "acui_cron_delete_users" ) ):
				$all_users = get_users( array( 'fields' => array( 'ID' ) ) );
				$cron_delete_users_assign_posts = get_option( "acui_cron_delete_users_assign_posts");
				
				foreach ( $all_users as $user ) {
					if( !in_array( $user->ID, $users_registered ) ){
						if( !empty( $cron_delete_users_assign_posts ) && get_userdata( $cron_delete_users_assign_posts ) !== false ){
							wp_delete_user( $user->ID, $cron_delete_users_assign_posts );
						}
						else{
							wp_delete_user( $user->ID );
						}						
					}
				}
			endif;*/

			?>
			</table>
			<br/>
			<p><?php _e( 'Process finished you can go', 'import-users-from-csv-with-meta' ); ?> <a href="<?php echo get_admin_url() . '/users.php'; ?>"><?php _e( 'here to see results', 'import-users-from-csv-with-meta' ); ?></a></p>
			<?php
			ini_set('auto_detect_line_endings',FALSE);
			
			do_action( 'after_acui_import_users' );
		?>
	</div>
<?php
}

function acui_options() 
{
	global $url_plugin;

	if ( !current_user_can('create_users') ) {
		wp_die( __( 'You are not allowed to see this content.', 'import-users-from-csv-with-meta' ));
	}

	if ( isset ( $_GET['tab'] ) ) 
		$tab = $_GET['tab'];
   	else 
   		$tab = 'homepage';


	if( isset( $_POST ) && !empty( $_POST ) ):
		switch ( $tab ){
      		case 'homepage':
      			acui_fileupload_process( $_POST, false );

      			return;
      		break;

      		case 'mail-options':
      			acui_save_mail_template( $_POST );
      		break;

      		case 'cron':
      			acui_manage_cron_process( $_POST );
      		break;

      	}
      	
	endif;
	
	if ( isset ( $_GET['tab'] ) ) 
		acui_admin_tabs( $_GET['tab'] ); 
	else
		acui_admin_tabs('homepage');
	
  	switch ( $tab ){
      case 'homepage' :
		
	$args_old_csv = array( 'post_type'=> 'attachment', 'post_mime_type' => 'text/csv', 'post_status' => 'inherit', 'posts_per_page' => -1 );
	$old_csv_files = new WP_Query( $args_old_csv );

	acui_check_options();
?>
	<div class="wrap">	

		<?php if( $old_csv_files->found_posts > 0 ): ?>
		<div class="postbox">
		    <div title="<?php _e( 'Click to open/close', 'import-users-from-csv-with-meta' ); ?>" class="handlediv">
		      <br>
		    </div>

		    <h3 class="hndle"><span>&nbsp;<?php _e( 'Old CSV files uploaded', 'import-users-from-csv-with-meta' ); ?></span></h3>

		    <div class="inside" style="display: block;">
		    	<p><?php _e( 'For security reasons you should delete this files, probably they would be visible in the Internet if a bot or someone discover the URL. You can delete each file or maybe you want delete all CSV files you have uploaded:', 'import-users-from-csv-with-meta' ); ?></p>
		    	<input type="button" value="<?php _e( 'Delete all CSV files uploaded', 'import-users-from-csv-with-meta' ); ?>" id="bulk_delete_attachment" style="float:right;" />
		    	<ul>
		    		<?php while($old_csv_files->have_posts()) : 
		    			$old_csv_files->the_post(); 

		    			if( get_the_date() == "" )
		    				$date = "undefined";
		    			else
		    				$date = get_the_date();
		    		?>
		    		<li><a href="<?php echo wp_get_attachment_url( get_the_ID() ); ?>"><?php the_title(); ?></a> _e( 'uploaded on', 'import-users-from-csv-with-meta' ) . ' ' . <?php echo $date; ?> <input type="button" value="<?php _e( 'Delete', 'import-users-from-csv-with-meta' ); ?>" class="delete_attachment" attach_id="<?php the_ID(); ?>" /></li>
		    		<?php endwhile; ?>
		    		<?php wp_reset_postdata(); ?>
		    	</ul>
		        <div style="clear:both;"></div>
		    </div>
		</div>
		<?php endif; ?>	

		<div style="float:left; width:80%;">
			<h2><?php _e( 'Import users from ClubTec CSV (v2.01, Mar 9, 2017)','import-users-from-csv-with-meta' ); ?></h2>
		</div>

		<div style="clear:both;"></div>

		<div style="width:100%;">
			<form method="POST" enctype="multipart/form-data" action="" accept-charset="utf-8" onsubmit="return check();">
			<table class="form-table">
				<tbody>
				<tr class="form-field form-required">
					<h4><?php _e( 'Preview only?', 'import-users-from-csv-with-meta' ); ?> <input type="checkbox" name="preview" value = "<?php _e( 'yes', 'import-users-from-csv-with-meta' ); ?>" ></h4>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label><?php _e( 'CSV file <span class="description">(required)</span></label>', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<div id="upload_file">
							<input type="file" name="uploadfiles[]" id="uploadfiles" size="35" class="uploadfiles" />
							<?php _e( '<em>or you can choose directly a file from your host,', 'import-users-from-csv-with-meta' ) ?> <a href="#" class="toggle_upload_path"><?php _e( 'click here', 'import-users-from-csv-with-meta' ) ?></a>.</em>
						</div>
						<div id="introduce_path" style="display:none;">
							<input placeholder="<?php _e( 'You have to introduce the path to file, i.e.:' ,'import-users-from-csv-with-meta' ); ?><?php $upload_dir = wp_upload_dir(); echo $upload_dir["path"]; ?>/test.csv" type="text" name="path_to_file" id="path_to_file" value="<?php echo dirname( __FILE__ ); ?>/test.csv" style="width:70%;" />
							<em><?php _e( 'or you can upload it directly from your PC', 'import-users-from-csv-with-meta' ); ?>, <a href="#" class="toggle_upload_path"><?php _e( 'click here', 'import-users-from-csv-with-meta' ); ?></a>.</em>
						</div>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label><?php _e( 'Update existing users?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<select name="update_existing_users">
							<option value="yes"><?php _e( 'Yes', 'import-users-from-csv-with-meta' ); ?></option>
							<option value="no"><?php _e( 'No', 'import-users-from-csv-with-meta' ); ?></option>
						</select>
					</td>
				</tr>

				<tr class="form-field form-required">
					<th scope="row"><label><?php _e( 'What should the plugin do with empty cells?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<select name="empty_cell_action">
							<option value="leave"><?php _e( 'Leave the old value for this metadata', 'import-users-from-csv-with-meta' ); ?></option>
							<option value="delete"><?php _e( 'Delete the metadata', 'import-users-from-csv-with-meta' ); ?></option>
						</select>
					</td>
				</tr>

				<?php if( is_plugin_active( 'buddypress/bp-loader.php' ) ):

					if( !class_exists( "BP_XProfile_Group" ) ){
						require_once( WP_PLUGIN_DIR . "/buddypress/bp-xprofile/classes/class-bp-xprofile-group.php" );
					}

					$buddypress_fields = array();
					$profile_groups = BP_XProfile_Group::get( array( 'fetch_fields' => true	) );

					if ( !empty( $profile_groups ) ) {
						 foreach ( $profile_groups as $profile_group ) {
							if ( !empty( $profile_group->fields ) ) {				
								foreach ( $profile_group->fields as $field ) {
									$buddypress_fields[] = $field->name;
								}
							}
						}
					}
				?>

				<tr class="form-field form-required">
					<th scope="row"><label><?php _e( 'BuddyPress users', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td><?php _e( 'You can insert any profile from BuddyPress using his name as header. Plugin will check, before import, which fields are defined in BuddyPress and will assign it in the update. You can use this fields:', 'import-users-from-csv-with-meta' ); ?>
					<ul style="list-style:disc outside none;margin-left:2em;">
						<?php foreach ($buddypress_fields as $buddypress_field ): ?><li><?php echo $buddypress_field; ?></li><?php endforeach; ?>
					</ul>
					<?php _e( 'Remember that all date fields have to be imported using a format like this: 2016-01-01 00:00:00', 'import-users-from-csv-with-meta' ); ?>

					<p class="description"><strong>(<?php _e( 'Only for', 'import-users-from-csv-with-meta' ); ?> <a href="https://wordpress.org/plugins/buddypress/">BuddyPress</a> <?php _e( 'users', 'import-users-from-csv-with-meta' ); ?></strong>.)</p>
					</td>					
				</tr>

				<?php endif; ?>

				<?php if( is_plugin_active( 'wp-members/wp-members.php' ) ): ?>

				<tr class="form-field form-required">
					<th scope="row"><label>Y</label></th>
					<td>
						<select name="activate_users_wp_members">
							<option value="no_activate"><?php _e( 'Do not activate users', 'import-users-from-csv-with-meta' ); ?></option>
							<option value="activate"><?php _e( 'Activate users when they are being imported', 'import-users-from-csv-with-meta' ); ?></option>
						</select>

						<p class="description"><strong>(<?php _e( 'Only for', 'import-users-from-csv-with-meta' ); ?> <a href="https://wordpress.org/plugins/wp-members/"><?php _e( 'WP Members', 'import-users-from-csv-with-meta' ); ?></a> <?php _e( 'users', 'import-users-from-csv-with-meta' ); ?>)</strong>.</p>
					</td>
					
				</tr>

				<?php endif; ?>

				<?php if( is_plugin_active( 'new-user-approve/new-user-approve.php' ) ): ?>

				<tr class="form-field form-required">
					<th scope="row"><label><?php _e( 'Approve users at the same time is being created', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<select name="approve_users_new_user_appove">
							<option value="no_approve"><?php _e( 'Do not approve users', 'import-users-from-csv-with-meta' ); ?></option>
							<option value="approve"><?php _e( 'Approve users when they are being imported', 'import-users-from-csv-with-meta' ); ?></option>
						</select>

						<p class="description"><strong>(<?php _e( 'Only for', 'import-users-from-csv-with-meta' ); ?> <a href="https://es.wordpress.org/plugins/new-user-approve/"><?php _e( 'New User Approve', 'import-users-from-csv-with-meta' ); ?></a> <?php _e( 'users', 'import-users-from-csv-with-meta' ); ?></strong>.</p>
					</td>
					
				</tr>

				<?php endif; ?>

				<?php if( is_plugin_active( 'allow-multiple-accounts/allow-multiple-accounts.php' ) ): ?>

				<tr class="form-field form-required">
					<th scope="row"><label><?php _e( 'Repeated email in different users?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<select name="allow_multiple_accounts">
							<option value="not_allowed"><?php _e( 'Not allowed', 'import-users-from-csv-with-meta' ); ?></option>
							<option value="allowed"><?php _e( 'Allowed', 'import-users-from-csv-with-meta' ); ?></option>
						</select>
						<p class="description"><strong>(<?php _e( 'Only for', 'import-users-from-csv-with-meta' ); ?> <a href="https://wordpress.org/plugins/allow-multiple-accounts/"><?php _e( 'Allow Multiple Accounts', 'import-users-from-csv-with-meta' ); ?></a> <?php _e( 'users', 'import-users-from-csv-with-meta'); ?>)</strong>. <?php _e('Allow multiple user accounts to be created having the same email address.','import-users-from-csv-with-meta' ); ?></p>
					</td>
				</tr>

				<?php endif; ?>

				<?php if( is_plugin_active( 'wp-access-areas/wp-access-areas.php' ) ): ?>

				<tr class="form-field form-required">
					<th scope="row"><label><?php _e('WordPress Access Areas is activated','import-users-from-csv-with-meta'); ?></label></th>
					<td>
						<p class="description"><?php _e('As user of','import-users-from-csv-with-meta' ); ?> <a href="https://wordpress.org/plugins/wp-access-areas/"><?php _e( 'WordPress Access Areas', 'import-users-from-csv-with-meta' )?></a> <?php _e( 'you can use the Access Areas created', 'import-users-from-csv-with-meta' ); ?> <a href="<?php echo admin_url( 'users.php?page=user_labels' ); ?>"><?php _e( 'here', 'import-users-from-csv-with-meta' ); ?></a> <?php _e( 'and use this areas in your own CSV file. Please use the column name <strong>wp-access-areas</strong> and in each row use <strong>the name that you have used', 'import-users-from-csv-with-meta' ); ?> <a href="<?php echo admin_url( 'users.php?page=user_labels' ); ?>"><?php _e( 'here', 'import-users-from-csv-with-meta' ); ?></a></strong><?php _e( ', like this ones:', 'import-users-from-csv-with-meta' ); ?></p>
						<ol>
							<?php 
								$data = WPAA_AccessArea::get_available_userlabels( '0,5' , NULL ); 								
								foreach ( $data as $access_area_object ): ?>
									<li><?php echo $access_area_object->cap_title; ?></li>
							<?php endforeach; ?>

						</ol>
						<p class="description"><?php _e( "If you leave this cell empty for some user or the access area indicated doesn't exist, user won't be assigned to any access area. You can choose more than one area for each user using pads between them in the same row, i.e.: ", 'import-users-from-csv-with-meta' ) ?>access_area1#accces_area2</p>
					</td>
				</tr>

				<?php endif; ?>

				<tr class="form-field">
					<th scope="row"><label for="user_login"><?php _e( 'Send mail', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<p><?php _e( 'Do you wish to send a mail with credentials and other data?', 'import-users-from-csv-with-meta' ); ?> <input type="checkbox" name="sends_email" value = "<?php _e('yes','import-users-from-csv-with-meta'); ?>"></p>
						<p><?php _e( 'Do you wish to send this mail also to users that are being updated? (not only to the one which are being created)', 'import-users-from-csv-with-meta' ); ?> <input type="checkbox" name="send_email_updated" value = "<?php _e( 'yes', 'import-users-from-csv-with-meta' ); ?>" ></p>
					</td>
				</tr>
				</tbody>
			</table>

			<?php wp_nonce_field( 'acui-import', 'acui-nonce' ); ?>

			<input class="button-primary" type="submit" name="uploadfile" id="uploadfile_btn" value="<?php _e( 'Start importing', 'import-users-from-csv-with-meta' ); ?>"/>
			</form>
<!--			<h3>Status Log</h3>
			<div id="debug-log" class="mc4wp-log widefat"
				 style="height: 212px; width: 1100px; font-family: monaco,monospace,courier,'courier new','Bitstream Vera Sans Mono'; font-size: 13px; resize: vertical; line-height: 140%; padding: 6px; background: #262626; color: #fff">
				<?php
/*				$log_reader = new MC4WP_Debug_Log_Reader( '/var/www/html/wp-content/import.log' );
				$line = $log_reader->read_as_html();

				if (!empty($line)) {
					while (is_string($line)) {
						echo '<div class="debug-log-line">' . $line . '</div>';
						$line = $log_reader->read_as_html();
					}
				} else {
					echo '<div class="debug-log-empty">';
					echo '-- ' . __('Nothing here.', 'import-clubtec');
					echo '</div>';
				}
				*/?>
			</div>
-->		</div>

	</div>
	<script type="text/javascript">
	function check(){
		if(document.getElementById("uploadfiles").value == "" && jQuery( "#upload_file" ).is(":visible") ) {
		   alert("<?php _e( 'Please choose a file', 'import-users-from-csv-with-meta' ); ?>");
		   return false;
		}

		if( jQuery( "#path_to_file" ).val() == "" && jQuery( "#introduce_path" ).is(":visible") ) {
		   alert("<?php _e( 'Please enter a path to the file', 'import-users-from-csv-with-meta' ); ?>");
		   return false;
		}
	}

	jQuery( document ).ready( function( $ ){
		$( ".delete_attachment" ).click( function(){
			var answer = confirm( "<?php _e( 'Are you sure to delete this file?', 'import-users-from-csv-with-meta' ); ?>" );
			if( answer ){
				var data = {
					'action': 'acui_delete_attachment',
					'attach_id': $( this ).attr( "attach_id" )
				};

				$.post(ajaxurl, data, function(response) {
					if( response != 1 )
						alert( "<?php _e( 'There were problems deleting the file, please check file permissions', 'import-users-from-csv-with-meta' ); ?>" );
					else{
						alert( "<?php _e( 'File successfully deleted', 'import-users-from-csv-with-meta' ); ?>" );
						document.location.reload();
					}
				});
			}
		});

		$( "#bulk_delete_attachment" ).click( function(){
			var answer = confirm( "<?php _e( 'Are you sure to delete ALL CSV files uploaded? There can be CSV files from other plugins.', 'import-users-from-csv-with-meta' ); ?>" );
			if( answer ){
				var data = {
					'action': 'acui_bulk_delete_attachment',
				};

				$.post(ajaxurl, data, function(response) {
					if( response != 1 )
						alert( "<?php _e( 'There were problems deleting the files, please check files permissions', 'import-users-from-csv-with-meta' ); ?>" );
					else{
						alert( "<?php _e( 'Files successfully deleted', 'import-users-from-csv-with-meta' ); ?>" );
						document.location.reload();
					}
				});
			}
		});

		$( ".toggle_upload_path" ).click( function( e ){
			e.preventDefault();

			$("#upload_file,#introduce_path").toggle();
		} );

	} );
	</script>

	<?php 

	break;

	case 'columns':

	$headers = get_option("acui_columns"); 
	?>

		<h3><?php _e( 'Custom columns loaded', 'import-users-from-csv-with-meta' ); ?></h3>
		<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e( 'Columns loaded in previous files', 'import-users-from-csv-with-meta' ); ?></th>
				<td><small><em><?php _e( '(if you load another CSV with different columns, the new ones will replace this list)', 'import-users-from-csv-with-meta' ); ?></em></small>
					<ol>
						<?php 
						if( is_array( $headers ) && count( $headers ) > 0 ):
							foreach ($headers as $column): ?>
							<li><?php echo $column; ?></li>
						<?php endforeach;  ?>
						
						<?php else: ?>
							<li><?php _e( 'There is no columns loaded yet', 'import-users-from-csv-with-meta' ); ?></li>
						<?php endif; ?>
					</ol>
				</td>
			</tr>
		</tbody></table>

		<?php 

		break;

		case 'doc':

		?>

		<h3><?php _e( 'Documentation', 'import-users-from-csv-with-meta' ); ?></h3>
		<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e( 'Columns position', 'import-users-from-csv-with-meta' ); ?></th>
				<td><small><em><?php _e( '(Documents should look like the one presented into screenshot. Remember you should fill the first two columns with the next values)', 'import-users-from-csv-with-meta' ); ?></em></small>
					<ol>
						<li><?php _e( 'Username', 'import-users-from-csv-with-meta' ); ?></li>
						<li><?php _e( 'Email', 'import-users-from-csv-with-meta' ); ?></li>
					</ol>						
					<small><em><?php _e( '(The next columns are totally customizable and you can use whatever you want. All rows must contains same columns)', 'import-users-from-csv-with-meta' ); ?></em></small>
					<small><em><?php _e( '(User profile will be adapted to the kind of data you have selected)', 'import-users-from-csv-with-meta' ); ?></em></small>
					<small><em><?php _e( '(If you want to disable the extra profile information, please deactivate this plugin after make the import)', 'import-users-from-csv-with-meta' ); ?></em></small>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'id', 'import-users-from-csv-with-meta' ); ?></th>
				<td><?php _e( 'You can use a column called id in order to make inserts or updates of an user using the ID used by WordPress in the wp_users table. We have two different cases:', 'import-users-from-csv-with-meta' ); ?>
					<ul style="list-style:disc outside none; margin-left:2em;">
						<li><?php _e( "If id <strong>doesn't exist in your users table</strong>: user will be inserted", 'import-users-from-csv-with-meta' ); ?></li>
						<li><?php _e( "If id <strong>exists</strong>: plugin check if username is the same, if yes, it will update the data, if not, it ignores the cell to avoid problems", 'import-users-from-csv-with-meta' ); ?></li>
					</ul>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( "Passwords", 'import-users-from-csv-with-meta' ); ?></th>
				<td><?php _e( "A string that contains user passwords. We have different options for this case:", 'import-users-from-csv-with-meta' ); ?>
					<ul style="list-style:disc outside none; margin-left:2em;">
						<li><?php _e( "If you <strong>don't create a column for passwords</strong>: passwords will be generated automatically", 'import-users-from-csv-with-meta' ); ?></li>
						<li><?php _e( "If you <strong>create a column for passwords</strong>: if cell is empty, password won't be updated; if cell has a value, it will be used", 'import-users-from-csv-with-meta' ); ?></li>
					</ul>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'WordPress default profile data', 'import-users-from-csv-with-meta' ); ?></th>
				<td><?php _e( "You can use those labels if you want to set data adapted to the WordPress default user columns (the ones who use the function", 'import-users-from-csv-with-meta' ); ?> <a href="http://codex.wordpress.org/Function_Reference/wp_update_user">wp_update_user</a>)
					<ol>
						<li><strong>user_nicename</strong>: <?php _e( "A string that contains a URL-friendly name for the user. The default is the user's username.", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>user_url</strong>: <?php _e( "A string containing the user's URL for the user's web site.", 'import-users-from-csv-with-meta' ); ?>	</li>
						<li><strong>display_name</strong>: <?php _e( "A string that will be shown on the site. Defaults to user's username. It is likely that you will want to change this, for both appearance and security through obscurity (that is if you don't use and delete the default admin user).", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>nickname</strong>: <?php _e( "The user's nickname, defaults to the user's username.", 'import-users-from-csv-with-meta' ); ?>	</li>
						<li><strong>usr_name</strong>: <?php _e( "The user's first name.", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>last_name</strong>: <?php _e("The user's last name.", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>description</strong>: <?php _e("A string containing content about the user.", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>jabber</strong>: <?php _e("User's Jabber account.", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>aim</strong>: <?php _e("User's AOL IM account.", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>yim</strong>: <?php _e("User's Yahoo IM account.", 'import-users-from-csv-with-meta' ); ?></li>
						<li><strong>user_registered</strong>: <?php _e( "Using the WordPress format for this kind of data Y-m-d H:i:s.", "import-users-from-csv-with-meta "); ?></li>
					</ol>
				</td>
			</tr>
			<?php if( is_plugin_active( 'woocommerce/woocommerce.php' ) ): ?>

				<tr valign="top">
					<th scope="row"><?php _e( "WooCommerce is activated", 'import-users-from-csv-with-meta' ); ?></th>
					<td><?php _e( "You can use those labels if you want to set data adapted to the WooCommerce default user columns", 'import-users-from-csv-with-meta' ); ?>
					<ol>
						<li>billing_first_name</li>
						<li>billing_last_name</li>
						<li>billing_company</li>
						<li>billing_address_1</li>
						<li>billing_address_2</li>
						<li>billing_city</li>
						<li>billing_postcode</li>
						<li>billing_country</li>
						<li>billing_state</li>
						<li>billing_phone</li>
						<li>billing_email</li>
						<li>shipping_first_name</li>
						<li>shipping_last_name</li>
						<li>shipping_company</li>
						<li>shipping_address_1</li>
						<li>shipping_address_2</li>
						<li>shipping_city</li>
						<li>shipping_postcode</li>
						<li>shipping_country</li>
						<li>shipping_state</li>
					</ol>
				</td>
				</tr>

				<?php endif; ?>
			<tr valign="top">
				<th scope="row"><?php _e( "Important notice", 'import-users-from-csv-with-meta' ); ?></th>
				<td><?php _e( "You can upload as many files as you want, but all must have the same columns. If you upload another file, the columns will change to the form of last file uploaded.", 'import-users-from-csv-with-meta' ); ?></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( "Any question about it", 'import-users-from-csv-with-meta' ); ?></th>
				<td>
					<ul style="list-style:disc outside none; margin-left:2em;">
						<li><?php _e( 'Free support (in WordPress forums):', 'import-users-from-csv-with-meta' ); ?> <a href="https://wordpress.org/support/plugin/import-users-from-csv-with-meta">https://wordpress.org/support/plugin/import-users-from-csv-with-meta</a>.</li>
						<li><?php _e( 'Premium support (with a quote):', 'import-users-from-csv-with-meta' ); ?> <a href="mailto:contacto@codection.com">contacto@codection.com</a>.</li>
					</ul>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e( 'Example', 'import-users-from-csv-with-meta' ); ?></th>
			<td><?php _e( 'Download this', 'import-users-from-csv-with-meta' ); ?> <a href="<?php echo plugins_url() . "/import-users-from-csv-with-meta/test.csv"; ?>">.csv <?php _e('file','import-users-from-csv-with-meta'); ?></a> <?php _e( 'to test', 'import-users-from-csv-with-meta' ); ?></td>
			</tr>
		</tbody>
		</table>
		<br/>
		<div style="width:775px;margin:0 auto"><img src="<?php echo plugins_url() . "/import-users-from-csv-with-meta/csv_example.png"; ?>"/></div>
	<?php break; ?>

	<?php case 'mail-options':
		$from_email = get_option( "acui_mail_from" );
		$from_name = get_option( "acui_mail_from_name" );
		$body_mail = get_option( "acui_mail_body" );
		$subject_mail = get_option( "acui_mail_subject" );
		$automattic_wordpress_email = get_option( "acui_automattic_wordpress_email" );
	?>
		<form method="POST" enctype="multipart/form-data" action="" accept-charset="utf-8">
		<h3><?php _e('Mail options','import-users-from-csv-with-meta'); ?></h3>

		<p class="description"><?php _e( 'You can set your own SMTP and other mail details', 'import-users-from-csv-with-meta' ); ?> <a href="<?php echo admin_url( 'tools.php?page=acui-smtp' ); ?>" target="_blank"><?php _e( 'here', 'import-users-from-csv-with-meta' ); ?></a>.
		
		<table class="optiontable form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><?php _e( 'WordPress automatic emails users updated', 'import-users-from-csv-with-meta' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php _e( 'Send automattic WordPress emails?', 'import-users-from-csv-with-meta' ); ?></span>
							</legend>
							<label for="automattic_wordpress_email">
								<select name="automattic_wordpress_email" id="automattic_wordpress_email">
									<option <?php if( $automattic_wordpress_email == 'false' ) echo "selected='selected'"; ?> value="false"><?php _e( "Deactivate WordPress automattic email when an user is updated or his password is changed", 'import-users-from-csv-with-meta' ) ;?></option>
									<option <?php if( $automattic_wordpress_email == 'true' ) echo "selected='selected'"; ?> value="true"><?php _e( 'Activate WordPress automattic email when an user is updated or his password is changed', 'import-users-from-csv-with-meta' ); ?></option>
								</select>
								<span class="description"><? _e( "When you update an user or change his password, WordPress prepare and send automattic email, you can deactivate it here.", 'import-users-from-csv-with-meta' ); ?></span>
							</label>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>	

		<h3><?php _e( 'Customize the email that can be sent when importing users', 'import-users-from-csv-with-meta' ); ?></h3>
		
		<p><?php _e( 'Mail subject :', 'import-users-from-csv-with-meta' ); ?><input name="subject_mail" size="100" value="<?php echo $subject_mail; ?>" id="title" autocomplete="off" type="text"></p>
		<?php wp_editor( $body_mail , 'body_mail'); ?>

		<br/>
		<input class="button-primary" type="submit" value="Save mail template"/>
		
		<p>You can use:</p>
		<ul style="list-style-type:disc; margin-left:2em;">
			<li>**username** = <?php _e( 'username to login', 'import-users-from-csv-with-meta' ); ?></li>
			<li>**password** = <?php _e( 'user password', 'import-users-from-csv-with-meta' ); ?></li>
			<li>**loginurl** = <?php _e( 'current site login url', 'import-users-from-csv-with-meta' ); ?></li>
			<li>**lostpasswordurl** = <?php _e( 'lost password url', 'import-users-from-csv-with-meta' ); ?></li>
			<li>**passwordreseturl** = <?php _e( 'password reset url', 'import-users-from-csv-with-meta' ); ?></li>
			<li>**email** = <?php _e( 'user email', 'import-users-from-csv-with-meta' ); ?></li>
			<li><?php _e( "You can also use any WordPress user standard field or an own metadata, if you have used it in your CSV. For example, if you have a first_name column, you could use **first_name** or any other meta_data like **my_custom_meta**", 'import-users-from-csv-with-meta' ) ;?></li>
		</ul>

		</form>

	<?php break; ?>

	<?php case 'cron':

	$cron_activated = get_option( "acui_cron_activated");
	$send_mail_cron = get_option( "acui_send_mail_cron");
	$send_mail_updated = get_option( "acui_send_mail_updated");
	$cron_delete_users = get_option( "acui_cron_delete_users");
	$cron_delete_users_assign_posts = get_option( "acui_cron_delete_users_assign_posts");
	$path_to_file = get_option( "acui_cron_path_to_file");
	$period = get_option( "acui_cron_period");
	$role = get_option( "acui_cron_role");
	$move_file_cron = get_option( "acui_move_file_cron");
	$path_to_move = get_option( "acui_cron_path_to_move");
	$log = get_option( "acui_cron_log");

	if( empty( $cron_activated ) )
		$cron_activated = false;

	if( empty( $send_mail_cron ) )
		$send_mail_cron = false;

	if( empty( $send_mail_updated ) )
		$send_mail_updated = false;

	if( empty( $cron_delete_users ) )
		$cron_delete_users = false;

	if( empty( $cron_delete_users_assign_posts ) )
		$cron_delete_users_assign_posts = '';

	if( empty( $path_to_file ) )
		$path_to_file = dirname( __FILE__ ) . '/test.csv';

	if( empty( $period ) )
		$period = 'hourly';

	if( empty( $move_file_cron ) )
		$move_file_cron = false;

	if( empty( $path_to_move ) )
		$path_to_move = dirname( __FILE__ ) . '/move.csv';

	if( empty( $log ) )
		$log = "No tasks done yet.";

	?>
		<h3><?php _e( "Execute an import of users periodically", 'import-users-from-csv-with-meta' ); ?></h3>

		<form method="POST" enctype="multipart/form-data" action="" accept-charset="utf-8">
			<table class="form-table">
				<tbody>
				<tr class="form-field">
					<th scope="row"><label for="path_to_file"><?php _e( "Path of file that are going to be imported", 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<input placeholder="<?php _e('Insert complete path to the file', 'import-users-from-csv-with-meta' ) ?>" type="text" name="path_to_file" id="path_to_file" value="<?php echo $path_to_file; ?>" style="width:70%;" />
						<p class="description"><?php _e( 'You have to introduce the path to file, i.e.:', 'import-users-from-csv-with-meta' ); ?> <?php $upload_dir = wp_upload_dir(); echo $upload_dir["path"]; ?>/test.csv</p>
					</td>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label for="period"><?php _e( 'Period', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>	
						<select id="period" name="period">
							<option <?php if( $period == 'hourly' ) echo "selected='selected'"; ?> value="hourly"><?php _e( 'Hourly', 'import-users-from-csv-with-meta' ); ?></option>
							<option <?php if( $period == 'twicedaily' ) echo "selected='selected'"; ?> value="twicedaily"><?php _e( 'Twicedaily', 'import-users-from-csv-with-meta' ); ?></option>
							<option <?php if( $period == 'daily' ) echo "selected='selected'"; ?> value="daily"><?php _e( 'Daily', 'import-users-from-csv-with-meta' ); ?></option>
						</select>
						<p class="description"><?php _e( 'How often the event should reoccur?', 'import-users-from-csv-with-meta' ); ?></p>
					</td>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label for="cron-activated"><?php _e( 'Activate periodical import?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<input type="checkbox" name="cron-activated" value="yes" <?php if( $cron_activated == true ) echo "checked='checked'"; ?>/>
					</td>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label for="send-mail-cron"><?php _e( 'Send mail when using periodical import?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<input type="checkbox" name="send-mail-cron" value="yes" <?php if( $send_mail_cron == true ) echo "checked='checked'"; ?>/>
					</td>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label for="send-mail-updated"><?php _e( 'Send mail also to users that are being updated?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<input type="checkbox" name="send-mail-updated" value="yes" <?php if( $send_mail_updated == true ) echo "checked='checked'"; ?>/>
					</td>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label for="cron-delete-users"><?php _e( 'Delete users that are not present in the CSV?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<div style="float:left;">
							<input type="checkbox" name="cron-delete-users" value="yes" <?php if( $cron_delete_users == true ) echo "checked='checked'"; ?>/>
						</div>
						<div style="margin-left:25px;">
							<select id="cron-delete-users-assign-posts" name="cron-delete-users-assign-posts">
								<?php
									if( $cron_delete_users_assign_posts == '' )
										echo "<option selected='selected' value=''>" . __( 'Delete posts of deled users without assing to any user', 'import-users-from-csv-with-meta' ) . "</option>";
									else
										echo "<option value=''>" . __( 'Delete posts of deled users without assing to any user', 'import-users-from-csv-with-meta' ) . "</option>";

									$blogusers = get_users();
									
									foreach ( $blogusers as $bloguser ) {
										if( $bloguser->ID == $cron_delete_users_assign_posts )
											echo "<option selected='selected' value='{$bloguser->ID}'>{$bloguser->display_name}</option>";
										else
											echo "<option value='{$bloguser->ID}'>{$bloguser->display_name}</option>";
									}
								?>
							</select>
							<p class="description"><?php _e( 'After delete users, we can choose if we want to assign their posts to another user. Please do not delete them or posts will be deleted.', 'import-users-from-csv-with-meta' ); ?></p>
						</div>
					</td>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label for="role"><?php _e( 'Role', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<select id="role" name="role">
							<?php 
								if( $role == '' )
									echo "<option selected='selected' value=''>" . __( 'Disable role assignement in cron import', 'import-users-from-csv-with-meta' )  . "</option>";
								else
									echo "<option value=''>" . __( 'Disable role assignement in cron import', 'import-users-from-csv-with-meta' )  . "</option>";

								$list_roles = acui_get_editable_roles();								
								foreach ($list_roles as $key => $value) {
									if($key == $role)
										echo "<option selected='selected' value='$key'>$value</option>";
									else
										echo "<option value='$key'>$value</option>";
								}
							?>
						</select>
						<p class="description"><?php _e( 'Which role would be used to import users?', 'import-users-from-csv-with-meta' ); ?></p>
					</td>
				</tr>
				<tr class="form-field form-required">
					<th scope="row"><label for="move-file-cron"><?php _e( 'Move file after import?', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<div style="float:left;">
							<input type="checkbox" name="move-file-cron" value="yes" <?php if( $move_file_cron == true ) echo "checked='checked'"; ?>/>
						</div>

						<div id="move-file-cron-cell" style="margin-left:25px;">
							<input placeholder="<?php _e( 'Insert complete path to the file', 'import-users-from-csv-with-meta'); ?>" type="text" name="path_to_move" id="path_to_move" value="<?php echo $path_to_move; ?>" style="width:70%;" />
							<p class="description"><?php _e( 'You have to introduce the path to file, i.e.:', 'import-users-from-csv-with-meta'); ?> <?php $upload_dir = wp_upload_dir(); echo $upload_dir["path"]; ?>/move.csv</p>
						</div>
					</td>
				</tr>				
				<tr class="form-field form-required">
					<th scope="row"><label for="log"><?php _e( 'Last actions of schedule task', 'import-users-from-csv-with-meta' ); ?></label></th>
					<td>
						<pre><?php echo $log; ?></pre>
					</td>
				</tr>
				</tbody>
			</table>
			<input class="button-primary" type="submit" value="<?php _e( 'Save schedule options', 'import-users-from-csv-with-meta'); ?>"/>
		</form>

		<script>
		jQuery( document ).ready( function( $ ){
			$( "[name='cron-delete-users']" ).change(function() {
		        if( $(this).is( ":checked" ) ) {
		            var returnVal = confirm("<?php _e( 'Are you sure to delete all users that are not present in the CSV? This action cannot be undone.', 'import-users-from-csv-with-meta' ); ?>");
		            $(this).attr("checked", returnVal);

		            if( returnVal )
		            	$( '#cron-delete-users-assign-posts' ).show();
		        }
		        else{
	       	        $( '#cron-delete-users-assign-posts' ).hide();     	        
		        }
		    });

		    $( "[name='move-file-cron']" ).change(function() {
		        if( $(this).is( ":checked" ) )
		        	$( '#move-file-cron-cell' ).show();
		        else
		        	$( '#move-file-cron-cell' ).hide();
		    });

		    <?php if( $cron_delete_users == '' ): ?>
		    $( '#cron-delete-users-assign-posts' ).hide();
		    <?php endif; ?>

		    <?php if( !$move_file_cron ): ?>
		    $( '#move-file-cron-cell' ).hide();
		    <?php endif; ?>
		});
		</script>
	<?php break; ?>

	<?php case 'donate': ?>

	<div class="postbox">
	    <h3 class="hndle"><span>&nbsp;<?php _e( 'Do you like it?', 'import-users-from-csv-with-meta' ); ?></span></h3>

	    <div class="inside" style="display: block;">
	        <img src="<?php echo $url_plugin; ?>icon_coffee.png" alt="<?php _e( 'buy me a coffee', 'import-users-from-csv-with-meta' ); ?>" style=" margin: 5px; float:left;">
	        <p><?php _e( 'Hi! we are', 'import-users-from-csv-with-meta'); ?> <a href="https://twitter.com/fjcarazo" target="_blank" title="Javier Carazo">Javier Carazo</a> <?php _e( 'and', 'import-users-from-csv-with-meta' ); ?> <a href="https://twitter.com/ahornero" target="_blank" title="Alberto Hornero">Alberto Hornero</a> <?php _e( 'from', 'import-users-from-csv-with-meta' ); ?> <a href="http://codection.com">Codection</a>, <?php _e("developers of this plugin.", 'import-users-from-csv-with-meta' ); ?></p>
	        <p><?php _e( 'We have been spending many hours to develop this plugin. <br>If you like and use this plugin, you can <strong>buy us a cup of coffee</strong>.', 'import-users-from-csv-with-meta' ); ?></p>
	        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="hosted_button_id" value="QPYVWKJG4HDGG">
				<input type="image" src="https://www.paypalobjects.com/en_GB/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="<?php _e('PayPal – The safer, easier way to pay online.', 'import-users-from-csv-with-meta' ); ?>">
				<img alt="" border="0" src="https://www.paypalobjects.com/es_ES/i/scr/pixel.gif" width="1" height="1">
			</form>
	        <div style="clear:both;"></div>
	    </div>
	</div>

	<?php break; ?>

	<?php case 'help': ?>

	<div class="postbox">
	    <h3 class="hndle"><span>&nbsp;<?php _e( 'Need help with WordPress or WooCommerce?', 'import-users-from-csv-with-meta' ); ?></span></h3>

	    <div class="inside" style="display: block;">
	        <p><?php _e( 'Hi! we are', 'import-users-from-csv-with-meta' ); ?> <a href="https://twitter.com/fjcarazo" target="_blank" title="Javier Carazo">Javier Carazo</a><?php _e( 'and', 'import-users-from-csv-with-meta' ) ?> <a href="https://twitter.com/ahornero" target="_blank" title="Alberto Hornero">Alberto Hornero</a>  <?php _e( 'from', 'import-users-from-csv-with-meta' ); ?> <a href="http://codection.com">Codection</a>, <?php _e( 'developers of this plugin.', 'import-users-from-csv-with-meta' ); ?></p>
	        <p><?php _e( 'We work everyday with WordPress and WooCommerce, if you need help hire us, send us a message to', 'import-users-from-csv-with-meta' ); ?> <a href="mailto:contacto@codection.com">contacto@codection.com</a>.</p>
	        <div style="clear:both;"></div>
	    </div>
	</div>

	<?php break; ?>
<?php
	}
}