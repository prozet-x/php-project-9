<?php

declare(strict_types=1);

namespace App\Error\Renderer;

use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Interfaces\ErrorRendererInterface;
use Throwable;

final class HtmlErrorRenderer implements ErrorRendererInterface
{
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($exception instanceof HttpNotFoundException) {
            return file_get_contents(__DIR__ . '/../../templates/error404.phtml');
        } elseif ($exception instanceof HttpInternalServerErrorException) {
            return file_get_contents(__DIR__ . '/../../templates/error500.phtml');
        }
        return $exception -> getMessage();
    }
}
