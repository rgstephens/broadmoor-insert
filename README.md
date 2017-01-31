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

### Wordpress calls that update database:

- **wp_create_user** - Insert user with only basic info: username, password & email. **Main create call**
- **wp_insert_user** - Insert user, allows meta data, password change email not sent
- **wp_update_user** -  Updates multiple pieces of user data but not custom metadata & send ***password change email***
- **update_user_meta** - Updates a single piece of user metadata
- **delete_user_meta** - 
- **wp_set_password** -
- **do_action**('lostpassword_post')

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
