<?php
    if(isset($_GET['code']) && $_GET['state'] === 'E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT') {

        $code = $_GET["code"];

        $grant_type = "authorization_code";
        $redirect_uri = "http://localhost/wp/wordpress/wp-content/plugins/projektboerse/redirect.php";
        //$redirect_uri = rtrim($_SERVER['HTTP_REFERER'], '/').$_SERVER['PHP_SELF'];
        //my_log_file2($_SERVER);
        $client_id = "wordpress-plugin";
        $url = "https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/token";

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
        curl_close($curl);
        $pb_access_token = $response['access_token'];
        $pb_refresh_token = $response['refresh_token'];
        //print_r($response);
        echo "<p style=\"word-break: break-all; word-wrap: break-word;\">Access Token: ".$pb_access_token."</p><br /><br />";
        echo "<p style=\"word-break: break-all; word-wrap: break-word;\">Refresh Token: ".$pb_refresh_token."</p>";

    }

function my_log_file2( $msg, $name = '' )
{
    // Print the name of the calling function if $name is left empty
    $trace=debug_backtrace();
    $name = ( '' == $name ) ? $trace[1]['function'] : $name;

    $error_dir = '/home/andreas/Schreibtisch/pb_debug.log';
    $msg = print_r( $msg, true );
    $log = $name . "  |  " . $msg . "\n";
    error_log( $log, 3, $error_dir );
}





