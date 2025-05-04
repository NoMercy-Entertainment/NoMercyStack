<?php

require __DIR__ . '/classes/KeycloakClient.php';

function get_login_credentials()
{
    $client = new KeycloakClient();
    $access = $client->has_role('PHPMyAdmin');

    if ($access) {
        $username = getenv('MYSQL_USERNAME');
        $password = getenv('MYSQL_PASSWORD');

        return [
            $username,
            $password,
        ];
    }
}
