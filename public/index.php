<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

session_start();

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($req, $resp) {
    return $this -> get('renderer') -> render($resp, 'main.phtml');
});

$app -> post('/urls', function($req, $resp) {
    $inputedData = $req -> getParsedBodyParam('url');
    /*$dbh = new PDO('pgsql:host=localhost;dbname=phpproj3test', 'dima');
    $res = '';
    foreach($dbh->query('SELECT * from urls') as $row) {
        $res .= $row;
    }
    $dbh = null;*/

    //INSTALL PGSQL-provider: apt-get install php-pgsql. Then restart appache. And you will need to create a pass for user


    $dsn = "pgsql:host=localhost;port=5432;dbname=phpproj3test;";
    $pdo = new PDO($dsn, 'dima', 'pwd', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    if ($pdo) {
        $res = '';
        foreach($pdo->query('SELECT * from urls') as $row) {
            $res .= $row['name'] . '   ' . $row['created_at'];
        }
        return $resp -> write($res);
    }

    return $resp -> write(count(PDO::getAvailableDrivers()));
});

$app->run();
