<?php
/**
 * Add custom metabox paragraph for THK projects on project pages
 */
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
/**
 * HTML input fields for post metadata. Loads the last saved includes!
 */
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
        <input type="text" name="pb_wporg_project_supervisor" id="pb_wporg_project_supervisor" value="<?php echo esc_attr($supervisor)?>" >
    </p>
    <!--    ------------- study courses and attached modules -------->
    <p>
        <label><strong>Projekt verfügbar für:</strong><br /></label>
        <?php
        if(!is_array($study_courses)) {
            echo "[ " . $study_courses . " ]";
            return;
        }

        foreach ($study_courses as $value) {
            $modules = pb_get_studyCoursesModules($value['_links']['modules']['href']);

            echo "<i><p><b>".$value['name']." (".$value['academicDegree'].")</b></p>";
            foreach ($modules as $key2 => $value2) {


                if(metadata_exists('post', $post->ID, '_pb_wporg_meta_studyModules')){
                    echo "<input type='checkbox' name='studyModules[]' id='{$value2['id']}' value='{$value2['id']}' ".( in_array($value2['id'], get_post_meta($post->ID, '_pb_wporg_meta_studyModules', true)) ? 'checked' : '')." > ";
                }
                else {
                    echo "<input type='checkbox' name='studyModules[]' id='{$value2['id']}' value='{$value2['id']}' > ";
                }
                echo "<label for='".$value2['id']."'>".$value2['name']."&nbsp;&nbsp;&nbsp;&nbsp;</label>";
            }
            echo "</i>";

        }
        ?>
    </p>

    <?php
}

// TODO edit to fit PROX
/**
 * Save the pb-metabox includes into unique meta keys
 */
function pb_wporg_save_postdata($post_id)
{

    //study course modules list
    if (array_key_exists('studyModules', $_POST)) {
        update_post_meta(
            $post_id,
            '_pb_wporg_meta_studyModules',
            $_POST['studyModules']
        );
    }



    // checkbox
    $checkbox_value = ( isset( $_POST['checkbox_value'] ) && '1' === $_POST['checkbox_value'] ) ? 1 : 0; // Input var okay.
    update_post_meta( $post_id, '_pb_wporg_meta_checkbox', esc_attr( $checkbox_value ) );

    // supervisor
    if (array_key_exists('pb_wporg_project_supervisor', $_POST)) {

        update_post_meta(
            $post_id,
            '_pb_wporg_meta_supervisor',
            sanitize_text_field($_POST['pb_wporg_project_supervisor'])
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