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
define( 'DEFAULT_API_URL' , 'http://localhost:8045/posts/jsonadd' );
define( 'DEFAULT_KEYCLOAK_API_URL' , 'http://localhost:8180/auth/realms/pboerse/protocol/openid-connect/token' );
$GLOBALS['disabled'] = "";


    // disable gutenberg for posts
        add_filter('use_block_editor_for_post', '__return_false', 10);

    // disable gutenberg for post types
        add_filter('use_block_editor_for_post_type', '__return_false', 10);


// ---------------------- TinyMCE Custom Button Section --------------------------------


add_action( 'init', 'pb_buttons' );
function pb_buttons() {
    add_filter( "mce_external_plugins", "pboerse_add_button" );
    add_filter( 'mce_buttons', 'pboerse_register_button' );
}
function pboerse_add_button( $plugin_array ) {
    $plugin_array['pboerse'] = plugin_dir_url(__FILE__) . '/pb_button.js';
    return $plugin_array;
}
function pboerse_register_button( $buttons ) {
    array_push( $buttons, 'pb_button1' ); //
    return $buttons;
}


// ------------------------ Plugin functionality ----------------------------

/**
 * post Variable Reference: https://codex.wordpress.org/Function_Reference/$post
 */
function post_published_api_call( $ID, $post) {
    if(!isset($_POST['publish-to-somewhere']) || $_POST['publish-to-somewhere'] == '0') return;

        $url = get_option('api_url', array('plugin_text_string' => DEFAULT_API_URL))['plugin_text_string'];
        $title = $post->post_title;
        $content = wp_post_to_html($post->post_content);

        $post_data = array(
            'status' => 'publish',
            'title' => $title,
            'content' => $content
        );

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

function wp_post_to_html($wp_post_content){
    $remove_tags = str_replace("<!-- /wp:paragraph -->","", str_replace("<!-- wp:paragraph -->","", $wp_post_content));
    $replace_line_breaks = str_replace("\n","", str_replace("\n\n", "<br />", $remove_tags));
    $remove_p = str_replace("</p>","", str_replace("<p>", "", $replace_line_breaks));
    return $remove_p;
}

// hidden input field to get the button state
add_action( 'post_submitbox_misc_actions', 'wpse325418_my_custom_hidden_field' );
function wpse325418_my_custom_hidden_field() {
    echo "<input id='i-am-hidden' name='publish-to-somewhere' type='hidden' value='0' />";
}

function extract_keycloak_access_token($response){

    if($response==="TOKEN_REQUEST_ERROR")
        return $response;

    $kc_response = json_decode($response['body']);

    return $kc_response->access_token;
}

function get_keycloak_token_response(){

    $kc_url = get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL))['token_url'];
    $kc_clientid = get_option('token_api_clientid')['token_clientid'];
    $kc_username = get_option('token_api_username')['token_username'];
    $kc_password = get_option('token_api_password')['token_password'];

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

    if($response['response']['code']!==200)
        return "TOKEN_REQUEST_ERROR";

    return $response;
}

function keycloak_session_logout($keycloak_token_response) {

    if($keycloak_token_response==="TOKEN_REQUEST_ERROR")
        return $keycloak_token_response;

    $kc_clientid = get_option('token_api_clientid')['token_clientid'];
    $refresh_token = json_decode($keycloak_token_response['body'])->refresh_token;

    $url = preg_replace("/\btoken$/","logout", get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL))['token_url']);

    $data = wp_remote_post($url, array(
        'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded'),
        'body' => http_build_query(array(
            'client_id' => $kc_clientid,
            'refresh_token' => $refresh_token)),
        'method' => 'POST'
    ));
}


//  -------------------------  Options Page & Settings --------------------------------------

function pb_options_page_html($post_data)
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div>
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // output security fields for the registered setting "pboerse"
            settings_fields( 'pb_api' );
            // output setting sections and their fields
            // (sections are registered for "pboerse", each field is registered to a specific section)
            do_settings_sections( 'pb_api' );
            ?>
<!--            <br />-->
<!--            <br />-->
            <?php

            settings_fields( 'token_api' );
            do_settings_sections( 'token_api' );
            //submit_button( 'Speichern' );

            submit_button( 'Einstellungen Speichern' );
            //my_log_file(get_option('token_enable_checkbox')['token_enable'] , 'aktiv?');
            ?>
        </form>
        <form action="<?php echo admin_url( 'admin-post.php' ); ?>" onsubmit="target_popup(this)">
            <input type="hidden" name="action" value="tokencheck534547">
            <?php submit_button( 'Teste Tokenanforderung', 'secondary', "" ,false ); ?>
        </form>
        <script>
            function target_popup(form) {
                window.open('', 'formpopup', 'width=400,height=400,resizeable,scrollbars');
                form.target = 'formpopup';
            }
        </script>
    </div>
    <?php
}

add_action( 'admin_post_tokencheck534547', 'tokencheck534547_test' );

function tokencheck534547_test() {

    $keycloak_token_response = get_keycloak_token_response();

    if($keycloak_token_response==="TOKEN_REQUEST_ERROR")
        echo    "TOKEN_REQUEST_ERROR<br><br>
                Es konnte kein Token angefordert werden.<br><br>
                Stellen Sie sicher, dass die Einstellungen vorher gespeichert wurden und überprüfen Sie die Eingaben!";
    else if ($keycloak_token_response==="URL_MALFORMED")
        echo "MALFORMED_TOKEN_URL";
    else {
        echo "ERFOLG!<br><br>Keycloak Access Token erfolgreich erhalten.";

        //logout session
        keycloak_session_logout($keycloak_token_response);
    }
}

