<?php
/**
 * I had to customize sydney/inc/slider.php directly because Child themes can't handle includes
 */

//error_log(print_r("functions.php", true));

add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );
function my_theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

function sr_user_profile_update_core( $user_id, $old_user_data ) {
    $admin_email = get_option('admin_email');
    $blogname = get_option('blogname');
    //error_log(print_r("sr_user_profile_update, Notify email: " . $admin_email, true));
    $user = get_userdata( $user_id );
    $message = sprintf( __( 'This user has updated their profile.' ) ) . "\r\n\r\n";
    $message .= sprintf( __( 'Display Name: %s' ), $user->display_name ). "\r\n\r\n";
    //$message .= print_r($old_user_data, true);
    if($old_user_data->user_email != $user->user_email) {
        //$admin_email = "you@yourdomain.com";
        $message .= sprintf( __( 'Old Email: %s' ), $old_user_data->user_email ). "\r\n\r\n";
        $message .= sprintf( __( 'New Email: %s' ), $user->user_email ). "\r\n\r\n";
        wp_mail($admin_email, 'User changed profile at ' . $blogname, $message);
    }
    //wp_mail( $admin_email, sprintf( __( '[Staff Member Site] User Profile Update' ), get_option('blogname') ), $message );
}

//add_action( 'profile_update', 'sr_user_profile_update_core', 10, 2 );

function sr_user_profile_update_meta( $user_id, $old_user_data ) {
    $core_fields = array("user_email");
    $meta_fields = array("company", "first_name", "last_name", "phone1", "mobile_number",
        "home_address", "home_address_2", "home_city", "home_state", "home_zip", "home_phone", "home_fax",
        "work_address", "work_address_2", "work_city", "work_state", "work_zip", "work_phone", "work_fax",
        "title", "spouse_name");
    $profile_change = false;
    $user = get_userdata( $user_id );
    $message = sprintf( __( 'This user has updated their profile.' ) ) . "\r\n\r\n";
    $message .= sprintf( __( 'Display Name: %s' ), $user->display_name ). "\r\n\r\n";
    //************* meta fields
    if($old_user_data->user_email != $user->user_email) {
        $profile_change = true;
        $message .= ' email: ' . $old_user_data->user_email . ' > ' . $user->user_email . "\r\n";
    }
    //************* meta fields
    $old_user_data = get_transient( 'sr_old_user_data_' . $user_id );
    if (!$old_user_data && !$profile_change) {
        //error_log(print_r("sr_user_profile_update_meta, no old data, return", true));
        return;
    }
    $admin_email = get_option('admin_email');
    $blogname = get_option('blogname');

    error_log(print_r("sr_user_profile_update_meta, Notify email: " . $admin_email, true));
    //error_log(print_r($old_user_data, true));
    //$message .= print_r($old_user_data, true);

    foreach ($meta_fields as $field_name) {
        //error_log(print_r($field_name . "  = " . $old_user_data->{$field_name}, true));
        if ($old_user_data) {
            $old_var = $old_user_data->{$field_name} ?: '';
        } else {
            $old_var = '';
        }
        $new_var = $user->{$field_name} ?: '';
        if($old_var != $new_var) {
            $profile_change = true;
            $message .= '   ' . $field_name . ': ' . $old_var . ' > ' . $new_var . "\r\n";
            //error_log(print_r('   ' . $field_name . ': ' . $old_var . ' > ' . $new_var, true));
        }
    }

    /*    $old_company = $old_user_data->company ?: '';
        $new_company = $user->company ?: '';
        //error_log(print_r("  company: " . $old_company . "  > " . $new_company, true));
        if($old_company != $new_company) {
            $profile_change = true;
            $message .= ' company: ' . $old_user_data->company . ' > ' . $user->company . "\r\n";
            //$message .= sprintf( __( 'Old Company: %s' ), $old_user_data->company ). "\r\n\r\n";
            //$message .= sprintf( __( 'New Company: %s' ), $user->company ). "\r\n\r\n";
            //wp_mail( $admin_email, sprintf( __( '[Staff Member Site] User Profile Update' ), get_option('blogname') ), $message );
        }*/

    if ($profile_change) {
        wp_mail($admin_email, 'User changed profile at ' . $blogname, $message);
    }

}

