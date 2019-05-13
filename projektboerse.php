<?php
/*
 * Plugin Name: TH Köln Prox Client
 * Description: Projekte mit der TH-Köln Projekt- und Themenbörse synchronisieren.
 * Author: Andreas Paulick
 * Author URI: https://github.com/andreaspaulick
 * Version: 0.9
 * Plugin URI: https://github.com/andreaspaulick/projektboerse
*/

defined( 'ABSPATH' ) or exit;
define( 'DEFAULT_API_URL' , 'https://gpdev.archi-lab.io/' ); // default link to the PB API
define( 'DEFAULT_KEYCLOAK_API_URL' , 'https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/token' ); // default link to the keycloak API here you can choose whether to use the local "pb dummy" or the official test version of the PB via internet

include 'redirect.php';
include 'pb_options.php';

/**
 * post Variable Reference: https://codex.wordpress.org/Function_Reference/$post
 */
function post_published_api_call( $ID, $post) {

    if( get_post_meta($post->ID, '_pb_wporg_meta_checkbox', true) !== "1" ) return; // return (do nothing) if checkbox "also send to pb" is not checked

        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present

        $title = $post->post_title;
        if (empty($title)) {
            $title = "[kein titel]";
        }
        $content = wp_strip_all_tags($post->post_content); // at the moment all tags are stripped (images won't be transferred)

        // get the supervisor name from meta key if it exists. optherwise get the default supervisor name from settings page
        if(metadata_exists( 'post', $post->ID, '_pb_wporg_meta_project_status' )){
            $sup_name = get_post_meta($post->ID, '_pb_wporg_meta_supervisor', true);
        }
        else
            $sup_name = get_option('pb_add_supervisor')['pb_add_supervisor_field'];

        // data for Testserver Projektbörse --> https://gpdev.archi-lab.io/projects
        $post_data = array(
            // TODO creatorID ermitteln!
            'creatorID' => '1b29e41e-aab2-4757-8ea2-7e2daca207e6',
            'creatorName' => $GLOBALS['prox_username'],
            'description' => $content,
            'name' => $title,
            'status' => get_post_meta($post->ID, '_pb_wporg_meta_project_status', true),
            'supervisorName' => $sup_name
        );

        $json_post = json_encode($post_data);

        // get the project-id of the pb-post (if set)
        $pb_project_id = get_post_meta($post->ID, 'pb_project_id', true);

        if( metadata_exists( 'post', $post->ID, 'pb_project_id' )){ // means the project is edited

            $url2 = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/projects/'.$pb_project_id ;

            $data = wp_remote_request($url2, array(
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token']),
                'body'          => json_encode( array(
                    'id'            => $pb_project_id,
                    // TODO creatorID ermitteln!
                    'creatorID' => '1b29e41e-aab2-4757-8ea2-7e2daca207e6',
                    'creatorName' => $GLOBALS['prox_username'],
                    'description' => $content,
                    'name' => $title,
                    'status' => get_post_meta($post->ID, '_pb_wporg_meta_project_status', true),
                    'supervisorName' => $sup_name,
                )),
                'method' => 'PUT'
            ));

            // save the "modified" date for future comparison
            $modified = json_decode(wp_remote_retrieve_body(
                wp_remote_get($url2,
                    array('headers' => array(
                        'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])
                    )
                )
            ), true)['modified'];
            update_post_meta( $post->ID, 'pb_project_modified', $modified);

        } // end projects exists
        else { // no previously saved project-id = new project

            $data = wp_remote_post($url, array(
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token']),
                'body' => $json_post,
                'method' => 'POST'
            ));

            // save the "modified" date for future comparison
            $modified = json_decode(wp_remote_retrieve_body(
                wp_remote_get(rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects/' . json_decode(wp_remote_retrieve_body($data))->id,
                    array('headers' => array(
                        'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])
                    )
                )
            ), true)['modified'];
            update_post_meta( $post->ID, 'pb_project_modified', $modified);


            // save the id of the pb-project
            update_post_meta( $post->ID, 'pb_project_id', json_decode(wp_remote_retrieve_body($data))->id);
        }
}
add_action( 'publish_projects', 'post_published_api_call', 10, 2);

function pb_keycloak_is_authenticated() {
    $response = wp_remote_get('https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/userinfo', array('headers' => array(
        'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])));
    $response_code = wp_remote_retrieve_response_code( $response );
    if ($response_code === 200) {
        return true;
    }
    else {
        return false;
    }
}


// not only delete projects in wordpress, but also delete the corresponding entry in the pb via REST API
function pb_sync_delete_post($postid){
    global $post_type;
    $pb_project_id = get_post_meta($postid, 'pb_project_id', true);

    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/projects/'.$pb_project_id ;

    if(isset(get_option('pb_sync_delete')['pb_sync_delete_field']) && get_option('pb_sync_delete')['pb_sync_delete_field']==="1" && $post_type == 'projects'){

        $response = wp_remote_request($url, array(
            'headers' => array( 'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token']),
            'method' => 'DELETE'
        ));
    }
}
add_action('before_delete_post', 'pb_sync_delete_post');

// deactivate publish button
add_action('admin_head', 'hide_publish_button');
function hide_publish_button() {
    global $post;

    if (!isset($post->post_type)){
        return;
    }

    if(pb_keycloak_is_authenticated() && $post->post_type === 'projects') {
        return;
    }
    ?>
    <script type="text/javascript">
        window.onload = function() {
            document.getElementById('publish').disabled = true;
        }
    </script>
    <?php
}

// init some important globals
function pb_init_values() {

    session_start();

    // receive and save access token
    if(isset($_SESSION['wejf4uergzu'])) {
        $GLOBALS['pb_access_token'] = $_SESSION['wejf4uergzu'];
    }
    else
        $GLOBALS['pb_access_token'] = "";

    // send redirect-uri to redirect.php
    $_SESSION['pb_plugins_url'] = plugins_url('/projektboerse/redirect.php');

    // get the name of the currently logged-in keycloak user
    if(!isset($GLOBALS['prox_username']) && wp_remote_retrieve_response_code(wp_remote_get('https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/userinfo', array('headers' => array(
            'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])))) === 200){
        $GLOBALS['prox_username'] = json_decode(wp_remote_retrieve_body(wp_remote_get('https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/userinfo', array('headers' => array(
            'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])))), true)['name'];
    }
    else
        $GLOBALS['prox_username'] = "none";

    // send token-endpoint uri to redirect.php
    $_SESSION['pb_oauth_token_uri'] = get_option('token_api_url')['token_url'];
}
add_action( 'init', 'pb_init_values');

function generateRandomString($length = 50) {
    return hash('sha512', substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length));
}

// custom post-type "projects":
function wpt_project_post_type() {
    $labels = array(
        'name'               => __( 'Projekte' ),
        'singular_name'      => __( 'Projekt' ),
        'add_new'            => __( 'Projekt erstellen' ),
        'add_new_item'       => __( 'Neues Projekt anlegen' ),
        'edit_item'          => __( 'Projekt bearbeiten' ),
        'new_item'           => __( 'Neues Projekt anlegen' ),
        'view_item'          => __( 'Projekt anzeigen' ),
        'view_items'         => __( 'Projekte anzeigen' ),
        'all_items'          => __( 'Alle Projekte' ),
        'search_items'       => __( 'Projekte suchen' ),
        'not_found'          => __( 'Keine Projekte gefunden' ),
        'not_found_in_trash' => __( 'Keine Projekte im Papierkorb gefunden' )
    );
    $supports = array(
        'title',
        'editor',
        'author',
    );
    $args = array(
        'labels'               => $labels,
        'supports'             => $supports,
        'public'               => true,
        'capability_type'      => 'post',
        'rewrite'              => array( 'slug' => 'projects' ),
        'has_archive'          => 'projects',
        'menu_position'        => 30,
        'menu_icon'            => 'dashicons-welcome-learn-more',
        'register_meta_box_cb' => 'pb_wporg_add_custom_box',
    );
    register_post_type( 'projects', $args );
}
add_action( 'init', 'wpt_project_post_type');

// add a [sc_pb_meta] shortcode at the end of every project-type-post
add_filter('the_content', 'modify_content');
function modify_content($content) {
    global $post;
    //TODO make a setting out of "is_archive"
    if($post->post_type === 'projects' && !is_archive())
        if(isset(get_option('pb_add_datetime')['pb_add_datetime_field'])) {
            return $content . "[sc_pb_meta]"."[sc_pb_meta_dateandtime]" ;
        }
        else
            return $content . "[sc_pb_meta]";
    else
        return $content;
}

// TODO alter this function to fit PROX
// defines what the shortcode should display
function sc_pb_meta_function(){
    global $post;

    $project_status = get_post_meta($post->ID, '_pb_wporg_meta_project_status', true);

    if ($project_status === 'VERFÜGBAR') $project_status = 'verfügbar';
    elseif ($project_status === 'LAUFEND') $project_status = 'laufend';
    elseif ($project_status === 'ABGESCHLOSSEN') $project_status = 'abgeschlossen';

    $shortcode_meta = "<span style=\"font-size: 12px;\" > 
                            <table style=\"width:100%\">
                                  <tr>
                                    <th>Projektstatus</th>
                                  </tr>
                                  <tr>
                                    <td>".$project_status."</td>

                                  </tr>
                            </table>
                       </span>";
    return $shortcode_meta;
}
add_shortcode('sc_pb_meta', 'sc_pb_meta_function');

// determine the creation date and time of the current projekt
function sc_pb_meta_dateandtime(){
    global $post;
    return "<span style='font-size: 10px;'> <i>Projekt erstellt am: ".get_the_date("d. F Y, H:i", $post->ID)." Uhr</i></span>";
}
add_shortcode('sc_pb_meta_dateandtime', 'sc_pb_meta_dateandtime');

// Add custom metabox paragraph for THK projects on project pages
function pb_wporg_add_custom_box()
{
    //$screens = ['post', 'wporg_cpt'];
    //foreach ($screens as $screen) {
        add_meta_box(
            'studiengang_wporg_box_id',           // Unique ID
            'Prox Projektattribute',  // Box title
            'pb_custom_box_html',  // Content callback, must be of type callable
            'projects'                  // Post type
        );
    //}
}
add_action('add_meta_boxes', 'pb_wporg_add_custom_box');

// TODO edit to fit PROX
// HTML input fields for post metadata. Loads the last saved data!
function pb_custom_box_html($post)
{
    $meta = get_post_meta( $post->ID );
    $study_courses = pb_get_studyCourses();

    if(!isset( $meta['_pb_wporg_meta_checkbox'][0])){  // if meta-key is unset it means that we are about to create a new post
        $checkbox_value = (isset(get_option('pb_send_to_pb')['pb_send_to_pb_field'])) ? 1 : 0;  // set default value for checkbox depending on user settings
    }
    else { // user is editing an existing project and we saved a value ("1" or "0") for the checkbox before
        $checkbox_value = ('1' === $meta['_pb_wporg_meta_checkbox'][0] ) ? 1 : 0;
    }

//    $study_course = get_post_meta($post->ID, '_pb_wporg_meta_course', true);
//    $project_type = get_post_meta($post->ID, '_pb_wporg_meta_project_type', true);
    $project_status = get_post_meta($post->ID, '_pb_wporg_meta_project_status', true);

    if(!isset( $meta['_pb_wporg_meta_supervisor'][0])){  // if meta-key is unset it means that we are about to create a new post
        $supervisor = get_option('pb_add_supervisor')['pb_add_supervisor_field'];
    }
    else {
        $supervisor = $meta['_pb_wporg_meta_supervisor'][0];
    }

    ?>
    <p>
        <b>Status der Autorisierung: </b>
        <?php
            if(pb_keycloak_is_authenticated()===true) {
                echo "<b><a style=\"color:green\"> <br />Authentifiziert &check;</a></b>";
            }
            else {
                echo "<b><text style=\"color:red\"> <br />&cross; Keine Synchronisierung mit Prox möglich: es können keine Projekte veröffentlicht werden. Bitte vorher <a href='https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/auth?client_id=wordpress-plugin&redirect_uri=".plugins_url('/projektboerse/redirect.php')."&response_type=code&scope=openid&state=E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT' target='_blank'>einloggen</a>!</text></b>";
            }
        ?>
    </p>
    
    <p>
        <input type="hidden" name="checkbox_value" value="1"/>
    </p>
    <p>
        <label for="pb_wporg_project_status"><strong>Projektstatus:</strong><br /></label>
        <select name="pb_wporg_project_status" id="pb_wporg_project_status" class="postbox">
            <option value="VERFÜGBAR" <?php selected( $project_status, "VERFÜGBAR" ); ?>>verfügbar</option>
            <option value="LAUFEND" <?php selected( $project_status, "LAUFEND" ); ?>>laufend</option>
            <option value="ABGESCHLOSSEN" <?php selected( $project_status, "ABGESCHLOSSEN" ); ?>>abgeschlossen</option>
        </select>
    </p>
    <p>
        <label for="pb_wporg_project_supervisor"><strong>Name des Projektbetreuers:</strong><br /></label>
        <input type="text" name="pb_wporg_project_supervisor" id="pb_wporg_project_supervisor" value="<?php echo $supervisor?>" >
    </p>
<!--    ------------- study courses and attached modules -------->
    <p>
        <label><strong>Projekt verfügbar für:</strong><br /></label>
        <?php
            if(!is_array($study_courses)) {
                echo "[ " . $study_courses . " ]";
                return;
            }

            foreach ($study_courses as $key) {
                $modules = pb_get_studyCoursesModules($key['_links']['modules']['href']);
                echo "<i><p><b>".$key['name']." (".$key['academicDegree'].")</b></p>";
                foreach ($modules as $key2) {
                    echo "<input type='checkbox' id='".$key2['id']."' name='studyModules[]' value='".$key2['id']."'>  ";
                    echo "<label for='".$key2['id']."'>".$key2['name']."&nbsp;&nbsp;&nbsp;&nbsp;</label>";
                }
                echo "</i>";

            }
        ?>
    </p>

    <?php
}

// TODO edit to fit PROX
// save the pb-metabox data into a unique meta key
function pb_wporg_save_postdata($post_id)
{
    // checkbox
    $checkbox_value = ( isset( $_POST['checkbox_value'] ) && '1' === $_POST['checkbox_value'] ) ? 1 : 0; // Input var okay.
    update_post_meta( $post_id, '_pb_wporg_meta_checkbox', esc_attr( $checkbox_value ) );

    // supervisor
    if (array_key_exists('pb_wporg_project_supervisor', $_POST)) {

        update_post_meta(
            $post_id,
            '_pb_wporg_meta_supervisor',
            $_POST['pb_wporg_project_supervisor']
        );
    }

//    // study course
//    if (array_key_exists('pb_wporg_course', $_POST)) {
//
//        update_post_meta(
//            $post_id,
//            '_pb_wporg_meta_course',
//            //implode(', ', $_POST['pb_wporg_course']) // array to single string
//            $_POST['pb_wporg_course']
//        );
//    }

//    // project type
//    if (array_key_exists('pb_wporg_project_type', $_POST)) {
//        update_post_meta(
//            $post_id,
//            '_pb_wporg_meta_project_type',
//            $_POST['pb_wporg_project_type']
//        );
//    }

    // project status
    if (array_key_exists('pb_wporg_project_status', $_POST)) {
        update_post_meta(
            $post_id,
            '_pb_wporg_meta_project_status',
            $_POST['pb_wporg_project_status']
        );
    }

}
add_action('publish_projects', 'pb_wporg_save_postdata', 9);

//// retrieves pretty study-course-list from PB REST API
//function get_pb_courses() {
//
//    // TODO remove hardcoded URL before release:
//    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/studyCourses';
//    //$url = 'https://gpdev.archi-lab.io/studyCourses';
//
//    $response = wp_remote_get($url); // get study courses from PB API
//    $response_body = json_decode($response['body'], TRUE); // we only need the body of the response
//    if(array_key_exists('status', $response_body) && $response_body['status']===404) // if status=404 the api was not found
//        return array("ERROR: Could not retrieve API data...");
//
//    $courses = array_column_recursive($response_body,'name'); // go get all study courses
//    $degree = array_column_recursive($response_body, 'academicDegree'); // get the academic degree of all couses
//
//    $id = array();
//
//    // dirty implementation to get the ID of each course (which in fact is the self href in the projektbörse api)
//    for ($i = 0; $i < count($courses); $i++) {
//        array_push($id, $response_body['_embedded']['studyCourses'][$i]['id']);
//    }
//
//    // we substitute the index number-key (0, 1, 2, ...) of the course array with the "href-ID" of the API as the key
//    for ($i = 0; $i < count($courses); $i++) {
//        $courses[$i] = $courses[$i].' ('.$degree[$i].')'; // course name + academic degree = new full course name
//
//        // key substitution:
//        $courses[$id[$i]] = $courses[$i];
//        unset($courses[$i]);
//    }
//
//   return $courses;
//}

// generates an array of study courses and its modules
function pb_get_studyCourses() {
    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projectStudyCourses';
    $response = wp_remote_get($url);
    if(is_wp_error($response) || wp_remote_retrieve_response_code( $response ) === 404) return "API nicht erreichbar";
    else return json_decode($response['body'], TRUE)['_embedded']['projectStudyCourses'];
}

function pb_get_studyCoursesModules($url) {
    //$url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projectStudyCourses';
    $response = wp_remote_get($url);
    return json_decode($response['body'], TRUE)['_embedded']['projectModules'];
}
//add_action( 'init', 'pb_get_studyCourses');

// get all values of a multidimensional array
function array_column_recursive(array $haystack, $needle) {
    $found = [];
    array_walk_recursive($haystack, function($value, $key) use (&$found, $needle) {
        if ($key == $needle)
            $found[] = $value;
    });
    return $found;
}

// Import own Projects from Prox
function pb_import_pb_projects() {

    echo '<style>
            body, td, textarea, input, select, button {
              font-family: sans-serif;
              font-size: 12px;
            } 
            table {
              font-family: arial, sans-serif;
              
              border-collapse: collapse;
              width: 100%;
            }
            tbody {
                display:block;
                height:50px;
                overflow:auto;
            }
            thead, tbody tr {
                display:table;
                width:100%;
                table-layout:fixed;/* even columns width , fix width of table too*/
            }
            thead {
                width: calc( 100% - 1em )/* scrollbar is average 1em/16px width, remove it from thead width */
            }
        
            td, th {
              border: 1px solid #cccccc;
              text-align: center;
              padding: 8px;
            }
        
            tr:nth-child(even) {
              background-color: #dddddd;
            }
          </style>';
    if(pb_keycloak_is_authenticated()===false) {
        echo "Nicht authentifiziert. Bitte in Prox einloggen.";
        exit;
    }
    $projects = pb_get_projects();
    ?>
        <script>
        function checkedall() {

            for (var i = 0; i < document.forms[0].elements.length; i++) {
                document.forms[0].elements[i].checked = true;
            }
        }

        function checkednone() {

            for (var i = 0; i < document.forms[0].elements.length; i++) {
                document.forms[0].elements[i].checked = false;
            }
        }
        </script>
    <?php

    echo "<h1>Projekt-Synchronisation</h1>";
    echo "Die folgende Liste zeigt alle Ihre in der Projektbörse befindlichen Projekte. Sie können diese normalerweise alle ausgewählt lassen, da nur in WordPress nicht vorhandene bzw. in Prox geänderte Projekte importiert/synchronisiert werden.<br/><br/>";

    echo "<form action=". admin_url('admin-post.php') ." method='post'>";
    echo "<button type='button' onclick='checkedall()'>Alle auswählen</button> ";
    echo "<button type='button' onclick='checkednone()'>Keine auswählen</button> ";
    echo "<input type='submit' name='formSubmit' value='Jetzt synchronisieren...' /><br /><br />";


    echo "<input type='hidden' name='action' value='pb_import_pb_projects_step2'>";
    ?>
    <table>
        <thead>
            <tr>
                <th width="20%">Import?</th>
                <th>Projekttitel</th>
            </tr>
        </thead>
    <?php
    echo "<tbody style='height:395px;display:block;overflow:scroll'>";
    foreach ($projects as $p) {

        if($p['creatorName']=== $GLOBALS['prox_username']) {
            echo "<tr><td width=\"20%\"><input type='checkbox' name='myinput[]' value=" . $p['id'] . " id=". $p['id'] ." checked></td>";
            //echo "<td><label for=" . $p['id'] . ">".$p['title']."</label></td></tr>";
            echo "<td><label for=" . $p['id'] . ">".$p['name']."</label></td></tr>";
        }
    }
    echo "</tbody>";
    echo "</table>";
    echo "</form>";



}
add_action( 'admin_post_pb_import_pb_projects_step2', 'pb_import_pb_projects_step2' );


function pb_import_pb_projects_step2 (){

    echo '<style>
            body, td, textarea, input, select, button {
              font-family: sans-serif;
              font-size: 14px;
            }
          </style>';

    if(!empty($_POST['myinput'])) {
        $projects_to_import = $_POST['myinput'];
    }
    else $projects_to_import = array();

    $projects = pb_get_projects();

    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present

    $count = 0;
    $deleted = 0;


    // delete projects in wordpress which are not present in prox (if the corresponding checkbox is checked)
    if (isset(get_option('pb_smart_delete_on_import')['pb_smart_delete_on_import_field'])) {
        $all_wp_projects = get_posts(array(
            'post_type' => 'projects',
        ));

        foreach ($all_wp_projects as $key) {
            $found = false;

            foreach ($projects as $p) {
                if (get_post_meta($key->ID, 'pb_project_id', true) == $p['id']){
                    $found = true;
                }
            }
            if($found === false) {
                wp_delete_post($key->ID);
                $deleted++;
            }
        }
    }


    // build new post:
    foreach ($projects as $key) {

        //search for the pb-id in all of the projects meta-keys:
        $args = array(
            'meta_key' => 'pb_project_id',
            'meta_value' => $key['id'],
            'post_type' => 'projects',

        );
        $posts_array = get_posts($args); // $posts_array is empty = no post with this id = we can safely import

        // if we find a post in wordpress with the given ID, we need to save its 'modified' date and time for later comparison
        if( !empty($posts_array)  ) {
            $modified = get_post_meta($posts_array[0]->ID, 'pb_project_modified', true);
        }
        else
            $modified = ""; // save empty string to prevent php from throwing an error later

        // retrieve the 'modified' date and time from prox-server
        $server_modified = json_decode(wp_remote_retrieve_body(wp_remote_get($url."/".$key['id'], array('headers' => array(
            'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])))), true)['modified'];

        // import project in wordpress if it's not here
        // TODO make creatorName dynamic
        if ($key['creatorName'] === 'Prof. Dozent' && empty($posts_array) && in_array($key['id'], $projects_to_import) && !empty($projects)) {  // only import if it's the users post AND if the post (the pb-project-id) is not already there
            $imported_project = array(
                'post_type' => 'projects',
                'post_title' => $key['name'],
                'post_content' => $key['description'],
                'post_status' => 'publish'
            );
            $post_id = wp_insert_post($imported_project);

            if(!is_wp_error($post_id))
                $count++;

            update_post_meta($post_id, 'pb_project_id', $key['id']); // add the pb-project id to the metadata, so we can sync-delete each post
            update_post_meta($post_id, '_pb_wporg_meta_project_status', $key['status']);
            update_post_meta($post_id, '_pb_wporg_meta_checkbox', 1);
            update_post_meta($post_id, '_pb_wporg_meta_supervisor', $key['supervisorName']);
            update_post_meta($post_id, 'pb_project_modified', $key['modified']);
        }
        // validating the view: if the project is already in wordpress,
        // we need to check every post for changes on the server side
        // (compare local 'modified' value with the prox server value):
        else if (   !empty($modified) &&
                    !empty($server_modified) &&
                    $modified!=$server_modified &&
                    in_array($key['id'], $projects_to_import) &&
                    !empty($projects)    ){
            $imported_project = array(
                'ID'           => $posts_array[0]->ID,
                'post_type' => 'projects',
                'post_title' => $key['name'],
                'post_content' => $key['description'],
                'post_status' => 'publish'
            );
            $post_id = wp_update_post($imported_project);

            if(!is_wp_error($post_id))
                $count++;

            update_post_meta($post_id, 'pb_project_id', $key['id']); // add the pb-project id to the metadata, so we can sync-delete each post
            update_post_meta($post_id, '_pb_wporg_meta_project_status', $key['status']);
            update_post_meta($post_id, '_pb_wporg_meta_checkbox', 1);
            update_post_meta($post_id, '_pb_wporg_meta_supervisor', $key['supervisorName']);
            update_post_meta($post_id, 'pb_project_modified', $key['modified']);
        }
    }
    echo 'Projekte importiert: ' . $count;
    if($deleted > 0) {
        echo '<br />Projekte aus WP gelöscht: ' . $deleted;
    }
    if (!empty($projects_to_import) && $count === 0) {
        echo "<br /><br />Alle Projekte in der WordPress-Datenbank sind auf dem neuesten Stand.";
    }
    echo "<br /><br /><button type='button' onclick='window.close()'>Schließen</button> ";
}

// return a list of all prox-projects
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



// --------------------------- Debugging Section -------------------------------

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