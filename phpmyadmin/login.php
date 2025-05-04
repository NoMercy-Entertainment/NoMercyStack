<?php

require __DIR__ . '/classes/KeycloakClient.php';

function get_login_credentials()
{
    $client = new KeycloakClient();
    $access = $client->has_role('PHPMyAdmin');

    if ($access) {
        $username = getenv('DB_USERNAME');
        $password = getenv('DB_PASSWORD');

        return [
            $username,
            $password,
        ];
    }
}
