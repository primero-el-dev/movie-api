<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

require_once '../vendor/autoload.php';
require_once 'functions.php';

use App\Database\Database;
use App\Exception\AccessDeniedException;
use App\Management\CountryManagement;
use App\Management\GenreManagement;
use App\Management\PersonManagement;
use App\Management\MovieManagement;
use App\Management\UserManagement;
use App\Storage\Session;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;

Session::start();

$_ENV += Dotenv\Dotenv::parse(file_get_contents('../.env'));

function validate(array $schema, array $array) {
    Schema::import($schema)->in($array);
}

function dd(...$args): never
{
    echo '<pre>';
    foreach (debug_backtrace() as $d) {
        echo $d['file'] . ' : ' . $d['line'] . PHP_EOL;
    }
    var_dump($args);
    echo '</pre>';
    die;
}

function de(Exception $e): never
{
    echo '<pre>';
    foreach ($e->getTrace() as $d) {
        echo $d['file'] . ' : ' . $d['line'] . PHP_EOL;
    }
    echo $e->getMessage();
    echo '</pre>';
    die;
}

function createSchema(array $schema): SchemaContract
{
    return Schema::import(array_to_object($schema));
}

function checkSchema(array $schema, $data)
{
    if (is_array($data)) {
        $data = (empty($data)) ? new stdClass() : array_to_object($data);
    }

    return Schema::import(array_to_object($schema))->in($data);
}


function redirect(string $method, string $uri, array $params, string $content): void
{
    header('Location: ' . $uri);
}

function dev(): bool
{
    return true;
}

if (php_sapi_name() !== 'cli') {
    Database::init();
    $path = explode('?', $_SERVER['REQUEST_URI'])[0];
    $method = $_SERVER['REQUEST_METHOD'];

    try {
        $content = null;
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $content = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
        }
        elseif ($method === 'GET') {
            $content = $_GET;
        }

        $matches = [];
        if ($method === 'POST' && $path === '/login') {
            $response = UserManagement::login($content);
        }
        elseif ($method === 'POST' && $path === '/logout') {
            $response = UserManagement::logout();
        }
        elseif ($method === 'PUT' && $path === '/self') {
            $response = UserManagement::selfEdit($content);
        }
        elseif ($method === 'POST' && $path === '/user') {
            $response = UserManagement::add($content);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/user\/(\d+)$/', $path, $matches)) {
            $response = UserManagement::delete((int) $matches[1]);
        }
        // GENRES
        elseif ($method === 'GET' && $path === '/genre') {
            $response = GenreManagement::index($content);
        }
        elseif ($method === 'GET' && preg_match('/^\/genre\/(\d+)$/', $path, $matches)) {
            $response = GenreManagement::find((int) $matches[1]);
        }
        elseif ($method === 'POST' && $path === '/genre') {
            $response = GenreManagement::add($content);
        }
        elseif ($method === 'PUT' && preg_match('/^\/genre\/(\d+)$/', $path, $matches)) {
            $response = GenreManagement::edit($content, (int) $matches[1]);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/genre\/(\d+)$/', $path, $matches)) {
            $response = GenreManagement::delete((int) $matches[1]);
        }
        // COUNTRIES
        elseif ($method === 'GET' && $path === '/country') {
            $response = CountryManagement::index($content);
        }
        elseif ($method === 'GET' && preg_match('/^\/country\/(\d+)$/', $path, $matches)) {
            $response = CountryManagement::find((int) $matches[1]);
        }
        elseif ($method === 'POST' && $path === '/country') {
            $response = CountryManagement::add($content);
        }
        elseif ($method === 'PUT' && preg_match('/^\/country\/(\d+)$/', $path, $matches)) {
            $response = CountryManagement::edit($content, (int) $matches[1]);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/country\/(\d+)$/', $path, $matches)) {
            $response = CountryManagement::delete((int) $matches[1]);
        }
        // PERSONS
        elseif ($method === 'GET' && $path === '/person') {
            $response = PersonManagement::index($content);
        }
        elseif ($method === 'GET' && preg_match('/^\/person\/(\d+)$/', $path, $matches)) {
            $response = PersonManagement::find((int) $matches[1]);
        }
        elseif ($method === 'POST' && $path === '/person') {
            $response = PersonManagement::add($content);
        }
        elseif ($method === 'PUT' && preg_match('/^\/person\/(\d+)$/', $path, $matches)) {
            $response = PersonManagement::edit($content, (int) $matches[1]);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/person\/(\d+)$/', $path, $matches)) {
            $response = PersonManagement::delete((int) $matches[1]);
        }
        // MOVIES
        elseif ($method === 'GET' && $path === '/movie') {
            $response = MovieManagement::index($content);
        }
        elseif ($method === 'GET' && preg_match('/^\/movie\/(\d+)$/', $path, $matches)) {
            $response = MovieManagement::find((int) $matches[1]);
        }
        elseif ($method === 'POST' && $path === '/movie') {
            $response = MovieManagement::add($content);
        }
        elseif ($method === 'PUT' && preg_match('/^\/movie\/(\d+)$/', $path, $matches)) {
            $response = MovieManagement::edit($content, (int) $matches[1]);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/movie\/(\d+)$/', $path, $matches)) {
            $response = MovieManagement::delete((int) $matches[1]);
        }
        elseif ($method === 'POST' && preg_match('/^\/movie\/(\d+)\/country$/', $path, $matches)) {
            $response = MovieManagement::addCountries($content, (int) $matches[1]);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/movie\/(\d+)\/country$/', $path, $matches)) {
            $response = MovieManagement::deleteCountries($content, (int) $matches[1]);
        }
        elseif ($method === 'POST' && preg_match('/^\/movie\/(\d+)\/role$/', $path, $matches)) {
            $response = MovieManagement::addRoles($content, (int) $matches[1]);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/movie\/(\d+)\/role$/', $path, $matches)) {
            $response = MovieManagement::deleteRoles($content, (int) $matches[1]);
        }
        elseif ($method === 'POST' && preg_match('/^\/movie\/(\d+)\/genre$/', $path, $matches)) {
            $response = MovieManagement::addGenres($content, (int) $matches[1]);
        }
        elseif ($method === 'DELETE' && preg_match('/^\/movie\/(\d+)\/genre$/', $path, $matches)) {
            $response = MovieManagement::deleteGenres($content, (int) $matches[1]);
        }
        // NO ROUTE FOUND
        else {
            throw new Exception("Route '$path' with '$method' not found.");
        }

        header('Content-Type: application/json');
        echo json_encode($response);
    }
    catch (JsonException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
    catch (AccessDeniedException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
    catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
}