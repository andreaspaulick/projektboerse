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
        <form action="<?php echo admin_url('admin-post.php');?>" method="post" onsubmit="target_popup2(this)">
            <input type="hidden" name="action" value="pb_import_pb_projects">
            <?php submit_button( 'Synchronisiere eigene Projekte aus Prox', 'secondary', "" ,false ); ?>
        </form>
        <script>
            function target_popup2(form) {
                window.open('', 'formpopup', 'width=520,height=600,resizeable,scrollbars');
                form.target = 'formpopup';
            }
        </script>

<!--        <br /><form action="--><?php //echo admin_url('admin-post.php');?><!--" method="post">-->
<!--            <input type="hidden" name="action" value="pb_auth_code_grant">-->
<!--            --><?php //submit_button( 'Authentifizierung', 'secondary', "" ,false ); ?>
<!--        </form>-->

        <br /><form action="https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/auth" method="post" onsubmit="get_token446t4(this)">
            <input type="hidden" name="client_id" value="wordpress-plugin" />
            <input type="hidden" name="redirect_uri" value="<?php echo plugins_url('/projektboerse/redirect.php');?>" />
            <input type="hidden" name="response_type" value="code" />
            <input type="hidden" name="scope" value="openid" />
            <input type="hidden" name="state" value="E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT">
            <?php submit_button( 'Authentifizierung', 'secondary', "" ,false ); ?>
        </form>
        <script>
            function get_token446t4(form) {
                window.open('', 'formpopup', 'width=600,height=600,resizeable,scrollbars');
                form.target = 'formpopup';
            }
        </script>

        <form action="options.php" method="post">
            <?php
            settings_fields( 'pb_settings_input' );
            do_settings_sections( 'pb_settings_input' );
            //submit_button( 'Speichern' );

            submit_button( 'Einstellungen Speichern' );
            ?>
        </form>
    </div>
    <?php
}

//add_action( 'admin_post_tokencheck534547', 'tokencheck534547' );
add_action( 'admin_post_pb_import_pb_projects', 'pb_import_pb_projects' );
//add_action( 'admin_post_pb_auth_code_grant', 'pb_auth_code_grant' );

//function pb_auth_code_grant () {
//    //$url = "https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/auth";
//
//    wp_redirect("https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/auth?client_id=wordpress-plugin&redirect_uri=".plugins_url('/projektboerse/redirect.php')."&response_type=code&scope=openid&state=E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT");

//    $response = wp_remote_request($url, array(
//        'headers' => array( 'Content-Type'      => 'application/x-www-form-urlencoded; charset=utf-8',
//                            'client_id'         => 'wordpress-plugin',
//                            'redirect_uri'      => admin_url('admin-post.php'),
//                            'response_type'     => 'code',
//                            'scope'             => 'openid',
//                            'state'             => 'E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT'
//            ),
//        'method' => 'POST'
//    ));
//    $request_body = array(
//        'client_id'         => 'wordpress-plugin',
//        'redirect_uri'      => admin_url('options-general.php?page=pboerse'),
//        'response_type'     => 'code',
//        'scope'             => 'openid',
//        'state'             => 'E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT'
//    );
//
//    $response = wp_remote_post($url, array(
//        'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded'),
//        'body' => http_build_query($request_body),
//        'method' => 'POST'
//    ));
//    my_log_file($response);

//    exit;
//}
//add_action( 'admin_post_pb_auth_code_grant', 'pb_auth_code_grant' );

function pb_options_page()
{
    add_submenu_page(
        'options-general.php',
        'TH Köln Prox Einstellungen',
        'THK Prox Client',
        'manage_options',
        'pboerse',
        'pb_options_page_html'
    );
}
add_action('admin_menu', 'pb_options_page');

