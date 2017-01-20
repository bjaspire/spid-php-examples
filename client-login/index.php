<?php

// Start Session to save oauth token in session (instead of cookie)
session_start();

// Include PHP SDK Client and a config file with credentials
require_once('../vendor/autoload.php');
require_once('../config/config.php');
// overwrite redirect url to be HERE
$spidClientConfig[VGS_Client::REDIRECT_URI] = "http://{$_SERVER['HTTP_HOST']}/client-login";
$spidClientConfig[VGS_Client::COOKIE] = false; // disable cookie support for SDK

// Instantiate the SDK client
$client = new VGS_Client($spidClientConfig);
$client->argSeparator = '&';

// When a logout redirect comes from SPiD, delete the local session
if (isset($_GET['logout'])) {
   unset($_SESSION['sdk']);
}

// Code is part of the redirect back from SPiD, redirect to self to remove it from URL
// since it may only be used once, and it has been used to create session
if (isset($_GET['code'])) {
    // Get/Check if we have local session, creates ones if code GET param comes
    $_SESSION['sdk'] = $client->getSession();
    header( "Location: ". $client->getCurrentURI(array(), array('code','login','logout'))) ;
    exit;
}
?>
<!doctype html>
<html>
<head>
    <title>SPiD Client user login and authentication example</title>
    <meta charset="utf-8">
</head>
<body>
<h1>SPiD Client user login and authentication example</h1>
<?php
// May get credential errors
if (isset($_GET['error'])) {
    echo '<h3 id="message" style="color:red">'.$_GET['error'].'</h3>';
}

$session = isset($_SESSION['sdk']) ? $_SESSION['sdk'] : false;

// If we have session, that means we are logged in.
if ($session) {
    // Authorize the client with the session saved user token
    $client->setAccessToken($session['access_token']);

    // Try since SDK may throw VGS_Client_Exceptions:
    //   For instance if the client is blocked, has exceeded ratelimit or lacks access right
    try {
        // Grab the logged in user's User Object, /me will include the entire User object
        $user = $client->api('/me');

        echo '<h3 id="message">Welcome</h3>
            <h4>Logged in as <span id="name" style="color:blue">'.$user['displayName'].'</span> <small>id: <span id="userId" style="color:green">'.$user['userId'].'</span> email: <span id="email" style="color:purple">'.$user['email'].'</span></h4>';

        if (isset($_GET['order_id'])) {
            echo '<pre>'.print_r($client->api('/order/'.$_GET['order_id']),true).'</pre>';
        }

    } catch (VGS_Client_Exception $e) {
        if ($e->getCode() == 401) {
            // access denied, in case the access token is expired, try to refresh it
            try {
                // refresh tokens using the session saved refresh token
                $client->refreshAccessToken($session['refresh_token']);
                $_SESSION['sdk']['access_token'] = $client->getAccessToken();
                $_SESSION['sdk']['refresh_token'] = $client->getRefreshToken();
                // Sesssion refreshed with valid tokens
                header( "Location: ". $client->getCurrentURI(array(), array('code','login','error','logout', 'order_id', 'spid_page'))) ;
                exit;
            } catch (Exception $e2) {
                /* falls back to $e message bellow */
            }
        }
        if ($e->getCode() == 400) {
            header( "Location: ". $client->getLoginURI(array('redirect_uri' => $client->getCurrentURI(array(), array('logout','error','code', 'order_id', 'spid_page')))));
            exit;
        }

        // API exception, show message, remove session as it is probably not usable
        unset($_SESSION['sdk']);
        echo '<h3 id="error" style="color:red">'.$e->getCode().' : '.$e->getMessage().'</h3>';
    }

    echo '<p><a id="login-link" href="' . $client->getAccountURI(array('redirect_uri' =>
        $client->getCurrentURI(array(), array('logout','error','code', 'order_id', 'spid_page'))
    )) . '">My Account</a></p>';

    // Show a logout link
    echo '<p><a id="login-link" href="' . $client->getLogoutURI(array('redirect_uri' =>
        $client->getCurrentURI(array('logout' => 1), array('error','code', 'order_id', 'spid_page'))
    )) . '">Logout</a></p>';


    echo '<p><a id="login-link" href="' . $client->getPurchaseURI(array(
        'redirect_uri' => $client->getCurrentURI(array(), array('logout', 'error', 'code', 'order_id', 'spid_page')),
        'cancel_redirect_uri' => $client->getCurrentURI(array('cancel'=>1), array('logout', 'error', 'code', 'order_id', 'spid_page')),
    )) . '">Buy something</a> (standard checkout flow)</p>';


    echo '<p><a id="login-link" href="' . $client->getPurchaseURI(array(
        // 'tag' => 'taggen98',
        'campaign_id' => 1, // provide a campaign id
        // 'product_id' => YYYY,
        // 'voucher_code' => ZZZZ,
        'redirect_uri' => $client->getCurrentURI(array('cameback'=>2), array('logout', 'error', 'code', 'order_id', 'spid_page')),
        'cancel_redirect_uri' => $client->getCurrentURI(array('cancel'=>1), array('logout', 'error', 'code', 'order_id', 'spid_page')),
    )) . '">Campaign Flow</a> (checkout flow with campaign specified</p>';

} else { // No session, user must log in
 
    echo '<h3 id="message">Please log in</h3>';
    // Show a login link
    echo '<p><a id="login-link" href="' . $client->getLoginURI(array(
        'redirect_uri' => $client->getCurrentURI(array('place' => 'oslo'), array('logout','error','code', 'default', 'cancel', 'order_id', 'spid_page')),
        'cancel_redirect_uri' => $client->getCurrentURI(array('cancel' => 1), array('logout','error','code', 'default', 'cancel', 'order_id', 'spid_page')),
    )) . '">Login</a> (standard auth flow)</p>';

    echo '<h5>or</h5>';
    echo '<p><a id="signup-flow-link" href="' . $client->getSignupURI(array(
        'redirect_uri' => $client->getCurrentURI(array(), array('logout','error','code', 'order_id', 'spid_page')),
        'cancel_redirect_uri' => "http://google.com"
    )) . '">Signup Flow</a> (standard auth flow with signup parameter</p>';

    echo '<h5>or</h5>';
    echo '<p><a id="checkout-link" href="' . $client->getPurchaseURI(array(
        'redirect_uri' => $client->getCurrentURI(array(), array('logout','error','code', 'order_id', 'spid_page')),
        'cancel_uri' => $client->getCurrentURI(array('cancel' => 1), array('logout','error','code', 'default', 'cancel', 'order_id', 'spid_page')),
    )) . '">Buy</a> (standard checkout flow)</p>';

}

?>
</body>
</html>
