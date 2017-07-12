## Apache

Logs: `/var/log/apache2`
Config: `/etc/apache2/sites-available`

## Notes on importer.php

Notes on how the script works, key variables and calls.

### Key Script Objects & Variables

**$user_data**, holds core user object (id, username, email, password) for **wp_insert_user**
**$updateEmailArgs**, holds id & email for **wp_update_user**

**$wp_users_fields**, "id", "user_nicename", "user_url", "display_name", "nickname", "first_name", "last_name", "description", "jabber", "aim", "yim", "user_registered", "password", "user_pass"
**$positions**, array holding position of $wp_users_fields in import file
**$headers_filtered**, 

**$headers**, holds the csv file headers
**$data**, holds the csv data rows

**$username**, holds username, calculated from usr_firstname & usr_lastname

### Wordpress Core Fieldnames

Wordpress field names are defined [here](https://codex.wordpress.org/Function_Reference/wp_update_user).

### Wordpress calls:

- **wp_create_user** - Insert user with only basic info: username, password & email. **Main create call**
- **wp_insert_user** - Insert user, allows meta data, password change email not sent
- **wp_update_user** -  Updates multiple pieces of user data but not custom metadata & send ***password change email***
- **update_user_meta** - Updates a single piece of user metadata
- **delete_user_meta** - 
- **wp_set_password** - 
  - User update when password is in import file
  - Id was supplied on insert and password is in import file
  - username exists and password is in import file
- **do_action**('lostpassword_post')
- **wp_mail**( $email, $subject, $body_mail ) - send email

### Steps to Add New Import Field

- Add CSV field name to **$map_broadmoor_fields** in importer.php
- Add imported field to **$show_meta_fields_admin** in import-users-from-csv-with-meta.php
- Add block to switch statement (see below) in importer.php

```
case "Relationship":
	update_user_meta($user_id, "relationship", $data[$i]);
	break;
```

### Example create boolean user meta field

- update_user_meta( $user_id, "MensGolf", true );

### user_meta examples

Update data based on user meta change [here](https://wordpress.org/support/topic/updatesync-user-after-update_user_meta/). Update done via cron job.

[MailChimp User Sync source](https://github.com/ibericode/mailchimp-user-sync)
MailChimp source

## Timely All-In-One Calendar

### functions.php Customization

The following code is added to suppress display of calendar posted on and by information:

```
if ( ! function_exists( 'sydney_posted_on' ) ) :
/**
 * Prints HTML with meta information for the current post-date/time and author.
 */
function sydney_posted_on() {
}
endif;
```

### Customize single event display

To remove much of the header information on the event details screen, the **event-single.twig** file must be customized.  The safe way to do that is to create an all-in-one calendar template.

The source file location is: /var/www/html/wp-content/plugins/all-in-one-event-calendar/public/themes-ai1ec/vortex/twig/event-single.twig

The custom template version is: /var/www/html/wp-content/themes-ai1ec/greg/twig/event-single.twig

The official instructions on creating a template are [here](https://time.ly/document/user-guide/customize-calendar/create-new-calendar-theme/).

There's also a good blog post [here](http://sundari-webdesign.com/all-in-one-event-calendar-theme-customization-tutorial-my-perfect-wordpress-calendar/)


### Mailchimp API Docs

list_subscribe( $list_id, $email_address, array $args = array(), $update_existing = false, $replace_interests = true )

add_list_member( $list_id, array $args )

```
public function add_list_member( $list_id, array $args ) {
    $subscriber_hash = $this->get_subscriber_hash( $args['email_address'] );
    $resource = sprintf( '/lists/%s/members/%s', $list_id, $subscriber_hash );
 
    // make sure we're sending an object as the MailChimp schema requires this
    if( isset( $args['merge_fields'] ) ) {
        $args['merge_fields'] = (object) $args['merge_fields'];
    }
 
    if( isset( $args['interests'] ) ) {
        $args['interests'] = (object) $args['interests'];
    }
 
    // "put" updates the member if it's already on the list... take notice
    $data = $this->client->put( $resource, $args );
    return $data;
}
```

```
add_action( 'mc4wp_form_subscribed', function() {
   // do something
});
```

```
/**
 * Tell MailChimp for WordPress to subscribe to a certain list based on the WPML language that is being viewed.
 *
 * Make sure to change the list ID's to the actual ID's of your MailChimp lists
 *
 * @param array $lists
 * @return array $lists
 */
function myprefix_filter_mc4wp_lists( $lists ) {
	$list_id_spanish_list = '123abcdef456';
	$list_id_english_list = '456defabc123';

	if( defined( 'ICL_LANGUAGE_CODE') ) {
		switch( ICL_LANGUAGE_CODE ) {

			// spanish
			case 'es':
				$lists = array( $list_id_spanish_list );
				break;
			// english
			case 'en':
				$lists = array( $list_id_english_list );
				break;
		}
	}
	return $lists;
}

add_filter( 'mc4wp_lists', 'myprefix_filter_mc4wp_lists' );
```