<?php
/**
 * I had to customize sydney/inc/slider.php directly because Child themes can't handle includes
 */

error_log(print_r("functions.php", true));

add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );
function my_theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

add_filter('integral_mailchimp_plugin_sync_merge_tags', 'get_sync_merge_tag_definitions');

function get_sync_merge_tag_definitions($tags) {
    error_log(print_r("get_sync_merge_tag_definitions", true));

    $tags['ALLGOLF'] = array(                      //- UPPERCASE, 10 characters max
        'name' => 'AllGolfClub',                 //- This is the name that will show up in the MailChimp interface
        'field_type' => 'radio',                    //- The field type (read more: https://apidocs.mailchimp.com/api/2.0/lists/merge-var-add.php)
        'req' => FALSE,                            //- Whether this value is required when filling in the subscribe form via the MailChimp site
        'public' => FALSE,                         //- Whether this field is visible to the public
        'show' => TRUE,                            //- Whether this field is visible in the field list in MailChimp
        'plugin_name' => 'CSV Import'   //- Where this Merge Tag is derived from (NOTE: For plugin purposes only to display in the List Management interface)
    );

    error_log(print_r("returning tags:", true));
    error_log(print_r($tags, true));
    return $tags;
}

add_filter('integral_mailchimp_plugin_get_merge_tags', 'get_sync_merge_tag_values', 10, 2);

function get_sync_merge_tag_values($tags, $user) {
    error_log(print_r("get_sync_merge_tag_values", true));
    error_log(print_r("tags:", true));
    error_log(print_r($tags, true));
    //$tagarray = array_map( function( $a ){ return $a[0]; }, $tags );

    $user_meta = get_user_meta($user->ID);
    //$tags['ALLGOLF'] = $user_meta->grp_name;
    //$tags['ALLGOLF'] = $user_meta->AllGolfClub;
    //$tags['ALLGOLFstring'] = 'Some example text';
    //return $tags
}
?>
