<?php
/**
 * Check whether the user is authenticated.
 *
 * This is achieved by calling the userinfo endpoint from keycloak using the current access token. If it returns status
 * code 200, then the user is logged in.
 */
function pb_keycloak_is_authenticated() {
    $response = wp_remote_get('https://login.coalbase.io/auth/realms/prox/protocol/openid-connect/userinfo', array('headers' => array(
        'Authorization' => 'Bearer ' . $GLOBALS['pb_access_token'])));
    $response_code = wp_remote_retrieve_response_code( $response );
    if ($response_code === 200) {
        return true;
    }
    else {
        return false;
    }
}