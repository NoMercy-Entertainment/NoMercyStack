<?php

use JetBrains\PhpStorm\NoReturn;

if (!class_exists('DotEnv')) {
    require '/var/www/html/classes/DotEnv.php';
}
new DotEnv('/var/www/.env')->load();

class KeycloakClient
{
    private string $baseUrl;
    private string $realm;
    private string $client_id;
    private string $client_secret;
    private string $phpmyadminUrl;
    private string $query;
    private string $scope;

    public function __construct()
    {
        $this->baseUrl = getenv('KEYCLOAK_FRONTEND_URL');
        $this->realm = getenv('KEYCLOAK_REALM');
        $this->client_id = getenv('KEYCLOAK_CLIENT_ID');
        $this->client_secret = getenv('KEYCLOAK_CLIENT_SECRET');
        $this->baseUrl = "https://$this->baseUrl/realms/$this->realm/protocol/openid-connect";
        $this->phpmyadminUrl = getenv('MYSQL_SERVER_NAME');

        $this->query = $this->filtered_querystring();

        $this->scope = 'openid';
    }

    public function login(): void
    {
        if (isset($_COOKIE['refresh_token'])) {
            $this->refresh_token();
        } else if (!isset($_GET['callback'])) {
            $this->get_code();
        } else {
            $this->get_token($_GET['code']);
        }
    }

    public function logout(): void
    {
        if (isset($_COOKIE['access_token'])) {
            unset($_COOKIE['access_token']);
            setcookie('access_token', null, -1, '/');
        }
        if (isset($_COOKIE['refresh_token'])) {
            unset($_COOKIE['refresh_token']);
            setcookie('refresh_token', null, -1, '/');
        }

        header("Location: $this->baseUrl/logout?redirect_uri=$this->phpmyadminUrl");
    }

    public function get_code(): void
    {
        $response_mode = 'query';
        $response_type = 'code';
        $nonce = uniqid(strval(rand()), true);
        $redirect = urlencode("$this->phpmyadminUrl/auth.php?callback=1");

        header("Location: $this->baseUrl/auth?client_id=$this->client_id&response_mode=$response_mode&response_type=$response_type&scope=$this->scope&nonce=$nonce&redirect_uri=$redirect");
        die();
    }

    public function get_token($code): void
    {
        $redirect = urlencode("$this->phpmyadminUrl/auth.php?callback=1");

        $grant_type = 'authorization_code';

        $params = "grant_type=$grant_type&client_id=$this->client_id&client_secret=$this->client_secret&code=$code&scope=$this->scope&redirect_uri=$redirect";

        $response = $this->curl("$this->baseUrl/token", $params);

        if (isset($response['error'])) {
            if (isset($_COOKIE['access_token'])) {
                unset($_COOKIE['access_token']);
                setcookie('access_token', null, -1, '/');
            }
            if (isset($_COOKIE['refresh_token'])) {
                unset($_COOKIE['refresh_token']);
                setcookie('refresh_token', null, -1, '/');
            }

            header("Location: $this->phpmyadminUrl");

            die();
        }

        if(!$response || !$response["access_token"]) {
            header("Location: $this->phpmyadminUrl/auth.php$this->query");
            die();
        }

        $token = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $response["access_token"])[1]))));

        foreach ($token->resource_access->master->roles as $role) {
            if ($role == 'PHPMyAdmin') {
                setcookie("access_token", $response["access_token"], time() + (60 * 60 * 10), "", "", "", true);
                setcookie("refresh_token", $response["refresh_token"], 0, "", "", "", true);
                break;
            }
        }

        header("Location: $this->phpmyadminUrl/index.php$this->query");
    }

    public function check_token()
    {
        if (!isset($_COOKIE["access_token"])) {
            header("Location: $this->phpmyadminUrl/auth.php$this->query");
            die();
        }

        $grant_type = 'token';
        $access_token = $_COOKIE["access_token"];

        $response = $this->curl("$this->baseUrl/userinfo", "grant_type=$grant_type&client_id=$this->client_id&client_secret=$this->client_secret", $access_token);

        if ($response == null) {
            header("Location: $this->phpmyadminUrl/auth.php$this->query");
            die();
        }

        if (isset($response['error'])) {
            if (isset($_COOKIE['refresh_token'])) {
                $this->refresh_token();
            } else {
                header("Location: $this->phpmyadminUrl/auth.php$this->query");
                die();
            }
        }

        if(!isset($response["sub"])) {
            header("Location: $this->phpmyadminUrl/auth.php$this->query");
            die();
        }

        return $response;
    }

    #[NoReturn] public function refresh_token(): void
    {
        $redirect = urlencode("$this->phpmyadminUrl/auth.php?callback=1&$this->query");

        $grant_type = 'refresh_token';
        $refresh_token = $_COOKIE["refresh_token"];

        $response = $this->curl("$this->baseUrl/token", "grant_type=$grant_type&client_id=$this->client_id&client_secret=$this->client_secret&scope=$this->scope&refresh_token=$refresh_token&redirect_uri=$redirect");

        if (isset($response['error'])) {
            if (isset($_COOKIE['access_token'])) {
                unset($_COOKIE['access_token']);
                setcookie('access_token', null, -1, '/');
            }
            if (isset($_COOKIE['refresh_token'])) {
                unset($_COOKIE['refresh_token']);
                setcookie('refresh_token', null, -1, '/');
            }

            header("Location: $this->phpmyadminUrl/auth.php$this->query");
            die();
        }

        if(!isset($response["access_token"]) || !isset($response["refresh_token"])) {
            header("Location: $this->phpmyadminUrl/auth.php$this->query");
            die();
        }

        $token = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $response["access_token"])[1]))));

        foreach ($token->resource_access->master->roles as $role) {
            if ($role == 'PHPMyAdmin') {
                setcookie("access_token", $response["access_token"], time() + (60 * 60 * 10), "", "", "", true);
                setcookie("refresh_token", $response["refresh_token"], time() + (60 * 60 * 10), "", "", "", true);
                break;
            }
        }

        header("Location: $this->phpmyadminUrl/index.php$this->query");
        die();
    }

    public function has_role($realmRole)
    {
        $response = $this->check_token();

        $token = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $_COOKIE["access_token"])[1]))));

        if (isset($response['error'])) {
            header("Location: $this->phpmyadminUrl/auth.php$this->query");
            die();
        }

        $access = false;
        foreach ($token->resource_access->master->roles as $role) {
            if ($role == $realmRole) {
                $access = true;
                break;
            }
        }

        return $access;
    }

    public function filtered_querystring(): string
    {
        $result = [];

        if (isset($_SERVER["QUERY_STRING"]) && $_SERVER["QUERY_STRING"] != '') {
            $query = explode('&', $_SERVER["QUERY_STRING"]);

            foreach ($query as $key => $value) {
                if ($value != '' && $key != 'callback' && $key != 'code' && $key != 'state' && $key != 'session_state') {
                    $key = explode('=', $value)[0];
                    $result[$key] = $key . '=' . explode('=', $value)[1];
                }
            }

            return '?' . implode('&', $result);
        }

        return '';
    }

    private function curl($url, $params, $access_token = null)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Bearer ' . $access_token,
            ),
        ));


        $response = curl_exec($curl);

        curl_close($curl);

        if(!$response) {
            return $this->curl($url, $params, $access_token);
        }

        return json_decode($response, true);

    }
}
