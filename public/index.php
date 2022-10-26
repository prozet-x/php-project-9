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

$app->get('/urls', function ($req, $resp) use ($router) {
    try {
        $connection = getconnectionToDB();
    }
    catch (Exception $e) {
        $this -> get('flash') -> addMessage('error', 'Возникла внутренняя ошибка сервера. Попробуйте выполнить действие позже.');
        return $resp -> withRedirect($router -> urlFor('main'), 302);
    }

    $queryForUrls = "SELECT id, name FROM urls ORDER BY id DESC";
    $resQueryForUrls = $connection -> query($queryForUrls);
    $urls = [];
    foreach ($resQueryForUrls as $row) {
        $urls[] = ['id' => $row['id'], 'name' => $row['name']];
    }
    $params = ['urls' => $urls];
    return $this -> get('renderer') -> render($resp, 'urls.phtml', $params);
}) -> setName('urls');

$app->get('/urls/{id}', function ($req, $resp, $args) use ($router) {
    try {
        $connection = getconnectionToDB();
    }
    catch (Exception $e) {
        $this -> get('flash') -> addMessage('error', 'Возникла внутренняя ошибка сервера. Попробуйте выполнить действие позже.');
        return $resp -> withRedirect($router -> urlFor('main'), 302);
    }

    $id = $args['id'];
    $queryForUrl = "SELECT * FROM urls WHERE id={$id}";
    $resQueryForUrl = $connection -> query($queryForUrl);
    $urlData = $resQueryForUrl -> fetch();
    $dateTime = new DateTime($urlData['created_at']);
    $dateTimeFormatted = $dateTime->format('Y-m-d H:i:s');
    $params = ['url' => ['id' => $id, 'name' => $urlData['name'], 'created_at' => $dateTimeFormatted]];
    return $this -> get('renderer') -> render($resp, 'url.phtml', $params);
}) -> setName('urlID');

$app -> post('/clearurls', function ($req, $resp) use ($router) {
    try {
        $connection = getconnectionToDB();
    }
    catch (Exception $e) {
        $this -> get('flash') -> addMessage('error', 'Возникла внутренняя ошибка сервера. Попробуйте выполнить действие позже.');
        return $resp -> withRedirect($router -> urlFor('main'), 302);
    }

    $queryForClearing = "DELETE FROM urls";
    $connection->query($queryForClearing);
    $this -> get('flash') -> addMessage('warning', 'Таблица urls очищена');
    return $resp -> withRedirect($router -> urlFor('main'), 302);
});

$app -> post('/urls', function($req, $resp) use ($router) {
    $inputedURL = $req -> getParsedBodyParam('url', null);

    if ($inputedURL === null) {
        $this -> get('flash') -> addMessage('warning', 'Нужно вести адрес веб-страницы');
        return $resp -> withRedirect($router -> urlFor('main'));
    }

    $url = $inputedURL['name'];
    $urlParsed = parse_url($url);
    $scheme = $urlParsed['scheme'];
    $host = $urlParsed['host'];
    $validator = new Valitron\Validator(array('url' => $url, 'host' => $host));
    $validator -> rule('required', 'url')
        -> rule('lengthMax', 'url', 255)
        -> rule('url', 'url')
        -> rule('contains', 'host', '.');

    if (!($validator->validate())) {
        return $this -> get('renderer') -> render($resp, 'main.phtml', ['badURL' => true, 'inputedURL' => $inputedURL['name']]);
    }

    try {
        $connection = getconnectionToDB();
    }
    catch (Exception $e) {
        $this -> get('flash') -> addMessage('error', 'Возникла внутренняя ошибка сервера. Попробуйте выполнить действие позже.');
        return $resp -> withRedirect($router -> urlFor('main'), 302);
    }

    $queryForExisting = "SELECT COUNT(*) AS counts FROM urls WHERE name='{$scheme}://{$host}'";
    $resOfQueryForExisting = $connection->query($queryForExisting);
    if (($resOfQueryForExisting -> fetch())['counts'] === 0) {
        $queryForInsertNewData = "INSERT INTO urls (name, created_at) VALUES ('{$scheme}://{$host}', current_timestamp)";
        $connection->query($queryForInsertNewData);
        $this -> get('flash') -> addMessage('success', 'Страница успешно добавлена');
    } else {
        $this -> get('flash') -> addMessage('warning', 'Страница уже существует');
    }
    return $resp -> withRedirect($router -> urlFor('main'), 302);
    /*$res = '';
    foreach($connection->query('SELECT * from urls') as $row) {
        $res .= $row['name'] . '   ' . $row['created_at'];
    }
    return $resp -> write($res);*/





    //INSTALL PGSQL-provider: apt-get install php-pgsql. Then restart appache. And you will need to create a pass for user
});

function getconnectionToDB() {
    $dbDriver = 'pgsql';
    $dbHost = 'localhost';
    $dbPort = '5432';
    $dbName = 'phpproj3test';
    $dbUserName = 'prozex';
    $dbUserPassword = 'pwd';
    $connectionString = "{$dbDriver}:host={$dbHost};port={$dbPort};dbname={$dbName};";
    return new PDO($connectionString, $dbUserName, $dbUserPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

$app->run();
