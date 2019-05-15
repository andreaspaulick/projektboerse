<?php
/**
 * Get all values of a multidimensional array
 */
function array_column_recursive(array $haystack, $needle) {
    $found = [];
    array_walk_recursive($haystack, function($value, $key) use (&$found, $needle) {
        if ($key == $needle)
            $found[] = $value;
    });
    return $found;
}

/**
 * Determine the creation date and time of the current projekt
 */
function sc_pb_meta_dateandtime(){
    global $post;
    return "<span style='font-size: 10px;'> <i>Projekt erstellt am: ".get_the_date("d. F Y, H:i", $post->ID)." Uhr</i></span>";
}
add_shortcode('sc_pb_meta_dateandtime', 'sc_pb_meta_dateandtime');

/**
 * Create a random string
 * @param int $length
 * @return random String
 */
function generateRandomString($length = 50) {
    return hash('sha512', substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length));
}

/**
 * Log to File
 * Description: Log into system php error log, usefull for Ajax and stuff that FirePHP doesn't catch
 */
function my_log_file( $msg, $name = '' )
{
    // Print the name of the calling function if $name is left empty
    $trace=debug_backtrace();
    $name = ( '' == $name ) ? $trace[1]['function'] : $name;

    $error_dir = '/home/andreas/Schreibtisch/pb_debug.log';
    $msg = print_r( $msg, true );
    $log = $name . "  |  " . $msg . "\n";
    error_log( $log, 3, $error_dir );
}