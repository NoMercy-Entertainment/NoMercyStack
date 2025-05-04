<?php

require(__DIR__ . '/classes/KeycloakClient.php');

$client = new KeycloakClient();
$client->logout();
