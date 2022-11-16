<?php

// При проверке Авито у меня просто ошибка, а в дем. проекте все иначе. Исправить.
require __DIR__ . '/../vendor/autoload.php';

use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpInternalServerErrorException;
use App\Error\Renderer\HtmlErrorRenderer;
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
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->registerErrorRenderer('text/html', HtmlErrorRenderer::class);
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($req, $resp) {
    $params = addMessagesToParams($this -> get('flash') -> getMessages());
    $params['activeMenuItem'] = 'main';
    return $this -> get('renderer') -> render($resp, 'main.phtml', $params);
}) -> setName('main');

$app->get('/urls', function ($req, $resp) use ($router) {
    $connection = getConnectionToDB($req);

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
    $params = addMessagesToParams($this -> get('flash') -> getMessages(), $params);
    $params['activeMenuItem'] = 'urls';
    return $this -> get('renderer') -> render($resp, 'urls.phtml', $params);
}) -> setName('urls');

$app->get('/urls/{id}', function ($req, $resp, $args) use ($router) {
    $connection = getConnectionToDB($req);

    $id = $args['id'];
    $urlData = getUrlDataById($connection, $id);
    if ($urlData === false) {
        throw new HttpNotFoundException($req, 'There is no record with this ID');
    }

    $params = ['url' => $urlData];
    $urlChecks = getUrlChecksById($connection, $id);
    $params['urlChecks'] = $urlChecks;
    $params = addMessagesToParams($this -> get('flash') -> getMessages(), $params);

    return $this -> get('renderer') -> render($resp, 'url.phtml', $params);
}) -> setName('urlID');

/*$app -> post('/clearurls', function ($req, $resp) use ($router) {
    $connection = getConnectionToDB($req);

    $queryForClearing = "TRUNCATE urls, url_checks";
    $connection->query($queryForClearing);
    $this -> get('flash') -> addMessage('warning', 'Таблицы очищены');
    return $resp -> withRedirect($router -> urlFor('main'), 302);
});*/

$app -> post('/urls', function ($req, $resp) use ($router) {
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
        $params = ['badURL' => true, 'inputedURL' => $inputedURL['name']];
        return $this -> get('renderer') -> render($resp, 'main.phtml', $params);
    }

    $connection = getConnectionToDB($req);

    $queryForExisting = "SELECT COUNT(*) AS counts FROM urls WHERE name='{$scheme}://{$host}'";
    $resOfQueryForExisting = $connection->query($queryForExisting);
    if (($resOfQueryForExisting -> fetch())['counts'] === 0) {
        $name = "{$scheme}://{$host}";
        $queryForInsertNewData = "INSERT INTO 
                                    urls (name, created_at)
                                  VALUES
                                      ('{$name}', date_trunc('second', current_timestamp))";
        $connection->query($queryForInsertNewData);
        $this -> get('flash') -> addMessage('success', 'Страница успешно добавлена');
        $id = getIdByName($connection, $name);
        return $resp->withRedirect($router->urlFor('urlID', ['id' => $id]), 302);
    }

    $this -> get('flash') -> addMessage('warning', 'Страница уже существует');
    return $resp -> withStatus(409) -> withRedirect($router -> urlFor('main'));
    //INSTALL PGSQL-provider: apt-get install php-pgsql.
    //Then restart appache. And you will need to create a pass for user
});

$app->post('/urls/{id}/checks', function ($req, $resp, $args) use ($router) {
    $id = $args['id'];
    $connection = getConnectionToDB($req);
    $urlData = getUrlDataById($connection, $id);
    if ($urlData === false) {
        throw new HttpInternalServerErrorException($req, 'This page is not exists.');
    }

    $url = $urlData['name'];
    $client = new Client(['base_uri' => $url, 'timeout' => 5.0]);
    try {
        $response = $client -> request('GET', '/');
    } catch (Exception) {
        $this -> get('flash') -> addMessage('error', 'Произошла ошибка при проверке');
        return $resp -> withStatus(404) -> withRedirect($router -> urlFor('urlID', ['id' => $id]));
    }

    $statusCodeOfResponse = $response -> getStatusCode();
    $bodyOfResponse = (string) ($response -> getBody());

    $h1 = null;
    $title = null;
    $description = null;
    $document = new Document($bodyOfResponse);
    $h1Elements = $document->find('h1');
    if (count($h1Elements) > 0) {
        $h1 = $h1Elements[0] -> text();
    }
    $titleElements = $document -> find('title');
    if (count($titleElements) > 0) {
        $title = optional($titleElements[0]) -> text();
    }
    $metaDescriptionElements = $document -> find('meta[name=description]');
    if (count($metaDescriptionElements) > 0) {
        $description = optional($metaDescriptionElements[0]) -> content;
    }

    try {
        $queryForInsertNewCheck = "INSERT INTO
                                    url_checks (url_id, status_code, created_at, h1, title, description)
                                  VALUES
                                      ('{$id}',
                                       {$statusCodeOfResponse},
                                       date_trunc('second', current_timestamp),
                                       '{$h1}',
                                       '{$title}',
                                       '{$description}'
                                      )";
        $connection->query($queryForInsertNewCheck);
    } catch (Exception) {
        throw new HttpInternalServerErrorException($req, 'Error on adding new record to checks table');
    }
    $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    return $resp->withRedirect($router->urlFor('urlID', ['id' => $id]), 302);
});





$app->run();