function pb_options_page()
{
    add_submenu_page(
        'options-general.php',
        'TH Köln Projektbörse Einstellungen',
        'THK Projektbörse',
        'manage_options',
        'pboerse',
        'pb_options_page_html'
    );
}
add_action('admin_menu', 'pb_options_page');

add_action('admin_init', 'plugin_admin_init');
function plugin_admin_init()
{
    // Path to Projektbörse API
    register_setting('pb_api', 'api_url');
    add_settings_section('plugin_main', 'Pfad zur API', 'plugin_section_text', 'pb_api');
    add_settings_field('plugin_text_string', 'URL:', 'plugin_setting_string', 'pb_api', 'plugin_main');

    // Enable KeyCloac token request
    register_setting('token_api', 'token_enable_checkbox');
    add_settings_section('plugin_main_token', 'Keycloak Access-Token Anforderung', 'token_section_text', 'token_api');
    add_settings_field('token_enable', 'Aktiviere Tokenanforderung?', 'token_enable', 'token_api', 'plugin_main_token');

    // Keycloak Access-Token-API URL
    register_setting('token_api', 'token_api_url');
    //add_settings_section('plugin_main_token', 'Keycloak Access-Token Anforderung', 'token_section_text', 'token_api');
    add_settings_field('token_url', 'Keycloak Token API URL:', 'token_setting_url', 'token_api', 'plugin_main_token');

    // Keycloak client_id
    register_setting('token_api', 'token_api_clientid');
    add_settings_field('token_clientid', 'Client-ID:', 'token_setting_clientid', 'token_api', 'plugin_main_token');

    // Keycloak username
    register_setting('token_api', 'token_api_username');
    add_settings_field('token_username', 'Benutzername:', 'token_setting_username', 'token_api', 'plugin_main_token');

    // Keycloak password
    register_setting('token_api', 'token_api_password');
    add_settings_field('token_password', 'Passwort:', 'token_setting_password', 'token_api', 'plugin_main_token');
}

function plugin_section_text() {
echo '<p>Geben Sie die URL zu der Projektbörse-API der TH Köln an.<br>Wird ein neuer Wordpress-Beitrag erstellt, so wird dieser direkt an die Projektbörse API gesendet, die ein JSON über eine REST Schnittstelle konsumiert</p>';
}

function token_section_text() {
    echo '<p>Ist der Projektbörse-Server durch Keycloak geschützt, so können Sie hier die Zugangsdaten des Keycloak Realms eingeben um in der Lage zu sein, Beiträge im geschützten Bereich der Projektbörse verfassen zu können</p>';
}

function plugin_setting_string() {
    //delete_option('api_url');
    $options = get_option('api_url', array('plugin_text_string' => DEFAULT_API_URL));
    echo "<input id='plugin_text_string' name='api_url[plugin_text_string]' size='80' type='text' value='{$options['plugin_text_string']}' />";

}

function token_enable () {
    //delete_option('token_enable_checkbox');
    $options = get_option('token_enable_checkbox', array('token_enable' => '0'));

    ?>
        <input type="hidden" name="token_enable_checkbox[token_enable]" value="0" >
        <input type="checkbox" id='token_enable' name='token_enable_checkbox[token_enable]' value="1" <?php checked( $options['token_enable'], 1); ?> >
        <label for="token_enable"><i>Fordere Zugangstoken mittels der unten gespeicherten Zugangsdaten an. Das Token wird zwingend benötigt um auf dem geschützten Realm an die REST-API senden zu können</i></label>
    <?php
    //$GLOBALS['readonly'] = get_option('token_enable_checkbox')['token_enable']  < 1 ? "readonly='readonly'" : "";
}

function token_setting_url() {
    //delete_option('token_api_url');
    $options = get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL));
    echo "<input id='token_url'       name='token_api_url[token_url]'   size='80' type='text' value='{$options['token_url']}' />";
    echo "<label><br><i>Geben Sie hier den Pfad zur Keycloak Zugangstoken API an. Beachten Sie dabei, dass Sie den korrekten Realm wählen. Dorthin wird eine Anfrage mit den unten gespeicherten Zugangsdaten gesendet. Sind die Daten korrekt, wird Keycloak ein Access-Token generieren und als Antwort senden. Dieses Token wird von diesem Plugin benutzt, um in dem geschützten Bereich der Projektbörse ein Post speichern zu können. <br>Das übliche Format sieht wie folgt aus:</i><br><code>https://<i>{url:port}</i>/auth/realms/<i>{realm name}</i>/protocol/openid-connect/token</code></label>";
}

function token_setting_clientid() {
    //delete_option('token_api_clientid');
    $options = get_option('token_api_clientid', array('token_clientid' => ''));
    echo "<input id='token_clientid'  name='token_api_clientid[token_clientid]'   size='20' type='text' value='{$options['token_clientid']}' />";
    echo "<label><br><i>Geben Sie hier Ihre Keycloak Client-ID für den Realm an</i></label>";
}

function token_setting_username() {
    //delete_option('token_api_username');
    $options = get_option('token_api_username', array('token_username' => ''));
    echo "<input id='token_username'  name='token_api_username[token_username]'   size='20' type='text' value='{$options['token_username']}' />";
    echo "<label><br><i>Geben Sie hier Ihren Keycloak Benutzernamen für den Realm  ein</i></label>";
}

function token_setting_password() {
    //delete_option('token_api_password');
    $options = get_option('token_api_password', array('token_password' => ''));
    echo "<input id='token_password'  name='token_api_password[token_password]'   size='20' type='password' value='{$options['token_password']}' >";
    echo "<label><br><i>Geben Sie hier das Passwort zu dem oben genannten Benutzernamen für den Realm  ein</i></label>";
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