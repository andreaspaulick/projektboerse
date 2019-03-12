<?php
/*
 * Plugin Name: TH Köln Projektbörse Beitragskloner
 * Description: Sendet als Projekt markierte Beiträge nach dem veröffentlichen automatisch an die Projektbörse der TH Köln.
 * Author: Andreas Paulick
 * Author URI: https://github.com/andreaspaulick
 * Version: 0.1
 * Plugin URI: 
*/

defined( 'ABSPATH' ) or exit;
define( 'DEFAULT_API_URL' , 'http://localhost:8045/posts/jsonadd' ); // default link to the PB API
define( 'DEFAULT_KEYCLOAK_API_URL' , 'http://localhost:8180/auth/realms/pboerse/protocol/openid-connect/token' ); // default link to the keycloak API
define( 'USE_LOCAL_PB' , TRUE ); // here you can choose whether to use the local "pb dummy" or the official test version of the PB via internet

include 'pb_options.php';

// ------------------------ Plugin functionality ----------------------------

/**
 * post Variable Reference: https://codex.wordpress.org/Function_Reference/$post
 */
function post_published_api_call( $ID, $post) {

    if( get_post_meta($post->ID, '_pb_wporg_meta_key0', true) !== "1" ) return; // return (do nothing) if checkbox "also send to pb" is not checked

        if(USE_LOCAL_PB === FALSE) {
            $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present
        }
        else {
            $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/posts/jsonadd'; // add json-consuming ressource to url. Strip last slash if present
        }

        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content); // at the moment all tags are stripped (images won't be transferred)

        // data for local DUMMY PROJEKTBÖRSE
        if(USE_LOCAL_PB === TRUE) {
            $post_data = array(
                'status' => 'publish',
                'title' => $title,
                'content' => $content,
                'course' => get_post_meta($post->ID, '_pb_wporg_meta_key1', true),
                'start' => get_post_meta($post->ID, '_pb_wporg_meta_key2', true),
                'end' => get_post_meta($post->ID, '_pb_wporg_meta_key3', true),
                'max_party' => get_post_meta($post->ID, '_pb_wporg_meta_key4', true),
                'tags' => wp_strip_all_tags(get_the_tag_list('', ',', '', $post->ID)),
                'user_login' => wp_get_current_user()->user_login
            );
        }
        else {
            // data for Testserver Projektbörse --> https://gptest.archi-lab.io/projects
            $post_data = array(
                'status' => 'GEPLANT',
                'name' => $title,
                'description' => $content
            );
        }

        $json_post = json_encode($post_data);

        if(get_option('token_enable_checkbox')['token_enable']==="0") {

            $data = wp_remote_post($url, array(
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8'),
                'body' => $json_post,
                'method' => 'POST'
            ));
        }
        else {
            $token_response = get_keycloak_token_response();

            $keycloak_access_token = extract_keycloak_access_token($token_response);

            if ($keycloak_access_token === "TOKEN_REQUEST_ERROR")
                return;

            $data = wp_remote_post($url, array(
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => 'Bearer ' . $keycloak_access_token),
                'body' => $json_post,
                'method' => 'POST'
            ));

            //logout session
            keycloak_session_logout($token_response);
        }
}
add_action( 'publish_post', 'post_published_api_call', 10, 2);

// Add custom metabox paragraph for THK projects on "post" and "wporg_cpt" pages
function pb_wporg_add_custom_box()
{
    $screens = ['post', 'wporg_cpt'];
    foreach ($screens as $screen) {
        add_meta_box(
            'studiengang_wporg_box_id',           // Unique ID
            'THK Projektbörse: Projektdaten',  // Box title
            'pb_custom_box_html',  // Content callback, must be of type callable
            $screen                   // Post type
        );
    }
}
add_action('add_meta_boxes', 'pb_wporg_add_custom_box');

// HTML input fields for post metadata. Loads the last saved data!
function pb_custom_box_html($post)
{
    $meta = get_post_meta( $post->ID );
    $checkbox_value = ( isset( $meta['checkbox_value'][0] ) &&  '1' === $meta['checkbox_value'][0] ) ? 1 : 0;
    ?>
    <p>
        <label><input type="checkbox" name="checkbox_value" value="1" <?php checked( $checkbox_value, 1 ); ?> />Kopie dieses Beitrags an Projektbörse senden?</label>
    </p>
    <hr>
    <p>
        <label for="pb_wporg_course">Studiengang</label>
        <select name="pb_wporg_course[]" id="pb_wporg_course" class="postbox" multiple="multiple" size="6">
            <?php
                $data = get_pb_courses();  // get all the courses via REST-API
                foreach($data as $key => $item){
            ?>
                <option value="<?php echo $key;?>"><?php echo $item; ?></option>
            <?php
                }
            ?>
        </select>
    </p>
    <p>
        <?php   ?>
        <label for="pb_wporg_project_start">Projektstart:</label>
        <input type="date" name="pb_wporg_project_start" id="pb_wporg_project_start" value="<?php echo get_post_meta($post->ID, '_pb_wporg_meta_key2', true);  ?>">
    </p>
    <p>
        <label for="pb_wporg_project_end">Projektende:</label>
        <input type="date" name="pb_wporg_project_end" id="pb_wporg_project_end" value="<?php echo get_post_meta($post->ID, '_pb_wporg_meta_key3', true);  ?>">
    </p>
    <p>
        <label for="pb_wporg_project_max_participants">Teilnehmerbegrenzung:</label>
        <input type="number" name="pb_wporg_project_max_participants" id="pb_wporg_project_max_participants" value="<?php echo get_post_meta($post->ID, '_pb_wporg_meta_key4', true);  ?>" size="2" min="1" max="999">
    </p>
    <?php
}

