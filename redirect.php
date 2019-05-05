<?php

function pb_get_access_token() {
    if(isset($_GET['code']) && $_GET['state'] === 'E9QcyBYe7kVaxjgXOrdwRevUDABhUHMlVIT8fzzd8FYx5EBALT') {
        $code = $_GET["code"];

        $grant_type = "authorization_code";
        //$redirect_uri = "http://localhost/wp/wordpress/wp-content/plugins/projektboerse/redirect.php";
        $redirect_uri = rtrim($_SERVER['HTTP_REFERER'], '/').$_SERVER['PHP_SELF'];
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
        echo "Access Token: ".$pb_access_token."<br /><br />";
        echo "Refresh Token: ".$pb_refresh_token;

    }
}





