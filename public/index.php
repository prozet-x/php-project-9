<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;
use GuzzleHttp\Client;
use DiDom\Document;

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
    $connection = getConnectionToDB();
    if ($connection === false) {
        return error500Page($this, $resp, $router);
    }
    //МОЖНО РУКАМИ ВВЕСТТИ АДРЕС 127.0.0.1:8000/urls/<НЕСУЩЕСТВУЮЩИЙ ИД>, И САЙТ НЕ ВЫДАСТ 404 ОШИБКИ
    //$queryForUrls = "SELECT urls.id AS urls_id, name, MAX(url_checks.created_at) AS last_check_time, status_code FROM urls LEFT JOIN url_checks ON urls.id = url_checks.url_id GROUP BY urls_id ORDER BY urls_id DESC";

    $queryForUrls = "SELECT
                        urls.id AS id_of_url,
                        name,
                        last_check_req.created_at AS last_check_datetime,
                        name,
                        status_code 
                    FROM
                        urls
                        LEFT JOIN
                        (SELECT
                             max_id_req.url_id AS url_id,
                             created_at,
                             status_code
                         FROM
                             (SELECT
                                  url_id,
                                  MAX(id) AS max_id
                              FROM url_checks
                              GROUP BY url_id
                             ) AS max_id_req
                             LEFT JOIN
                                 url_checks
                             ON
                                 max_id = url_checks.id
                        ) AS last_check_req
                        ON
                            urls.id = last_check_req.url_id
                    ORDER BY id_of_url DESC";

    $resQueryForUrls = $connection -> query($queryForUrls);
    $urls = [];
    foreach ($resQueryForUrls as $row) {
        $urls[] = [
            'id' => $row['id_of_url'],
            'name' => $row['name'],
            'status_code' => $row['status_code'],
            'lastCheckTime' => $row['last_check_datetime']
        ];
    }

    $params = ['urls' => $urls];

    $messages = $this -> get('flash') -> getMessages();
    if (!empty($messages)) {
        $params['messages'] = $messages;
    }

    return $this -> get('renderer') -> render($resp, 'urls.phtml', $params);
}) -> setName('urls');

$app->get('/urls/{id}', function ($req, $resp, $args) use ($router) {
    $connection = getConnectionToDB();
    if ($connection === false) {
        return error500Page($this, $resp, $router);
    }

    $id = $args['id'];

    $urlData = getUrlDataById($connection, $id);

    if ($urlData === false) {
        return error404Page($this, $resp);
    }

    $params = ['url' => $urlData];
    $urlChecks = getUrlChecksById($connection, $id);
    $params['urlChecks'] = $urlChecks;

    $messages = $this -> get('flash') -> getMessages();
    if (!empty($messages)) {
        $params['messages'] = $messages;
    }

    return $this -> get('renderer') -> render($resp, 'url.phtml', $params);
}) -> setName('urlID');

$app -> post('/clearurls', function ($req, $resp) use ($router) {
    $connection = getConnectionToDB();
    if ($connection === false) {
        return error500Page($this, $resp, $router);
    }

    $queryForClearing = "TRUNCATE urls, url_checks";
    $connection->query($queryForClearing);
    $this -> get('flash') -> addMessage('warning', 'Таблицы очищены');
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

    $connection = getConnectionToDB();
    if ($connection === false) {
        return error500Page($this, $resp, $router);
    }

    $queryForExisting = "SELECT COUNT(*) AS counts FROM urls WHERE name='{$scheme}://{$host}'";
    $resOfQueryForExisting = $connection->query($queryForExisting);
    if (($resOfQueryForExisting -> fetch())['counts'] === 0) {
        $queryForInsertNewData = "INSERT INTO urls (name, created_at) VALUES ('{$scheme}://{$host}', date_trunc('second', current_timestamp))";
        $connection->query($queryForInsertNewData);
        $this -> get('flash') -> addMessage('success', 'Страница успешно добавлена');
    } else {
        $this -> get('flash') -> addMessage('warning', 'Страница уже существует');
    }
    return $resp -> withRedirect($router -> urlFor('main'), 302);
    //INSTALL PGSQL-provider: apt-get install php-pgsql. Then restart appache. And you will need to create a pass for user
});

