<?php

declare(strict_types=1);

namespace App\Database;

use App\Zip\Extractor;
use DateTime;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use React\Filesystem\FilesystemInterface;
use React\Http\Browser;
use React\Stream\ReadableStreamInterface;
use Throwable;
use function React\Promise\Stream\unwrapWritable;

class Downloader
{

    /**
     * @var FilesystemInterface
     */
    private $fs;

    /**
     * @var Browser
     */
    private $http;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var string
     */
    private $url;
    /**
     * @var string
     */
    private $file;

    /**
     * @var string
     */
    private $lastModified;

    /**
     * @var bool
     */
    private $inProcess = false;

    public function __construct(FilesystemInterface $fs, Browser $http, Reader $reader, string $url, string $file, int $time)
    {
        $this->fs = $fs;
        $this->http = $http;
        $this->reader = $reader;
        $this->url = $url;
        $this->file = $file;
        $tz = new DateTimeZone('GMT');
        $this->lastModified = DateTime::createFromFormat('U', (string)$time, $tz)->format(DateTime::RFC7231);
    }

    public function download()
    {
        if ($this->inProcess) {
            return;
        }
        $this->inProcess = true;
        $headers = [
            'If-Modified-Since' => $this->lastModified,
        ];
        $this->http->requestStreaming('GET', $this->url, $headers)->then(function (ResponseInterface $response) {
            $this->inProcess = false;
            if ($response->getStatusCode() === 304) {
                return;
            }
            if ($response->hasHeader('Last-Modified')) {
                $this->lastModified = $response->getHeaderLine('Last-Modified');
            }

            /** @var ReadableStreamInterface $body */
            $content = '';
            $error = false;
            $body = $response->getBody();
            $tmp = unwrapWritable($this->fs->file($this->file . '.tmp')->open('cwt'));
            $body->pipe($tmp);
            $body->on('data', function ($chunk) use (&$content) {
                $content .= $chunk;
            });

            $body->on('error', function () use (&$error) {
                $error = true;
            });

            $body->on('close', function () use (&$content, &$error) {
                if (!$error) {
                    try {
                        $zip = new Extractor($content);
                        $content = $zip->extract('SxGeoCity.dat');
                        $this->reader->refresh($content);
                    } catch (Throwable $exception) {
                    }
                    $this->fs->file($this->file . '.tmp')->rename($this->file);
                }
            });
        });
    }
}
