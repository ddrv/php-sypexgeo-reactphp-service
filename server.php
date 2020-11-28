<?php

use App\Database\Downloader;
use App\Database\Exception\InvalidDatabaseFormat;
use App\Database\Reader;
use App\Zip\Exception\IncorrectZipFile;
use App\Zip\Extractor;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Filesystem\Filesystem;
use React\Http\Browser;
use React\Http\Message\Response;
use React\Http\Server;
use React\Socket\Server as Socket;

if (DIRECTORY_SEPARATOR === '\\') {
    fwrite(STDERR, 'Non-blocking console I/O not supported on Windows' . PHP_EOL);
    exit(1);
}

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$url = 'https://sypexgeo.net/files/SxGeoCity_utf8.zip';

$db = __DIR__ . '/var/db/sx-geo.zip';
if (!file_exists($db)) {
    echo 'Download database from' . $url . PHP_EOL;
    file_put_contents($db, file_get_contents($url));
    echo 'Done' . PHP_EOL;
}
$contents = file_get_contents($db);
try {
    $zip = new Extractor($contents);
} catch (IncorrectZipFile $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    unlink($db);
    exit($e->getCode());
}

try {
    $reader = new Reader($zip->extract('SxGeoCity.dat'));
} catch (InvalidDatabaseFormat $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    unlink($db);
    exit($e->getCode());
}

$loop = Factory::create();
$client = (new Browser($loop))->withTimeout(false);
$fs = Filesystem::create($loop);

$downloader = new Downloader($fs, $client, $reader, $url, $db, filectime($db) - 1000000);

$loop->addPeriodicTimer(86400, function () use ($downloader) {
    $downloader->download();
});

$server = new Server($loop, function (ServerRequestInterface $request) use ($reader) {
    $headers = [
        'Content-Type' => 'application/json'
    ];
    if (!in_array($request->getMethod(),['GET', 'HEAD'])) {
        $headers['Allowed'] = 'GET,HEAD';
        return new Response(405, $headers, json_encode(['error' => 'method not allowed']));
    }
    $path = $request->getUri()->getPath();
    $code = 200;
    switch ($path) {
        case '/about':
            $body = json_encode($reader->about());
            break;
        case '/define':
            $query = $request->getQueryParams();
            $ip = array_key_exists('ip', $query) ? $query['ip'] : null;
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                $body = json_encode($reader->define($ip));
            } else {
                $code = 400;
                $body = json_encode(['error' => 'ip is invalid']);
            }
            break;
        default:
            $code = 404;
            $body = json_encode(['error' => 'page not found']);
            break;
    }
    $headers['Content-Length'] = strlen($body);
    if ($request->getMethod() === 'HEAD') {
        $body = '';
    }
    return new Response($code, $headers, $body);
});

$socket = new Socket(isset($argv[1]) ? $argv[1] : '0.0.0.0:0', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;
$loop->run();
