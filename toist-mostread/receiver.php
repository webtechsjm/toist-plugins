<?php

session_start();
require_once('Google_Client.php');

$clientID = '254405477884.apps.googleusercontent.com';
$clientSecret = '5OtpQZpvBAdfXeDDWd-CLSri';
$api_key = 'AIzaSyDZmhe5G57bmRLRYb2yhVMKb2xVg3lrWdw';
$app_name = 'Torontoist Most Read';
$redirect = 'http://stage-torontoist.stjosephmedia.com/wp-content/plugins/toist-mostread/receiver.php';

$client = new Google_Client();
$client->setApplicationName($app_name);
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirect);
$client->setDeveloperKey($api_key);
$client->setScopes(array('https://www.googleapis.com/auth/analytics.readonly'));
$client->setUseObjects(true);

if(isset($_GET['code'])){
	$client->authenticate();
	$_SESSION['token'] = $client->getAccessToken();
	$redirect = 'http://stage-torontoist.stjosephmedia.com/wp-admin/options-general.php?page=toist-mostread';
	header('Location: '.filter_var($redirect,FILTER_SANITIZE_URL));
}

?>
