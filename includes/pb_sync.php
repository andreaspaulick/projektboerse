<?php
/**
 * HTML Import Page where the user can choose from a list of projects, what to synchronize
 */
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
            echo "<td><label for=" . $p['id'] . ">".$p['name']."</label></td></tr>";
        }
    }
    echo "</tbody>";
    echo "</table>";
    echo "</form>";



}
add_action( 'admin_post_pb_import_pb_projects_step2', 'pb_import_pb_projects_step2' );

/**
 * Actual import of the prox projects
 */
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
        if ($key['creatorName'] === $GLOBALS['prox_username'] && empty($posts_array) && in_array($key['id'], $projects_to_import) && !empty($projects)) {  // only import if it's the users post AND if the post (the pb-project-id) is not already there
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

            // get a list of modules associated with the project and save it as a metakey
            $project_modules = json_decode(wp_remote_retrieve_body(wp_remote_get($url."/".$key['id']."/modules", array('headers' => array(
                'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])))), true)['_embedded']['projectModules'];

            $temp_array = array();
            foreach ($project_modules as $value) {
                array_push($temp_array, $value['id']);
            }
            update_post_meta($post_id, '_pb_wporg_meta_studyModules', $temp_array);

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

            // get a list of modules associated with the project and save it as a metakey
            $project_modules = json_decode(wp_remote_retrieve_body(wp_remote_get($url."/".$key['id']."/modules", array('headers' => array(
                'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])))), true)['_embedded']['projectModules'];

            $temp_array = array();
            foreach ($project_modules as $value) {
                array_push($temp_array, $value['id']);
            }
            update_post_meta($post_id, '_pb_wporg_meta_studyModules', $temp_array);
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