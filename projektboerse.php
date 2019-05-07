<?php
/*
 * Plugin Name: TH Köln Projektbörsen-Klient
 * Description: Projekte erstellen und mit der TH-Köln Projekt- und Themenbörse synchronisieren.
 * Author: Andreas Paulick
 * Author URI: https://github.com/andreaspaulick
 * Version: 0.2
 * Plugin URI: https://github.com/andreaspaulick/projektboerse
*/

defined( 'ABSPATH' ) or exit;
define( 'DEFAULT_API_URL' , 'http://localhost:8045/' ); // default link to the PB API
define( 'DEFAULT_KEYCLOAK_API_URL' , 'http://localhost:8180/auth/realms/pboerse/protocol/openid-connect/token' ); // default link to the keycloak API
define( 'PROX_TOKEN_API_URL' , 'https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/token' );

define( 'USE_LOCAL_PB' , FALSE ); // here you can choose whether to use the local "pb dummy" or the official test version of the PB via internet

// --- Temporary Constants:
define ('PB_ACCESS_TOKEN', 'eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJua0RWOGY1cFZhaTBDSnJ0UHh3OEI1eHlUaFZqYlBiWTZEemlVdHc1M2hjIn0.eyJqdGkiOiIzODIwM2M4ZC05Y2QyLTQ3OWItYjUyMy00NGJlOGU2ZTUyMzIiLCJleHAiOjE1NTcyMjg2ODUsIm5iZiI6MCwiaWF0IjoxNTU3MjIxNDg1LCJpc3MiOiJodHRwczovL2xvZ2luLmNvYWxiYXNlLmlvL2F1dGgvcmVhbG1zL3Byb3giLCJhdWQiOiJhY2NvdW50Iiwic3ViIjoiMWIyOWU0MWUtYWFiMi00NzU3LThlYTItN2UyZGFjYTIwN2U2IiwidHlwIjoiQmVhcmVyIiwiYXpwIjoid29yZHByZXNzLXBsdWdpbiIsImF1dGhfdGltZSI6MTU1NzIyMTQ4NSwic2Vzc2lvbl9zdGF0ZSI6IjQ4NzkzNjU2LTZjZDUtNDJhYS05MzU1LTFiMWY1YjQ1NzY3YiIsImFjciI6IjEiLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsiRG96ZW50Iiwib2ZmbGluZV9hY2Nlc3MiLCJ1bWFfYXV0aG9yaXphdGlvbiJdfSwicmVzb3VyY2VfYWNjZXNzIjp7ImFjY291bnQiOnsicm9sZXMiOlsibWFuYWdlLWFjY291bnQiLCJtYW5hZ2UtYWNjb3VudC1saW5rcyIsInZpZXctcHJvZmlsZSJdfX0sInNjb3BlIjoib3BlbmlkIHByb2ZpbGUgZW1haWwiLCJlbWFpbF92ZXJpZmllZCI6dHJ1ZSwibmFtZSI6IlByb2YuIERvemVudCIsInByZWZlcnJlZF91c2VybmFtZSI6ImRvemVudCIsImdpdmVuX25hbWUiOiJQcm9mLiIsImZhbWlseV9uYW1lIjoiRG96ZW50In0.T8FUuLJdIgqiKlkK20Ko7Mjtnomch0Xdfgth8SP-VQMjAtsFKHIn4tXYZkwqlfldvmUytPJyQ8XvFNxce3XQbDNHRAUziK4Ji3nJMMamCHHWjxw2wQii7NI9_xc5dJAY1jSvx8V9M9TTJfiK1tr5prNBlxiQW9-GIGS6iMOQ6q-elF86E2lBf6Yszph_He1XCCDDyAt_sBjtwyyJBEG27-uLSGLzUb-EWu89HE6csBn-twSOsxG2i9Pa-jbW5cucCxTdrxRvbMXvmbyFIooFJdpEPmeYJkLUnUYmUG6UF8C9PeE5CeUCUhpTtd4cMHwBvhGFpEL51AmgGj-dHPFzyg');
define ('PB_REFRESH_TOKEN', '');
define ('PB_USER_NAME', 'Prof. Dozent');
// ---------------------------

include 'redirect.php';
include 'pb_options.php';


/**
 * post Variable Reference: https://codex.wordpress.org/Function_Reference/$post
 */
