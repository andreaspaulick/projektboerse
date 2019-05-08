<?php
$GLOBALS['prox_token'] = "hello";
    if(isset($_GET['code']) && $_GET['state'] === 'E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT') {

        session_start();
        $code = $_GET["code"];

        $grant_type = "authorization_code";
        $redirect_uri = $_SESSION['pb_plugins_url'];
        $client_id = "wordpress-plugin";
        $url = $_SESSION['pb_oauth_token_uri'];

        $post_data = array(
            'code'          => $code,
            'grant_type'    => "authorization_code",
            'redirect_uri'  => $redirect_uri,
            'client_id'     => $client_id
        );

        //echo $redirect_uri;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $curl_response = curl_exec($curl);
        $response = json_decode($curl_response, true);
        $info = curl_getinfo($curl);
        curl_close($curl);
        $pb_access_token = $response['access_token'];
        $pb_refresh_token = $response['refresh_token'];
//        echo "<p style=\"word-break: break-all; word-wrap: break-word;\">Access Token: ".$pb_access_token."</p><br /><br />";
//        echo "<p style=\"word-break: break-all; word-wrap: break-word;\">Refresh Token: ".$pb_refresh_token."</p>";
        // TODO reasonable feedback

        ?>
        <style>
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
        </style>
        <?php
            if($info['http_code'] === 200) {
                ?>
                    <h1>Prox Login</h1>
                    <h3 style="color:green">Einloggen erfolgreich!</h3>
                    <p>
                        Sie wurden erfolgreich eingeloggt. Alle Funktionen des Prox WordPress Clients stehen Ihnen jetzt zur Verfügung.
                    </p>
                    <p>
                        Sie können dieses Fenster nun schließen.
                    </p>
                    <p><button type='button' onclick='window.close()'>Schließen</button></p>
                <?php
            }
            else {
                ?>
                    <h1>Prox Login</h1>
                     <h3 style="color:red">Fehler beim einloggen!</h3>
                    <p>
                        Beim einloggen trat ein Fehler auf. Bitte versuchen Sie es erneut.
                    </p>
                    <p><button type='button' onclick='window.close()'>Schließen</button></p>
                <?php
            }

        $_SESSION['wejf4uergzu'] = $pb_access_token;

    }