add_action('admin_init', 'plugin_admin_init');
function plugin_admin_init()
{

    add_settings_section('pb_misc_settings', 'Allgemeine Einstellungen', 'pb_misc_settings_text', 'pb_settings_input');

    // delete wp and pb posts at once
    register_setting('pb_settings_input', 'pb_sync_delete');
    add_settings_field('pb_sync_delete_field', 'Synchrones Löschen', 'pb_sync_delete3478', 'pb_settings_input', 'pb_misc_settings');

    // add date and time to project-post
    register_setting('pb_settings_input', 'pb_add_datetime');
    add_settings_field('pb_add_datetime_field', 'Zeistempel hinzufügen', 'pb_add_datetime3478', 'pb_settings_input', 'pb_misc_settings');

    // on import, delete projects in wordpress which are not present in prox
    register_setting('pb_settings_input', 'pb_smart_delete_on_import');
    add_settings_field('pb_smart_delete_on_import_field', 'Lösche bei Synchronisierung', 'pb_add_smart_delete_on_import', 'pb_settings_input', 'pb_misc_settings');

    // enter standard-supervisor-name
    register_setting('pb_settings_input', 'pb_add_supervisor');
    add_settings_field('pb_add_supervisor_field', 'Standard Betreuer Name', 'pb_add_supervisor3478', 'pb_settings_input', 'pb_misc_settings');

    // Path to Projektbörse API
    register_setting('pb_settings_input', 'pb_api_url');
    add_settings_section('plugin_main', 'Pfad zur Projektbörse API', 'plugin_section_text', 'pb_settings_input');
    add_settings_field('pb_url', 'URL:', 'pb_api_url2432425', 'pb_settings_input', 'plugin_main');

    add_settings_section('plugin_main_token', 'Keycloak Access-Token Anforderung', 'token_section_text', 'pb_settings_input');

    // Keycloak Access-Token-API URL
    register_setting('pb_settings_input', 'token_api_url');
    add_settings_field('token_url', 'Keycloak Token API URL:', 'token_setting_url', 'pb_settings_input', 'plugin_main_token');
}

function plugin_section_text() {
    echo '<p>Geben Sie hier die API-URL zu Prox (Projektbörse der TH Köln) an.<br>Über diese API werden mittels diesem Client Projekte in der Projektbörse verwaltet und ein lokaler Cache in WordPress angelegt</p>';
}

function token_section_text() {
    echo '<p>Prox ist durch OpenID Connect vor unberechtigten Zugriffen geschützt. Um den WordPress Client zu authentifizieren, so muss der Token-Endpunkt von Keycloak hier angegeben werden</p>';
}

function pb_misc_settings_text(){
    echo '<p>Allgemeine Einstellungen</p>';
}

function pb_add_smart_delete_on_import() {
    $options = get_option('pb_smart_delete_on_import', array('pb_smart_delete_on_import_field' => '1'));
    $checkbox_value = (isset( $options['pb_smart_delete_on_import_field'] )  && '1' === $options['pb_smart_delete_on_import_field'][0] ) ? 1 : 0;

    ?>
    <input type="checkbox" id="pb_smart_delete_on_import" name='pb_smart_delete_on_import[pb_smart_delete_on_import_field]' value="1" <?php checked( $checkbox_value, 1); ?> >
    <label for="pb_smart_delete_on_import"><i>Entferne bei der Synchronisierung der eigenen Projekte aus Prox all die WordPress Projekte, die in Prox nicht existieren</i></label>
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

function pb_add_datetime3478() {
    $options = get_option('pb_add_datetime', array('pb_add_datetime_field' => '1'));
    $checkbox_value = (isset( $options['pb_add_datetime_field'] )  && '1' === $options['pb_add_datetime_field'][0] ) ? 1 : 0;

    ?>
    <input type="checkbox" id="pb_add_datetime" name='pb_add_datetime[pb_add_datetime_field]' value="1" <?php checked( $checkbox_value, 1); ?> >
    <label for="pb_add_datetime"><i>Am Ende von jedem Projekt, Datum und Uhrzeit der Projekterstellung anfügen</i></label>
    <?php
}

function pb_api_url2432425() {
    //delete_option('pb_api_url');
    $options = get_option('pb_api_url', array('pb_url' => DEFAULT_API_URL));
    echo "<input id='pb_url' name='pb_api_url[pb_url]' size='80' type='text' value='{$options['pb_url']}' />";

}

function pb_add_supervisor3478() {
    //delete_option('pb_add_supervisor');
    $options = get_option('pb_add_supervisor', array('pb_add_supervisor_field' => 'Benutzer'));
    echo "<input id='pb_add_supervisor_field' name='pb_add_supervisor[pb_add_supervisor_field]' size='80' type='text' value='{$options['pb_add_supervisor_field']}' />";
}

function token_setting_url() {
    //delete_option('token_api_url');
    $options = get_option('token_api_url', array('token_url' => DEFAULT_KEYCLOAK_API_URL));
    echo "<input id='token_url'       name='token_api_url[token_url]'   size='80' type='text' value='{$options['token_url']}' />";
    echo "<label><br><i>Geben Sie hier den Pfad zum Prox-Zugangstoken Endpunkt an. Beachten Sie dabei, dass Sie den korrekten Realm wählen.<br>Das übliche Format sieht wie folgt aus:</i><br><code>https://<i>{url:port}</i>/auth/realms/<i>{realm name}</i>/protocol/openid-connect/token</code></label>";
}