function post_published_api_call( $ID, $post) {

    if( get_post_meta($post->ID, '_pb_wporg_meta_checkbox', true) !== "1" ) return; // return (do nothing) if checkbox "also send to pb" is not checked

        // TODO alter URL
        if(USE_LOCAL_PB === FALSE) {
            $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present
        }
        else {
            $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/posts/jsonadd'; // add json-consuming ressource to url. Strip last slash if present
        }

        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content); // at the moment all tags are stripped (images won't be transferred)

        if(metadata_exists( 'post', $post->ID, '_pb_wporg_meta_project_status' )){
            $sup_name = get_post_meta($post->ID, '_pb_wporg_meta_supervisor', true);
        }
        else
            $sup_name = get_option('pb_add_supervisor')['pb_add_supervisor_field'];


        // data for local DUMMY PROJEKTBÖRSE
        if(USE_LOCAL_PB === TRUE) {
            $post_data = array(
                'status' => get_post_meta($post->ID, '_pb_wporg_meta_project_status', true),
                'title' => $title,
                'content' => $content,
                'course' => implode(",", get_post_meta($post->ID, '_pb_wporg_meta_course', true)),
                'type' => implode(",", get_post_meta($post->ID, '_pb_wporg_meta_project_type', true)),
                'user_login' => wp_get_current_user()->user_login
            );
        }
        else {
            // data for Testserver Projektbörse --> https://gpdev.archi-lab.io/projects
            $post_data = array(
                // TODO creatorID ermitteln!
                'creatorID' => '1b29e41e-aab2-4757-8ea2-7e2daca207e6',
                // TODO creatorName ermitteln!
                'creatorName' => 'Prof. Dozent',
                'description' => $content,
                'name' => $title,
                'status' => get_post_meta($post->ID, '_pb_wporg_meta_project_status', true),
                'supervisorName' => $sup_name
            );
        }

        $json_post = json_encode($post_data);

        // get the project-id of the pb-post (if set)
        $pb_project_id = get_post_meta($post->ID, 'pb_project_id', true);

        if( metadata_exists( 'post', $post->ID, 'pb_project_id' )){ // means the project is edited

            // TODO: change URL to PB
            //$url2 = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/posts/id/'.$pb_project_id ;
            $url2 = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/projects/'.$pb_project_id ;

//            if(get_option('token_enable_checkbox')['token_enable']==="0") { // if true: don't use keycloak-authentication
//                $data = wp_remote_request($url2, array(
//                    'headers' => array( 'Content-Type' => 'application/json; charset=utf-8'),
//                    'body' => $json_post,
//                    'method' => 'PUT'
//                ));
//
//                // get and save the etag
//                $etag = wp_remote_retrieve_headers(
//                    wp_remote_get($url2)
//                )['etag'];
//                update_post_meta( $post->ID, 'pb_project_etag', $etag);
//            }
//            else { // use keycloak-authentication
//                $token_response = get_keycloak_token_response();
//                $keycloak_access_token = extract_keycloak_access_token($token_response);
//
//                if ($keycloak_access_token === "TOKEN_REQUEST_ERROR"){
//                    return;
//                }

                if(USE_LOCAL_PB === TRUE) {
                    $data = wp_remote_request($url2, array(
                        'headers' => array( 'Content-Type' => 'application/json; charset=utf-8',
                            'Authorization' => 'Bearer ' . $keycloak_access_token),
                            'body'          => json_encode( array(
                            'id'            => $pb_project_id,
                            'status'        => get_post_meta($post->ID, '_pb_wporg_meta_project_status', true),
                            'title'         => $title,
                            'content'       => $content,
                            'course'        => implode(",", get_post_meta($post->ID, '_pb_wporg_meta_course', true)),
                            'type'          => implode(",", get_post_meta($post->ID, '_pb_wporg_meta_project_type', true)),
                            'user_login'    => wp_get_current_user()->user_login
                        )),
                        'method' => 'PUT'
                    ));

//                    // get and save the etag
                    $etag = wp_remote_retrieve_headers(
                        wp_remote_get($url2,
                            array('headers' => array(
                                'Authorization' => 'Bearer ' . $keycloak_access_token)
                            )
                        )
                    )['etag'];
                    update_post_meta( $post->ID, 'pb_project_etag', $etag);

                    //logout session
                    keycloak_session_logout($token_response);
                }
                else { // use official prox
                    $data = wp_remote_request($url2, array(
                        'headers' => array( 'Content-Type' => 'application/json; charset=utf-8',
                            'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN),
                        'body'          => json_encode( array(
                            'id'            => $pb_project_id,
                            // TODO creatorID ermitteln!
                            'creatorID' => '1b29e41e-aab2-4757-8ea2-7e2daca207e6',
                            // TODO creatorName ermitteln!
                            'creatorName' => 'Prof. Dozent',
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
                                'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN)
                            )
                        )
                    ), true)['modified'];
                    update_post_meta( $post->ID, 'pb_project_modified', $modified);

//                    // get and save the etag
//                    $etag = wp_remote_retrieve_headers(
//                        wp_remote_get($url2,
//                            array('headers' => array(
//                                'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN)
//                            )
//                        )
//                    )['etag'];
//                    update_post_meta( $post->ID, 'pb_project_etag', $etag);
//
//                    //logout session
//                    keycloak_session_logout($token_response);
                }

            //}
        } // end projects exists
        else { // no previously saved project-id = new project
//            if(get_option('token_enable_checkbox')['token_enable']==="0") { // if true: don't use keycloak-authentication
//
//                $data = wp_remote_post($url, array(
//                    'headers' => array( 'Content-Type' => 'application/json; charset=utf-8'),
//                    'body' => $json_post,
//                    'method' => 'POST'
//                ));
//
//                // get and save the etag
//                $etag = wp_remote_retrieve_headers(
//                    wp_remote_get(rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/posts/id/' . json_decode(wp_remote_retrieve_body($data))->id)
//                )['etag'];
//                update_post_meta( $post->ID, 'pb_project_etag', $etag);
//
//                // save the id of the pb-project
//                update_post_meta( $post->ID, 'pb_project_id', json_decode(wp_remote_retrieve_body($data))->id);
//            }
//            else { // use keycloak-authentication
//                $token_response = get_keycloak_token_response();
//                $keycloak_access_token = extract_keycloak_access_token($token_response);
//
//                if ($keycloak_access_token === "TOKEN_REQUEST_ERROR"){
//                    return;
//                }

                $data = wp_remote_post($url, array(
                    'headers' => array( 'Content-Type' => 'application/json; charset=utf-8',
                        'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN),
                    'body' => $json_post,
                    'method' => 'POST'
                ));

                // save the "modified" date for future comparison
                $modified = json_decode(wp_remote_retrieve_body(
                    wp_remote_get(rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects/' . json_decode(wp_remote_retrieve_body($data))->id,
                        array('headers' => array(
                            'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN)
                        )
                    )
                ), true)['modified'];
                update_post_meta( $post->ID, 'pb_project_modified', $modified);


                // save the id of the pb-project
                update_post_meta( $post->ID, 'pb_project_id', json_decode(wp_remote_retrieve_body($data))->id);
//                //logout session
//                keycloak_session_logout($token_response);
            //}

        }
}
add_action( 'publish_projects', 'post_published_api_call', 10, 2);

function pb_keycloak_is_authenticated() {
    $response = wp_remote_get('https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/userinfo', array('headers' => array(
        'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN)));
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

    //TODO alter URL
    //$url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/posts/delete/'.$pb_project_id ;
    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/projects/'.$pb_project_id ;

//    if(get_option('token_enable_checkbox')['token_enable']==="0") { // don't use keycloak auth
//        if(isset(get_option('pb_sync_delete')['pb_sync_delete_field']) && get_option('pb_sync_delete')['pb_sync_delete_field']==="1" && $post_type == 'projects'){
//
//            $response = wp_remote_request($url, array('method' => 'DELETE' ));
//        }
//    }
//    else { // use keycloak auth
        if(isset(get_option('pb_sync_delete')['pb_sync_delete_field']) && get_option('pb_sync_delete')['pb_sync_delete_field']==="1" && $post_type == 'projects'){

//            $token_response = get_keycloak_token_response();
//            $keycloak_access_token = extract_keycloak_access_token($token_response);
//
//            if ($keycloak_access_token === "TOKEN_REQUEST_ERROR"){
//                return;
//            }

            $response = wp_remote_request($url, array(
                'headers' => array( 'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN),
                'method' => 'DELETE'
            ));

//            //logout session
//            keycloak_session_logout($token_response);
        }
    //}
}
add_action('before_delete_post', 'pb_sync_delete_post');

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


//// add a [sc_pb_meta] shortcode at the end of every project-type-post
//add_filter('the_content', 'modify_content');
//function modify_content($content) {
//    global $post;
//
//    //TODO make a setting out of "is_archive"
//    if($post->post_type === 'projects' && !is_archive())
//        if(isset(get_option('pb_add_datetime')['pb_add_datetime_field'])) {
//            return $content . "[sc_pb_meta]"."[sc_pb_meta_dateandtime]" ;
//        }
//        else
//            return $content . "[sc_pb_meta]";
//    else
//        return $content;
//}

//// add type-tags (PP, BA, MA) to the title
//add_filter('the_title', 'modify_title', 10, 2);
//
//function modify_title($title, $id) {
//    global $post;
//
//    // bugfix for themes (no post object available)
//    if( !is_object($post) )
//        return $title;
//
//    $types = get_post_meta($id, '_pb_wporg_meta_project_type', true);
//    $add_title = (isset(get_option('pb_add_type_tag')['pb_add_type_tag_field']) && "1" === get_option('pb_add_type_tag')['pb_add_type_tag_field']) ? 1 : 0;
//
//    if (!empty($types) && $post->post_type === 'projects' && $add_title === 1)
//        return "[".implode("/",$types)."] ".$title;
//    else
//        return $title;
//}


// TODO alter this function to fit PROX
// defines what the shortcode should display
function sc_pb_meta_function(){
    global $post;

    $study_courses = get_post_meta($post->ID, '_pb_wporg_meta_course', true); // get array of selected courses
    $project_type = get_post_meta($post->ID, '_pb_wporg_meta_project_type', true);

    if(!empty($project_type)){
        foreach ($project_type as &$type) {
            if ($type === 'PP') $type = 'Praxisprojekt';
            elseif ($type === 'BA') $type = 'Bachelorarbeit';
            elseif ($type === 'MA') $type = 'Masterarbeit';
        }
    }
    else
        $project_type = ["nicht spezifiziert"];

    $project_status = get_post_meta($post->ID, '_pb_wporg_meta_project_status', true);

    if ($project_status === 'VERFÜGBAR') $project_status = 'verfügbar';
    elseif ($project_status === 'LAUFEND') $project_status = 'laufend';
    elseif ($project_status === 'ABGESCHLOSSEN') $project_status = 'abgeschlossen';

    $all_courses = get_pb_courses(); // get array of all courses
    $out_courses = array(); // target array for human-readable study course names

    // translate long course-id into human readable course-name:
    if (!empty($study_courses)) {
        foreach ($study_courses as $key => $value) {
            array_push($out_courses, $all_courses[$value]);
        }
    }
    else
        array_push($out_courses, "nicht spezifiziert");

    $shortcode_meta = "<span style=\"font-size: 12px;\" > 
                            <table style=\"width:100%\">
                                  <tr>
                                    <th>Projektstatus</th>
                                    <th>Geeignete Studiengänge</th>
                                    <th>Geeignet für</th>
                                  </tr>
                                  <tr>
                                    <td>".$project_status."</td>
                                    <td>".implode('<br />', $out_courses)."</td>
                                    <td>".implode('<br />', $project_type)."</td>
                                  </tr>
                            </table>
                       </span>";
    return $shortcode_meta;
}
add_shortcode('sc_pb_meta', 'sc_pb_meta_function');

function sc_pb_meta_dateandtime(){
    global $post;
    return "<span style='font-size: 10px;'> <i>Projekt erstellt am: ".get_the_date("d. F Y, H:i", $post->ID)." Uhr</i></span>";
}
add_shortcode('sc_pb_meta_dateandtime', 'sc_pb_meta_dateandtime');

// Add custom metabox paragraph for THK projects on "post" and "wporg_cpt" pages
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

    if(!isset( $meta['_pb_wporg_meta_checkbox'][0])){  // if meta-key is unset it means that we are about to create a new post
        $checkbox_value = (isset(get_option('pb_send_to_pb')['pb_send_to_pb_field'])) ? 1 : 0;  // set default value for checkbox depending on user settings
    }
    else { // user is editing an existing project and we saved a value ("1" or "0") for the checkbox before
        $checkbox_value = ('1' === $meta['_pb_wporg_meta_checkbox'][0] ) ? 1 : 0;
    }

    $study_course = get_post_meta($post->ID, '_pb_wporg_meta_course', true);
    $project_type = get_post_meta($post->ID, '_pb_wporg_meta_project_type', true);
    $project_status = get_post_meta($post->ID, '_pb_wporg_meta_project_status', true);

    if(!isset( $meta['_pb_wporg_meta_supervisor'][0])){  // if meta-key is unset it means that we are about to create a new post
        $supervisor = get_option('pb_add_supervisor')['pb_add_supervisor_field'];
    }
    else {
        $supervisor = $meta['_pb_wporg_meta_supervisor'][0];
    }

    ?>
    <p>
        <b>Auth Status: </b>
        <?php
            if(pb_keycloak_is_authenticated()===true) {
                echo "<i> authentifiziert</i>";
            }
            else {
                echo "<i> Keine Synchronisierung mit Prox möglich. Bitte <a href='https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/auth?client_id=wordpress-plugin&redirect_uri=".plugins_url('/projektboerse/redirect.php')."&response_type=code&scope=openid&state=E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT' target='_blank'>einloggen</a>!</i>";
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
    <p>
        <label for="pb_wporg_course"><strong>Studiengang:</strong><br /></label>
        <select name="pb_wporg_course[]" id="pb_wporg_course" class="postbox" multiple="multiple" size="6">
            <?php
                $data = get_pb_courses();  // get all the courses via REST-API
                foreach($data as $key => $item){
            ?>
                <option value="<?php echo $key;?>" <?php echo ( !empty( $study_course ) && in_array( $key, $study_course ) ? ' selected="selected"' : '' ) ?>><?php echo $item; ?></option>
            <?php
                }
            ?>
        </select>
    </p>
    <p>
        <label for="pb_wporg_project_type"><strong>Projekt geeignet für:</strong><br /></label>
        <input type="checkbox" name="pb_wporg_project_type[]" id="pb_wporg_project_type_pp" value="PP" <?php echo ( !isset( $meta['_pb_wporg_meta_project_type'][0]) || !empty( $project_type ) && in_array( 'PP', $project_type ) ? ' checked' : '' ) ?>>
        <label for="pb_wporg_project_type_pp">Praxisprojekt<br /></label>

        <input type="checkbox" name="pb_wporg_project_type[]" id="pb_wporg_project_type_ba" value="BA" <?php echo ( !isset( $meta['_pb_wporg_meta_project_type'][0]) || !empty( $project_type ) && in_array( 'BA', $project_type ) ? ' checked' : '' ) ?>>
        <label for="pb_wporg_project_type_ba">Bachelorarbeit<br /></label>

        <input type="checkbox" name="pb_wporg_project_type[]" id="pb_wporg_project_type_ma" value="MA" <?php echo ( !isset( $meta['_pb_wporg_meta_project_type'][0]) || !empty( $project_type ) && in_array( 'MA', $project_type ) ? ' checked' : '' ) ?>>
        <label for="pb_wporg_project_type_ma">Masterarbeit<br /></label>
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

    // study course
    if (array_key_exists('pb_wporg_course', $_POST)) {

        update_post_meta(
            $post_id,
            '_pb_wporg_meta_course',
            //implode(', ', $_POST['pb_wporg_course']) // array to single string
            $_POST['pb_wporg_course']
        );
    }

    // project type
    if (array_key_exists('pb_wporg_project_type', $_POST)) {
        update_post_meta(
            $post_id,
            '_pb_wporg_meta_project_type',
            $_POST['pb_wporg_project_type']
        );
    }

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


//function wp_post_to_html($wp_post_content){
//    $remove_tags = str_replace("<!-- /wp:paragraph -->","", str_replace("<!-- wp:paragraph -->","", $wp_post_content));
//    $replace_line_breaks = str_replace("\n","", str_replace("\n\n", "<br />", $remove_tags));
//    $remove_p = str_replace("</p>","", str_replace("<p>", "", $replace_line_breaks));
//    return $remove_p;
//}

//function extract_keycloak_access_token($response){
//
//    if($response==="TOKEN_REQUEST_ERROR" || !is_array($response) && strpos($response, 'cURL error 7:') !== false)
//        return "TOKEN_REQUEST_ERROR";
//
//    $kc_response = json_decode($response['body']); // JSON to array
//
//    return $kc_response->access_token; // get access-token
//}
//
//function get_keycloak_token_response(){
//
//    $kc_url = get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL))['token_url'];
//    $kc_clientid = get_option('token_api_clientid')['token_clientid'];
//    $kc_username = get_option('token_api_username')['token_username'];
//    $kc_password = get_option('token_api_password')['token_password'];
//
//    // if URL does not start with 'http' return error message
//    if(strpos($kc_url,"http" )===false)
//        return "URL_MALFORMED";
//
//    $request_body = array(
//        'client_id' => $kc_clientid,
//        'username' => $kc_username,
//        'password' => $kc_password,
//        'grant_type' => 'password'
//    );
//
//    $response = wp_remote_post($kc_url, array(
//        'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded'),
//        'body' => http_build_query($request_body),
//        'method' => 'POST'
//    ));
//
//    if ( is_wp_error( $response ) ) {
//        $error_message = $response->get_error_message();
//        return $error_message;
//    }
//    else if($response['response']['code']!==200)
//        return "TOKEN_REQUEST_ERROR";
//
//    return $response;
//}
//
//function keycloak_session_logout($keycloak_token_response) {
//
//    if($keycloak_token_response==="TOKEN_REQUEST_ERROR")
//        return $keycloak_token_response;
//
//    $kc_clientid = get_option('token_api_clientid')['token_clientid'];
//    $refresh_token = json_decode($keycloak_token_response['body'])->refresh_token;  // get refresh-token
//
//    $url = preg_replace("/\btoken$/","logout", get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL))['token_url']);  // replace "token" endpoint with "logout" endpoint from Token-URL
//
//    $data = wp_remote_post($url, array(
//        'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded'),
//        'body' => http_build_query(array(
//            'client_id' => $kc_clientid,
//            'refresh_token' => $refresh_token)),
//        'method' => 'POST'
//    ));
//}

// retrieves pretty study-course-list from PB REST API
function get_pb_courses() {

    // TODO remove hardcoded URL before release:
    $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/studyCourses';
    //$url = 'https://gpdev.archi-lab.io/studyCourses';

    $response = wp_remote_get($url); // get study courses from PB API
    $response_body = json_decode($response['body'], TRUE); // we only need the body of the response
    if(array_key_exists('status', $response_body) && $response_body['status']===404) // if status=404 the api was not found
        return array("ERROR: Could not retrieve API data...");

    $courses = array_column_recursive($response_body,'name'); // go get all study courses
    $degree = array_column_recursive($response_body, 'academicDegree'); // get the academic degree of all couses

    $id = array();

    // dirty implementation to get the ID of each course (which in fact is the self href in the projektbörse api)
    for ($i = 0; $i < count($courses); $i++) {
        //array_push($id, $response_body['_embedded']['studyCourses'][$i]['_links']['self']['href']);
        array_push($id, $response_body['_embedded']['studyCourses'][$i]['id']);
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

//function pb_import_pb_projects() {
//
//    // TODO alter URL
//    if(USE_LOCAL_PB === FALSE) {
//        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present
//    }
//    else {
//        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/posts'; // add json-consuming ressource to url. Strip last slash if present
//    }
//
//    if(get_option('token_enable_checkbox')['token_enable']==="0") { // if true: don't use keycloak-authentication
//
//        //TODO implement non-keycloak
//    }
//    else { // use keycloak-authentication
//
//        $token_response = get_keycloak_token_response();
//        $keycloak_access_token = extract_keycloak_access_token($token_response);
//
//        if ($keycloak_access_token === "TOKEN_REQUEST_ERROR") {
//            return;
//        }
//
//        $request = wp_remote_get($url, array('headers' => array('Content-Type' => 'application/json; charset=utf-8',
//            'Authorization' => 'Bearer ' . $keycloak_access_token)));
//
//        if (is_wp_error($request) || wp_remote_retrieve_response_code( $request ) === 404){
//            echo 'FEHLER: konnte keine Verbindung zur Projektbörse aufbauen.';
//            return;
//        }
//
//        $request_body = wp_remote_retrieve_body($request);
//        $projects = json_decode($request_body, true);
//        $count = 0;
//
//        // build new post:
//        foreach ($projects as $key) {
//
//            if(USE_LOCAL_PB === TRUE) {
//
//                //search for the pb-id in all of the projects meta-keys:
//                $args = array(
//                    'meta_key' => 'pb_project_id',
//                    'meta_value' => $key['id'],
//                    'post_type' => 'projects',
//
//                );
//                $posts_array = get_posts($args); // $posts_array is empty = no post with this id = we can safely import
//
//                if( !empty($posts_array)  ) {
//                    $etag = get_post_meta($posts_array[0]->ID, 'pb_project_etag', true);
//                }
//                else
//                    $etag = "";
//
//                $request_etag = wp_remote_retrieve_headers(wp_remote_get($url."/id/".$key['id'], array('headers' => array(
//                    'Authorization' => 'Bearer ' . $keycloak_access_token))))['etag'];
//
//                // TODO in this case we are blind for changes made inside PB. We sometimes need to import a project even if the ID exist, because someone might have changed something inside PB:
//                if ($key['user_login'] === wp_get_current_user()->user_login && empty($posts_array) ) {  // only import if it's the users post AND if the post (the pb-project-id) is not already there
//                    $imported_project = array(
//                        'post_type' => 'projects',
//                        'post_title' => $key['title'],
//                        'post_content' => $key['content'],
//                        'post_status' => 'publish'
//                    );
//                    $post_id = wp_insert_post($imported_project);
//
//                    if(!is_wp_error($post_id))
//                        $count++;
//
//                    update_post_meta($post_id, 'pb_project_id', $key['id']); // add the pb-project id to the metadata, so we can sync-delete each post
//                    update_post_meta($post_id, 'pb_project_etag', $request_etag);
//                    update_post_meta($post_id, '_pb_wporg_meta_project_status', $key['status']);
//                    update_post_meta($post_id, '_pb_wporg_meta_course', explode(",", $key['course']));
//                    update_post_meta($post_id, '_pb_wporg_meta_project_type', explode(",", $key['type']));
//                    update_post_meta($post_id, '_pb_wporg_meta_checkbox', 1);
//                }
//                else if (!empty($etag) && !empty($request_etag) && $etag!=$request_etag){  // if etags differ, update the post because it's not up-to-date
//                    $imported_project = array(
//                        'ID'           => $posts_array[0]->ID,
//                        'post_type' => 'projects',
//                        'post_title' => $key['title'],
//                        'post_content' => $key['content'],
//                        'post_status' => 'publish'
//                    );
//                    $post_id = wp_update_post($imported_project);
//
//                    if(!is_wp_error($post_id))
//                        $count++;
//
//                    update_post_meta($post_id, 'pb_project_id', $key['id']); // add the pb-project id to the metadata, so we can sync-delete each post
//                    update_post_meta($post_id, 'pb_project_etag', $request_etag);
//                    update_post_meta($post_id, '_pb_wporg_meta_project_status', $key['status']);
//                    update_post_meta($post_id, '_pb_wporg_meta_course', explode(",", $key['course']));
//                    update_post_meta($post_id, '_pb_wporg_meta_project_type', explode(",", $key['type']));
//                    update_post_meta($post_id, '_pb_wporg_meta_checkbox', 1);
//                }
//
//            }
//            else {
//                // TODO add support for official prox
//            }
//        }
//        echo 'Projekte erfolgreich importiert: ' . $count;
//
//        //logout session
//        keycloak_session_logout($token_response);
//    }
//}

// neue Implementierung
function pb_import_pb_projects() {
    ?>
<!--    <head>-->
<!--        <script src="https://login.coalbase.io/auth/js/keycloak.js"></script>-->
<!--        <script>-->
<!--            var keycloak = Keycloak({-->
<!--                url: 'https://login.coalbase.io/auth/',-->
<!--                realm: 'prox',-->
<!--                clientId: 'wordpress-plugin'-->
<!--            });-->
<!--            keycloak.init().success(function(authenticated) {-->
<!--                alert(authenticated ? 'authenticated' : 'not authenticated');-->
<!--            }).error(function() {-->
<!--                alert('failed to initialize');-->
<!--            });-->
<!--        </script>-->
<!--    </head>-->
    <?php
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
        echo "unauthenticated";
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
    echo "Die folgende Liste zeigt alle Ihre in der Projektbörse befindlichen Projekte. Sie können diese normalerweise alle ausgewählt lassen, da nur nicht vorhandene bzw. geänderte Projekte importiert/synchronisiert werden.<br/><br/>";

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

        //if($p['user_login']=== wp_get_current_user()->user_login) {
        // TODO make hardcoded creatorName dynamic
        if($p['creatorName']=== 'Prof. Dozent') {
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

    // TODO alter URL
    if(USE_LOCAL_PB === FALSE) {
        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present
    }
    else {
        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/posts'; // add json-consuming ressource to url. Strip last slash if present
    }
    $count = 0;

//    $token_response = get_keycloak_token_response();
//    $keycloak_access_token = extract_keycloak_access_token($token_response);

        // build new post:
        foreach ($projects as $key) {

            if(USE_LOCAL_PB === TRUE) {

                //search for the pb-id in all of the projects meta-keys:
                $args = array(
                    'meta_key' => 'pb_project_id',
                    'meta_value' => $key['id'],
                    'post_type' => 'projects',

                );
                $posts_array = get_posts($args); // $posts_array is empty = no post with this id = we can safely import

                if( !empty($posts_array)  ) {
                    $etag = get_post_meta($posts_array[0]->ID, 'pb_project_etag', true);
                }
                else
                    $etag = "";

                $request_etag = wp_remote_retrieve_headers(wp_remote_get($url."/id/".$key['id'], array('headers' => array(
                    'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN))))['etag'];

                // TODO in this case we are blind for changes made inside PB. We sometimes need to import a project even if the ID exist, because someone might have changed something inside PB:
                if ($key['user_login'] === wp_get_current_user()->user_login && empty($posts_array) && in_array($key['id'], $projects_to_import) && !empty($projects)) {  // only import if it's the users post AND if the post (the pb-project-id) is not already there
                    $imported_project = array(
                        'post_type' => 'projects',
                        'post_title' => $key['title'],
                        'post_content' => $key['content'],
                        'post_status' => 'publish'
                    );
                    $post_id = wp_insert_post($imported_project);

                    if(!is_wp_error($post_id))
                        $count++;

                    update_post_meta($post_id, 'pb_project_id', $key['id']); // add the pb-project id to the metadata, so we can sync-delete each post
                    update_post_meta($post_id, 'pb_project_etag', $request_etag);
                    update_post_meta($post_id, '_pb_wporg_meta_project_status', $key['status']);
                    update_post_meta($post_id, '_pb_wporg_meta_course', explode(",", $key['course']));
                    update_post_meta($post_id, '_pb_wporg_meta_project_type', explode(",", $key['type']));
                    update_post_meta($post_id, '_pb_wporg_meta_checkbox', 1);
                }
                else if (!empty($etag) && !empty($request_etag) && $etag!=$request_etag && in_array($key['id'], $projects_to_import) && !empty($projects)){  // if etags differ, update the post because it's not up-to-date
                    $imported_project = array(
                        'ID'           => $posts_array[0]->ID,
                        'post_type' => 'projects',
                        'post_title' => $key['title'],
                        'post_content' => $key['content'],
                        'post_status' => 'publish'
                    );
                    $post_id = wp_update_post($imported_project);

                    if(!is_wp_error($post_id))
                        $count++;

                    update_post_meta($post_id, 'pb_project_id', $key['id']); // add the pb-project id to the metadata, so we can sync-delete each post
                    update_post_meta($post_id, 'pb_project_etag', $request_etag);
                    update_post_meta($post_id, '_pb_wporg_meta_project_status', $key['status']);
                    update_post_meta($post_id, '_pb_wporg_meta_course', explode(",", $key['course']));
                    update_post_meta($post_id, '_pb_wporg_meta_project_type', explode(",", $key['type']));
                    update_post_meta($post_id, '_pb_wporg_meta_checkbox', 1);
                }

            }
            else {  // USE PROX!!!!!!!!!!!!!
                //search for the pb-id in all of the projects meta-keys:
                $args = array(
                    'meta_key' => 'pb_project_id',
                    'meta_value' => $key['id'],
                    'post_type' => 'projects',

                );
                $posts_array = get_posts($args); // $posts_array is empty = no post with this id = we can safely import

                // TODO change etag implementation to last modified:
                if( !empty($posts_array)  ) {
                    //update_post_meta( $post->ID, 'pb_project_modified', $modified);
                    $modified = get_post_meta($posts_array[0]->ID, 'pb_project_modified', true);
                }
                else
                    $modified = "";

                $server_modified = json_decode(wp_remote_retrieve_body(wp_remote_get($url."/".$key['id'], array('headers' => array(
                    'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN)))), true)['modified'];

                my_log_file($modified, "gespeichert: ");
                my_log_file($server_modified, "vom server: ");
                my_log_file($url."/projects/".$key['id']);


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
                // TODO etag implementation to last modified
                else if (!empty($modified) && !empty($server_modified) && $modified!=$server_modified && in_array($key['id'], $projects_to_import) && !empty($projects)){  // if modified differ, update the post because it's not up-to-date
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
        }
        echo 'Projekte importiert: ' . $count;
        if (!empty($projects) && $count === 0) {
            echo "<br /><br />Alle Projekte in der WordPress-Datenbank sind auf dem neuesten Stand.";
        }
        echo "<br /><br /><button type='button' onclick='window.close()'>Schließen</button> ";

//        //logout session
//        keycloak_session_logout($token_response);

}

function pb_get_projects() {
    // TODO alter URL
    if(USE_LOCAL_PB === FALSE) {
        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present
    }
    else {
        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/posts'; // add json-consuming ressource to url. Strip last slash if present
    }

//    $token_response = get_keycloak_token_response();
//    $keycloak_access_token = extract_keycloak_access_token($token_response);
//
//    if ($keycloak_access_token === "TOKEN_REQUEST_ERROR") {
//        return;
//    }

    $request = wp_remote_get($url, array('headers' => array('Content-Type' => 'application/json; charset=utf-8',
        'Authorization' => 'Bearer ' . PB_ACCESS_TOKEN)));

    if (is_wp_error($request) || wp_remote_retrieve_response_code( $request ) === 404){
        echo 'FEHLER: konnte keine Verbindung zur Projektbörse aufbauen.';
        return;
    }

    $request_body = wp_remote_retrieve_body($request);
//    //logout session
//    keycloak_session_logout($token_response);
    return  json_decode($request_body, true)['_embedded']['projects'];
}



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