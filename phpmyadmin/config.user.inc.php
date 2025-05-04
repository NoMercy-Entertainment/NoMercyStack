<?php

require(__DIR__ . '/classes/KeycloakClient.php');

$i = 0;
$i++;
$cfg['CSPAllow'] = getenv('MYSQL_CSP_ALLOW');
$cfg['Servers'][$i]['extension'] = 'mysqli';
$cfg['Servers'][$i]['auth_type'] = 'signon';
$cfg['Servers'][$i]['SignonSession'] = 'SignonSession';
$cfg['Servers'][$i]['SignonURL'] = 'auth.php';
$cfg['Servers'][$i]['SignonScript'] = 'login.php';
$cfg['Servers'][$i]['LogoutURL'] = 'logout.php';