add_action( 'profile_update', 'sr_user_profile_update_meta', 10, 2 );

// Save old user data and meta for later comparison for non-standard fields (phone, address etc.)
function sr_old_user_data_transient(){
    //error_log(print_r("sr_old_user_data_transient, storing user meta", true));
    $user_id = get_current_user_id();
    $user_data = get_userdata( $user_id );
    $user_meta = get_user_meta( $user_id );

    foreach( $user_meta as $key=>$val ){
        $user_data->data->$key = current($val);
    }

    // 1 hour should be sufficient
    set_transient( 'sr_old_user_data_' . $user_id, $user_data->data, 60 * 60 );

}
add_action('um_user_before_updating_profile', 'sr_old_user_data_transient');
//add_action('profile_update', 'sr_old_user_data_transient', 5, 2);
//add_action('show_user_profile', 'sr_old_user_data_transient');

// Cleanup when done
function sr_old_user_data_cleanup( $user_id, $old_user_data ){
    delete_transient( 'sr_old_user_data_' . $user_id );
}

add_action( 'profile_update', 'sr_old_user_data_cleanup', 1000, 2 );

function gs_modify_user_columns($column_headers) {
    //error_log(print_r("gs_modify_user_columns: ", true));
    $column_headers['last_login'] = 'Last Login';
    $column_headers['id'] = 'ID';
    return $column_headers;
}

add_action('manage_users_columns','gs_modify_user_columns');

function gs_modify_user_table_row( $val, $column_name, $user_id ) {
    $user = get_userdata( $user_id );
    //error_log(print_r("gs_modify_user_table_row, user_id: " . $user_id . ", url: " . $user->user_url, true));

    switch ($column_name) {
        case 'id' :
            return $user_id;
            break;
        case 'last_login' :
            return date("Y-m-d H:i",get_user_meta( $user_id, '_um_last_login', true ));
            //return get_user_meta( $user_id, '_um_last_login', true );
            break;
        default:
    }

    return $val;
}

add_filter( 'manage_users_custom_column', 'gs_modify_user_table_row', 10, 3 );

function gs_user_sortable_columns( $columns ) {
    $columns['last_login'] = 'last_login';
    return $columns;
}

add_filter( 'manage_users_sortable_columns', 'gs_user_sortable_columns' );

function my_user_query($userquery){
    if('last_login'==$userquery->query_vars['orderby']) {
        global $wpdb;
        $userquery->query_from .= " LEFT OUTER JOIN $wpdb->usermeta AS alias ON ($wpdb->users.ID = alias.user_id) ";//note use of alias
        $userquery->query_where .= " AND alias.meta_key = '_um_last_login' ";//which meta are we sorting with?
        $userquery->query_orderby = " ORDER BY alias.meta_value ".($userquery->query_vars["order"] == "ASC" ? "asc " : "desc ");//set sort order
    }
    if('id'==$userquery->query_vars['orderby']) {
        error_log(print_r("id sort", true));
        global $wpdb;
        $userquery->query_from .= " LEFT OUTER JOIN $wpdb->usermeta AS alias ON ($wpdb->users.ID = alias.user_id) ";//note use of alias
        $userquery->query_where .= " AND alias.meta_key = 'id' ";//which meta are we sorting with?
        $userquery->query_orderby = " ORDER BY $wpdb->users.ID ".($userquery->query_vars["order"] == "ASC" ? "asc " : "desc ");//set sort order
    }
}

add_action('pre_user_query', 'my_user_query');

?>
