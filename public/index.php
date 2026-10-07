<?php

declare(strict_types=1);

/**
 * Front controller: toda petición que no sea un archivo estático entra por acá.
 */

$root = dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
  http_response_code(500);
  exit('Faltan las dependencias. Ejecutá "composer install" en la raíz del proyecto.');
}

require $root . '/vendor/autoload.php';

App\Core\App::boot($root)->run();
