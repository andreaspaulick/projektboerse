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
 * Return a list of all prox-projects
 *
 * @return mixed - returns an array of ALL projects in prox
 */
function pb_get_projects() {

    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present

    $request = wp_remote_get($url, array('headers' => array('Content-Type' => 'application/json; charset=utf-8',
        'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])));

    if (is_wp_error($request) || wp_remote_retrieve_response_code( $request ) === 404){
        echo 'FEHLER: konnte keine Verbindung zur Projektbörse aufbauen.';
        exit;
    }

    $request_body = wp_remote_retrieve_body($request);

    return  json_decode($request_body, true)['_embedded']['projects'];
}

/**
 * Generates an array of study courses
 *
 * @return string - array of study courses
 */
function pb_get_studyCourses() {
    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projectStudyCourses';
    $response = wp_remote_get($url);
    if(is_wp_error($response) || wp_remote_retrieve_response_code( $response ) === 404) return "API nicht erreichbar";
    else return json_decode($response['body'], TRUE)['_embedded']['projectStudyCourses'];
}

/**
 * Generates an array of modules for a certain project
 *
 * @param $url - the URL to the Prox modules endpoint of every project -> ../projects/{project-id}/modules/
 * @return mixed - array of all modules for that project
 */
function pb_get_studyCoursesModules($url) {
    $response = wp_remote_get($url);
    return json_decode($response['body'], TRUE)['_embedded']['projectModules'];
}

/**
 * Log to File
 * Description: Log into system php error log, useful for Ajax and stuff that FirePHP doesn't catch
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