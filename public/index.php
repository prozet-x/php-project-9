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

$container -> set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($req, $resp) {
    $params = [];
    $messages = $this -> get('flash') -> getMessages();
    if (!empty($messages)) {
        $params['messages'] = $messages;
    }
    return $this -> get('renderer') -> render($resp, 'main.phtml', $params);
}) -> setName('main');

$app -> post('/urls', function($req, $resp) use ($router) {
    $inputedURL = $req -> getParsedBodyParam('url', null);

    if ($inputedURL === null) {
        $this -> get('flash') -> addMessage('warning', 'Нужно вести адрес веб-страницы');
        return $resp -> withRedirect($router -> urlFor('main'));
    }

    $url = parse_url($inputedURL['name']);
    $scheme = $url['scheme'];
    $host = $url['host'];
    $validator = new Valitron\Validator(array('url' => $inputedURL['name'], 'host' => $host));
    $validator -> rule('required', 'url')
        -> rule('lengthMax', 'url', 255)
        -> rule('url', 'url')
        -> rule('contains', 'host', '.');
    if($validator->validate()) {
        $connectionString = "pgsql:host=localhost;port=5432;dbname=phpproj3test;";
        /*try {

        }
        catch () {

        }*/
        $pdo = new PDO($connectionString, 'prozex', 'pwd', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        if ($pdo) {
            $resOfQuery = $pdo->query("SELECT COUNT(*) AS counts FROM urls WHERE name='{$scheme}://{$host}'");
            if (($resOfQuery -> fetch())['counts'] === 0) {
                $pdo->query("INSERT INTO urls (name, created_at) VALUES ('{$scheme}://{$host}', current_timestamp)");
            } else {
                return $resp -> write('Already exists!!!');
            }

            $res = '';
            foreach($pdo->query('SELECT * from urls') as $row) {
                $res .= $row['name'] . '   ' . $row['created_at'];
            }
            return $resp -> write($res);
        }
    } else {
        return $this -> get('renderer') -> render($resp, 'main.phtml', ['badURL' => true, 'inputedURL' => $inputedURL['name']]);
    }



    //INSTALL PGSQL-provider: apt-get install php-pgsql. Then restart appache. And you will need to create a pass for user

    return $resp -> write(count(PDO::getAvailableDrivers()));
});

$app->run();
