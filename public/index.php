<?php

/*require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($req, $resp) {
    return $resp->write('Welcome to Slim!');
});
$app->run();*/



//require __DIR__ . '/../vendor/autoload.php';
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
    $inputedData = $req -> getParserBodyParam('url');
});

$app->run();
/*echo 'in public/index.php' . '<br>';
echo __DIR__ . '<br>';
print_r(scandir(__DIR__));
echo '<br>';
print_r(scandir(__DIR__ . '/..'));*/
