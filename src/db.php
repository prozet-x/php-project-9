<?php

function getUrlDataById(PDO $connection, $id): ?int
{
    $queryForUrl = "SELECT * FROM urls WHERE id={$id}";
    $resQueryForUrl = $connection -> query($queryForUrl);
    $urlData = $resQueryForUrl -> fetch();
    return $urlData === false
        ? false
        : ['id' => $urlData['id'], 'name' => $urlData['name'], 'created_at' => $urlData['created_at']];
}

function getIdByName($connection, $name)
{
    $queryForUrl = "SELECT id FROM urls WHERE name='{$name}'";
    $resQueryForUrl = $connection -> query($queryForUrl);
    $urlData = $resQueryForUrl -> fetch();
    return $urlData === false ? false : $urlData['id'];
}

function getUrlChecksById($connection, $id)
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

function getConnectionToDB($request)
{
    $databaseUrl = parse_url($_ENV['DATABASE_URL']);
    $username = $databaseUrl['user']; // janedoe
    $password = $databaseUrl['pass']; // mypassword
    $host = $databaseUrl['host']; // localhost
    $port = $databaseUrl['port']; // 5432
    $nameOfDB = ltrim($databaseUrl['path'], '/'); // mydb

    $dbDriver = 'pgsql';
    $dbHost = $host; //'localhost';
    $dbPort = $port; //'5432';
    $dbName = $nameOfDB; //'phpproj3test';
    $dbUserName = $username; //'dima';
    $dbUserPassword = $password; //'pwd';

    /*$dbHost = 'localhost';
    $dbPort = '5432';
    $dbName = 'phpproj3test';
    $dbUserName = 'dima';
    $dbUserPassword = 'pwd';*/

    $connectionString = "{$dbDriver}:host={$dbHost};port={$dbPort};dbname={$dbName};";
    try {
        return new PDO($connectionString, $dbUserName, $dbUserPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception) {
        throw new HttpInternalServerErrorException($request, 'DB-connection error.');
    }
}