// save the pb-metabox data into a unique meta key
function pb_wporg_save_postdata($post_id)
{

    // checkbox
    $checkbox_value = ( isset( $_POST['checkbox_value'] ) && '1' === $_POST['checkbox_value'] ) ? 1 : 0; // Input var okay.
    update_post_meta( $post_id, '_pb_wporg_meta_key0', esc_attr( $checkbox_value ) );

    // study course
    if (array_key_exists('pb_wporg_course', $_POST)) {
        update_post_meta(
            $post_id,
            '_pb_wporg_meta_key1',
            implode(', ', $_POST['pb_wporg_course']) // array to single string
        );
    }

    // project start
    if (array_key_exists('pb_wporg_project_start', $_POST)) {
        update_post_meta(
            $post_id,
            '_pb_wporg_meta_key2',
            $_POST['pb_wporg_project_start']
        );
    }

    // project end
    if (array_key_exists('pb_wporg_project_end', $_POST)) {
        update_post_meta(
            $post_id,
            '_pb_wporg_meta_key3',
            $_POST['pb_wporg_project_end']
        );
    }

    // project maximum participants
    if (array_key_exists('pb_wporg_project_max_participants', $_POST)) {
        update_post_meta(
            $post_id,
            '_pb_wporg_meta_key4',
            sanitize_text_field($_POST['pb_wporg_project_max_participants'])
        );
    }

}
add_action('publish_post', 'pb_wporg_save_postdata', 9);


function wp_post_to_html($wp_post_content){
    $remove_tags = str_replace("<!-- /wp:paragraph -->","", str_replace("<!-- wp:paragraph -->","", $wp_post_content));
    $replace_line_breaks = str_replace("\n","", str_replace("\n\n", "<br />", $remove_tags));
    $remove_p = str_replace("</p>","", str_replace("<p>", "", $replace_line_breaks));
    return $remove_p;
}

function extract_keycloak_access_token($response){

    if($response==="TOKEN_REQUEST_ERROR" || !is_array($response) || strpos($response, 'cURL error 7:') !== false)
        return "TOKEN_REQUEST_ERROR";

    $kc_response = json_decode($response['body']); // JSON to array

    return $kc_response->access_token; // get access-token
}

function get_keycloak_token_response(){

    $kc_url = get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL))['token_url'];
    $kc_clientid = get_option('token_api_clientid')['token_clientid'];
    $kc_username = get_option('token_api_username')['token_username'];
    $kc_password = get_option('token_api_password')['token_password'];

    // if URL does not start with 'http' return error message
    if(strpos($kc_url,"http" )===false)
        return "URL_MALFORMED";

    $request_body = array(
        'client_id' => $kc_clientid,
        'username' => $kc_username,
        'password' => $kc_password,
        'grant_type' => 'password'
    );

    $response = wp_remote_post($kc_url, array(
        'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded'),
        'body' => http_build_query($request_body),
        'method' => 'POST'
    ));

    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        return $error_message;
    }
    else if($response['response']['code']!==200)
        return "TOKEN_REQUEST_ERROR";

    return $response;
}

function keycloak_session_logout($keycloak_token_response) {

    if($keycloak_token_response==="TOKEN_REQUEST_ERROR")
        return $keycloak_token_response;

    $kc_clientid = get_option('token_api_clientid')['token_clientid'];
    $refresh_token = json_decode($keycloak_token_response['body'])->refresh_token;  // get refresh-token

    $url = preg_replace("/\btoken$/","logout", get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL))['token_url']);  // replace "token" endpoint with "logout" endpoint from Token-URL

    $data = wp_remote_post($url, array(
        'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded'),
        'body' => http_build_query(array(
            'client_id' => $kc_clientid,
            'refresh_token' => $refresh_token)),
        'method' => 'POST'
    ));
}

// retrieves pretty study-course-list from PB REST API
function get_pb_courses() {

    // TODO remove hardcoded URL before release:
    //$url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/studyCourses';
    $url = 'https://gptest.archi-lab.io/studyCourses';

    $response = wp_remote_get($url); // get study courses from PB API
    $response_body = json_decode($response['body'], TRUE); // we only need the body of the response
    if($response_body['status']===404) // if status=404 the api was not found
        return array("ERROR: Could not retrieve API data...");

    $courses = array_column_recursive($response_body,'name'); // go get all study courses
    $degree = array_column_recursive($response_body, 'academicDegree'); // get the academic degree of all couses

    $id = array();

    // dirty implementation to get the ID of each course (which in fact is the self href in the projektbörse api)
    for ($i = 0; $i < count($courses); $i++) {
        array_push($id, $response_body['_embedded']['studyCourses'][$i]['_links']['self']['href']);
    }

    // we substitute the index number-key (0, 1, 2, ...) of the course array with the "href-ID" of the API as the key
    for ($i = 0; $i < count($courses); $i++) {
        $courses[$i] = $courses[$i].' ('.$degree[$i].')'; // course name + academic degree = new full course name

        // key substitution:
        $courses[$id[$i]] = $courses[$i];
        unset($courses[$i]);
    }

   return $courses;
}

function array_column_recursive(array $haystack, $needle) {
    $found = [];
    array_walk_recursive($haystack, function($value, $key) use (&$found, $needle) {
        if ($key == $needle)
            $found[] = $value;
    });
    return $found;
}


//  -------------------------  Options Page & Settings --------------------------------------



// --------------------------- Debugging Section -------------------------------

/* Echo variable
 * Description: Uses <pre> and print_r to display a variable in formated fashion
 */
function echo_log( $what )
{
    echo '<pre>'.print_r( $what, true ).'</pre>';
}

/* Log to File
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