$app->post('/urls/{id}/checks', function ($req, $resp, $args) use ($router) {
    $id = $args['id'];

    $connection = getConnectionToDB();
    if ($connection === false) {
        return error500Page($this, $resp, $router);
    }

    $queryGetUrl = "SELECT name FROM urls WHERE id={$id}";
    $resQueryGetUrl = $connection->query($queryGetUrl);
    $fetchedRes = $resQueryGetUrl -> fetch();
    if ($fetchedRes === false) {
        $this -> get('flash') -> addMessage('error', 'При проверке возникла ошибка. Такой записи не существует.');
        return $resp -> withRedirect($router -> urlFor('urls'), 303);
    }

    $url = $fetchedRes['name'];
    $client = new Client(['base_uri' => $url, 'timeout' => 5.0]);

    $response = null;
    try {
        $response = $client -> request('GET', '/');
    }
    catch (Exception) {
        $this -> get('flash') -> addMessage('error', 'Произошла ошибка при проверке');
        return $resp -> withStatus(404) -> withRedirect($router -> urlFor('urlID', ['id' => $id]));
    }

    $statusCodeOfResponse = $response -> getStatusCode();
    $bodyOfResponse = (string) ($response -> getBody());

    $h1 = null;
    $title = null;
    $description = null;
    if ($statusCodeOfResponse === 200) {
        $document = new Document($bodyOfResponse);
        $h1Elements = $document->find('h1');
        if (count($h1Elements) > 0) {
            $h1 = $h1Elements[0] -> text();
        }
        $titleElements = $document -> find ('title');
        if (count($titleElements) > 0) {
            $title = optional($titleElements[0]) -> text();
        }
        $metaDescriptionElements = $document -> find ('meta[name=description]');
        if (count($metaDescriptionElements) > 0) {
            $description = optional($metaDescriptionElements[0]) -> content;
        }
    }

    try {
        $queryForInsertNewCheck = "INSERT INTO url_checks (url_id, status_code, created_at, h1, title, description) VALUES ('{$id}', {$statusCodeOfResponse}, date_trunc('second', current_timestamp), '{$h1}', '{$title}', '{$description}')";
        $connection->query($queryForInsertNewCheck);
    }
    catch (Exception) {
        return $this-> get('renderer') -> render($resp -> withStatus(500), 'error500.phtml');
    }
    $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    return $resp->withRedirect($router->urlFor('urlID', ['id' => $id]), 302);
});

function getUrlDataById($connection, $id)
{
    $queryForUrl = "SELECT * FROM urls WHERE id={$id}";
    $resQueryForUrl = $connection -> query($queryForUrl);
    $urlData = $resQueryForUrl -> fetch();
    return $urlData === false
        ? false
        : ['id' => $urlData['id'], 'name' => $urlData['name'], 'created_at' => $urlData['created_at']];
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

function error500Page($DIContainer, $resp) {
    return $DIContainer-> get('renderer') -> render($resp -> withStatus(500), 'error500.phtml');
}

function error404Page($DIContainer, $resp) {
    return $DIContainer-> get('renderer') -> render($resp -> withStatus(404), 'error404.phtml');
}

function getConnectionToDB() {
    $dbDriver = 'pgsql';
    $dbHost = 'localhost';
    $dbPort = '5432';
    $dbName = 'phpproj3test';
    $dbUserName = 'prozex';
    $dbUserPassword = 'pwd';
    $connectionString = "{$dbDriver}:host={$dbHost};port={$dbPort};dbname={$dbName};";
    try {
        return new PDO($connectionString, $dbUserName, $dbUserPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
    catch (Exception) {
        return false;
    }
}

$app->run();
