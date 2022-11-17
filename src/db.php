<?php

function getUrlDataById(PDO $connection, string $id, Psr\Http\Message\ServerRequestInterface $request): ?array
{
    $queryForUrl = "SELECT * FROM urls WHERE id={$id}";
    $resQueryForUrl = $connection -> query($queryForUrl);
    if ($resQueryForUrl === false) {
        throw new Slim\Exception\HttpInternalServerErrorException($request, 'Bad request to DB');
    }
    $urlData = $resQueryForUrl -> fetch();
    return $urlData === false
        ? null
        : ['id' => $urlData['id'], 'name' => $urlData['name'], 'created_at' => $urlData['created_at']];
}

function getIdByName(PDO $connection, string $name)
{
    $queryForUrl = "SELECT id FROM urls WHERE name='{$name}'";
    $resQueryForUrl = $connection -> query($queryForUrl);
    $urlData = $resQueryForUrl -> fetch();
    return $urlData === false ? false : $urlData['id'];
}

function getUrlChecksById(PDO $connection, string $id)
{
    $queryForUrlChecks = "SELECT * FROM url_checks WHERE url_id={$id} ORDER BY id DESC";
    $resQueryForUrlChecks = $connection -> query($queryForUrlChecks);
    $urlChecks = [];
    foreach ($resQueryForUrlChecks as $row) {
        $urlChecks[] = [
            'id' => $row['id'],
            'created_at' => $row['created_at'],
            'status_code' => $row['status_code'],
            'h1' => $row['h1'],
            'title' => $row['title'],
            'description' => $row['description']
        ];
    }
    return $urlChecks;
}

function getConnectionToDB(Psr\Http\Message\ServerRequestInterface $request)
{
    $databaseUrl = parse_url($_ENV['DATABASE_URL']);
    $username = $databaseUrl['user']; // janedoe
    $password = $databaseUrl['pass']; // mypassword
    $host = $databaseUrl['host']; // localhost
    $port = $databaseUrl['port']; // 5432
    $nameOfDB = ltrim($databaseUrl['path'], '/'); // mydb

    $dbDriver = 'pgsql';
    $dbHost = $host;
    $dbPort = $port;
    $dbName = $nameOfDB;
    $dbUserName = $username;
    $dbUserPassword = $password;

    /*$dbHost = 'localhost';
    $dbPort = '5432';
    $dbName = 'phpproj3test';
    $dbUserName = 'dima';
    $dbUserPassword = 'pwd';*/

    $connectionString = "{$dbDriver}:host={$dbHost};port={$dbPort};dbname={$dbName};";
    try {
        return new PDO($connectionString, $dbUserName, $dbUserPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception) {
        throw new Slim\Exception\HttpInternalServerErrorException($request, 'DB-connection error.');
    }
}
