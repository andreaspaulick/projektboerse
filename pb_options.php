<?php
$GLOBALS['disabled'] = "";

function pb_options_page_html($post_data)
{
    // check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); // get Page Title ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'pb_settings_input' );
            do_settings_sections( 'pb_settings_input' );
            //submit_button( 'Speichern' );

            submit_button( 'Einstellungen Speichern' );
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
    else if (!is_array($keycloak_token_response) && strpos($keycloak_token_response, 'cURL error 7:') !== false)
        echo "ERROR: Keycloak Server is unreachable";
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
    // add [<project-type>] before title
    register_setting('pb_settings_input', 'pb_add_type_tag');
    add_settings_section('pb_misc_settings', 'Allgemeine Einstellungen', 'pb_misc_settings_text', 'pb_settings_input');
    add_settings_field('pb_add_type_tag_field', 'Projekttyp-Tag', 'pb_add_type_tag3478', 'pb_settings_input', 'pb_misc_settings');

    // default value for sending projects to pb
    register_setting('pb_settings_input', 'pb_send_to_pb');
    add_settings_field('pb_send_to_pb_field', 'Sync-Checkbox', 'pb_send_to_pb3478', 'pb_settings_input', 'pb_misc_settings');

    // delete wp and pb posts at once
    register_setting('pb_settings_input', 'pb_sync_delete');
    add_settings_field('pb_sync_delete_field', 'Synchrones Löschen', 'pb_sync_delete3478', 'pb_settings_input', 'pb_misc_settings');


    // Path to Projektbörse API
    register_setting('pb_settings_input', 'pb_api_url');
    add_settings_section('plugin_main', 'Pfad zur Projektbörse API', 'plugin_section_text', 'pb_settings_input');
    add_settings_field('pb_url', 'URL:', 'pb_api_url2432425', 'pb_settings_input', 'plugin_main');

    // Enable KeyCloac token request
    register_setting('pb_settings_input', 'token_enable_checkbox');
    add_settings_section('plugin_main_token', 'Keycloak Access-Token Anforderung', 'token_section_text', 'pb_settings_input');
    add_settings_field('token_enable', 'Aktiviere Tokenanforderung?', 'token_enable', 'pb_settings_input', 'plugin_main_token');

    // Keycloak Access-Token-API URL
    register_setting('pb_settings_input', 'token_api_url');
    add_settings_field('token_url', 'Keycloak Token API URL:', 'token_setting_url', 'pb_settings_input', 'plugin_main_token');

    // Keycloak client_id
    register_setting('pb_settings_input', 'token_api_clientid');
    add_settings_field('token_clientid', 'Client-ID:', 'token_setting_clientid', 'pb_settings_input', 'plugin_main_token');

    // Keycloak username
    register_setting('pb_settings_input', 'token_api_username');
    add_settings_field('token_username', 'Benutzername:', 'token_setting_username', 'pb_settings_input', 'plugin_main_token');

    // Keycloak password
    register_setting('pb_settings_input', 'token_api_password');
    add_settings_field('token_password', 'Passwort:', 'token_setting_password', 'pb_settings_input', 'plugin_main_token');
}

function plugin_section_text() {
    echo '<p>Geben Sie die URL zu der Projektbörse-API der TH Köln an.<br>Wird ein neuer Wordpress-Beitrag erstellt, so wird dieser direkt an die Projektbörse API gesendet, die ein JSON über eine REST Schnittstelle konsumiert</p>';
}

function token_section_text() {
    echo '<p>Ist der Projektbörse-Server durch Keycloak geschützt, so können Sie hier die Zugangsdaten des Keycloak Realms eingeben um in der Lage zu sein, Beiträge im geschützten Bereich der Projektbörse verfassen zu können</p>';
}

function pb_misc_settings_text(){
    echo '<p>Allgemeine Einstellungen</p>';
}

function pb_add_type_tag3478(){
    $options = get_option('pb_add_type_tag', array('pb_add_type_tag_field' => '1'));
    $checkbox_value = (isset( $options['pb_add_type_tag_field'] )  && '1' === $options['pb_add_type_tag_field'][0] ) ? 1 : 0;

    ?>
    <input type="checkbox" id="pb_add_type_tag" name='pb_add_type_tag[pb_add_type_tag_field]' value="1" <?php checked( $checkbox_value, 1); ?> >
    <label for="pb_add_type_tag"><i>Stelle einen Tag vom Typ [PP/BA/MA] dem Projekttitel voran, der einen Hinweis darauf bietet, für welche Art von wissenschaftlichen Arbeiten das Projekt geeignet ist</i></label>
    <?php
}

function pb_send_to_pb3478(){
    $options = get_option('pb_send_to_pb', array('pb_send_to_pb_field' => '1'));
    $checkbox_value = (isset( $options['pb_send_to_pb_field'] )  && '1' === $options['pb_send_to_pb_field'][0] ) ? 1 : 0;

    ?>
    <input type="checkbox" id="pb_send_to_pb" name='pb_send_to_pb[pb_send_to_pb_field]' value="1" <?php checked( $checkbox_value, 1); ?> >
    <label for="pb_send_to_pb"><i>Aktiviere beim Erstellen eines neuen Projekts die Checkbox "mit Projektbörse synchronisieren" standardmäßig</i></label>
    <?php
}

function pb_sync_delete3478(){
    $options = get_option('pb_sync_delete', array('pb_sync_delete_field' => '1'));
    $checkbox_value = (isset( $options['pb_sync_delete_field'] )  && '1' === $options['pb_sync_delete_field'][0] ) ? 1 : 0;

    ?>
    <input type="checkbox" id="pb_sync_delete" name='pb_sync_delete[pb_sync_delete_field]' value="1" <?php checked( $checkbox_value, 1); ?> >
    <label for="pb_sync_delete"><i>Entferne beim Löschen von Projekten in WordPress auch den korrespondierenden Eintrag in der Projektbörse</i></label>
    <?php
}

function pb_api_url2432425() {
    //delete_option('pb_api_url');
    $options = get_option('pb_api_url', array('pb_url' => DEFAULT_API_URL));
    echo "<input id='pb_url' name='pb_api_url[pb_url]' size='80' type='text' value='{$options['pb_url']}' />";

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