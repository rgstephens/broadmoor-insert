## Notes on importer.php

Notes on how the script works, key variables and calls.

### Wordpress calls that update database:

- wp_insert_user
- wp_create_user
- wp_update_user
- update_user_meta
- delete_user_meta
- do_action('lostpassword_post')

### Example create boolean user meta field

- update_user_meta( $user_id, "MensGolf", true );
