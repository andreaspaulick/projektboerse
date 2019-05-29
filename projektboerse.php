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
define( 'DEFAULT_KEYCLOAK_API_URL' , 'https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/token' ); // default link to the keycloak token endpoint

include 'redirect.php';
include 'includes/pb_options.php';
include 'includes/pb_auth.php';
include 'includes/pb_sync.php';
include 'includes/pb_helpers.php';
include 'includes/pb_metabox.php';

/**
 * Main Function to do something (in this case: send projects via REST request) in case a project is published
 * post Variable Reference: https://codex.wordpress.org/Function_Reference/$post
 */
function post_published_api_call( $ID, $post) {

    if( get_post_meta($post->ID, '_pb_wporg_meta_checkbox', true) !== "1" ) return; // return (do nothing) if checkbox "also send to pb" is not checked

        $url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/') . '/projects'; // add json-consuming ressource to url. Strip last slash if present

        $title = $post->post_title;
        if (empty($title)) {
            $title = "[kein titel]";
        }
        $content = sanitize_textarea_field(wp_strip_all_tags($post->post_content)); // at the moment all tags are stripped (images won't be transferred)

        // get the supervisor name from meta key if it exists. optherwise get the default supervisor name from settings page
        if(metadata_exists( 'post', $post->ID, '_pb_wporg_meta_project_status' )){
            $sup_name = get_post_meta($post->ID, '_pb_wporg_meta_supervisor', true);
        }
        else
            $sup_name = get_option('pb_add_supervisor')['pb_add_supervisor_field'];

        // includes for Testserver Projektbörse --> https://gpdev.archi-lab.io/projects
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

        if( metadata_exists( 'post', $post->ID, 'pb_project_id' )){ // means the project is edited

            // get the project-id of the pb-post (if set)
            $pb_project_id = get_post_meta($post->ID, 'pb_project_id', true);

            $url2 = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/projects/'.$pb_project_id ;

            $data = wp_remote_request($url2, array(
                'headers' => array( 'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token']),
                'body'          => json_encode( array(
                    'id'            => $pb_project_id,
                    'creatorID' => '1b29e41e-aab2-4757-8ea2-7e2daca207e6',
                    'creatorName' => $GLOBALS['prox_username'],
                    'description' => $content,
                    'name' => $title,
                    'status' => get_post_meta($post->ID, '_pb_wporg_meta_project_status', true),
                    'supervisorName' => $sup_name,
                )),
                'method' => 'PUT'
            ));

            // update module list of current project in prox
            if(metadata_exists('post', $post->ID, '_pb_wporg_meta_studyModules')){
                $modules_url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/projectModules/' ;

                $text_string = "";

                foreach (get_post_meta($post->ID, '_pb_wporg_meta_studyModules', true) as $value) {
                    $text_string = $text_string.$modules_url.$value."\n";
                }
                $putModulesData = wp_remote_request($url2."/modules/", array(
                    'headers' => array( 'Content-Type' => 'text/uri-list',
                        'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token']),
                    'body'          => $text_string,
                    'method' => 'PUT'
                ));
            }

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

            // save module list of current project in prox
            if(metadata_exists('post', $post->ID, '_pb_wporg_meta_studyModules')){
                $modules_url = rtrim(get_option('pb_api_url', array('pb_api_url' => DEFAULT_API_URL))['pb_url'], '/').'/projectModules/' ;

                $text_string = "";

                foreach (get_post_meta($post->ID, '_pb_wporg_meta_studyModules', true) as $value) {
                    $text_string = $text_string.$modules_url.$value."\n";
                }
                $putModulesData = wp_remote_request($url."/".get_post_meta($post->ID, 'pb_project_id', true)."/modules/", array(
                        'headers' => array( 'Content-Type' => 'text/uri-list',
                            'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token']),
                        'body'          => $text_string,
                        'method' => 'PUT'
                    ));
            }
        }
}
add_action( 'publish_projects', 'post_published_api_call', 10, 2);


/**
 * If the user checked the corresponding setting, not only WordPress projects are deleted,
 * but also the corresponding entry in Prox via REST API
 *
 * @param $postid - the ID of the currently viewed project-post
 */
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

/**
 * Deactivate publish button in case the user is not authenticated
 */
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

/**
 * Init some important globals for includes exchange:
 * - access token
 * - redirect uri
 * - keycloak username
 * - token uri
 */
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

/**
 * Define custom post type "projects"
 */
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


/**
 * Add a [sc_pb_meta] shortcode at the end of every project-type-post
 *
 * @param $content - the content of a project-post
 * @return string - the new content with the added shortcode (and the post creation date+time if set)
 */
function modify_content($content) {
    global $post;
    if($post->post_type === 'projects' && !is_archive())
        if(isset(get_option('pb_add_datetime')['pb_add_datetime_field'])) {
            return $content . "[sc_pb_meta]"."[sc_pb_meta_dateandtime]" ;
        }
        else
            return $content . "[sc_pb_meta]";
    else
        return $content;
}
add_filter('the_content', 'modify_content');

/**
 * defines what the shortcode should display
 *
 * @return string - the HTML string which visualizes the metadata beneath every project-post
 */
function sc_pb_meta_function(){
    global $post;

    $project_status = get_post_meta($post->ID, '_pb_wporg_meta_project_status', true);

    if ($project_status === 'VERFÜGBAR') $project_status = 'verfügbar';
    elseif ($project_status === 'LAUFEND') $project_status = 'laufend';
    elseif ($project_status === 'ABGESCHLOSSEN') $project_status = 'abgeschlossen';

    // TODO show supervisor and project modules
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