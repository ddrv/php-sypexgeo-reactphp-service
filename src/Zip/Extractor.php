<?php

declare(strict_types=1);

namespace App\Zip;

use App\Zip\Exception\IncorrectCDFHSignature;
use App\Zip\Exception\IncorrectEOCDSignature;
use App\Zip\Exception\IncorrectLFHSignature;

class Extractor
{
    /**
     * @var int[]
     */
    private $lengths = ['L' => 4, 'l' => 4, 'i' => 4, 'I' => 4, 'S' => 2, 'a' => 1];

    /**
     * @var string
     */
    private $zipped;

    /**
     * @var array
     */
    private $files = [];

    public function __construct(string $zipped)
    {
        $this->zipped = $zipped;
        $size = strlen($this->zipped);
        $cursor = 0;
        for ($offset = 22, $length = $size; $offset <= $length; $offset++) {
            if ($offset > $size) {
                throw new IncorrectEOCDSignature();
            }
            $cursor = $size - $offset;

            if ("\x50\x4b\x05\x06" === $bytes = substr($this->zipped, $cursor, 4)) {
                break;
            }
        }

        $cursor += 4;
        $formats = [
            'diskNumber'                   => 'S',
            'startDiskNumber'              => 'S',
            'numberCentralDirectoryRecord' => 'S',
            'totalCentralDirectoryRecord'  => 'S',
            'sizeOfCentralDirectory'       => 'L',
            'centralDirectoryOffset'       => 'L',
            'commentLength'                => 'S',
        ];

        $read = $this->unpack($formats, $cursor);
        $EOCD = $read['data'];

        $offset = $EOCD['centralDirectoryOffset'];
        for ($i = 0; $i < $EOCD['numberCentralDirectoryRecord']; $i++) {
            $cursor = $offset;

            if ("\x50\x4b\x01\x02" !== $bytes = substr($this->zipped, $cursor, 4)) {
                throw new IncorrectCDFHSignature();
            }
            $cursor+=4;

            $formats = [
                'versionMadeBy' => 'S',
                'versionToExtract' => 'S',
                'generalPurposeBitFlag' => 'S',
                'compressionMethod' => 'S',
                'modificationTime' => 'S',
                'modificationDate' => 'S',
                'crc32' => 'L',
                'compressedSize' => 'L',
                'uncompressedSize' => 'L',
                'filenameLength' => 'S',
                'extraFieldLength' => 'S',
                'fileCommentLength' => 'S',
                'diskNumber' => 'S',
                'internalFileAttributes' => 'S',
                'externalFileAttributes' => 'L',
                'localFileHeaderOffset' => 'L',
            ];

            $read = $this->unpack($formats, $cursor);
            $cursor = $read['cursor'];
            $CDFH = $read['data'];

            $formats = [
                'filename' => ['a', $CDFH['filenameLength']],
                'extraField' => ['a', $CDFH['extraFieldLength']],
                'fileComment' => ['a', $CDFH['fileCommentLength']],
            ];
            $read = $this->unpack($formats, $cursor);
            $cursor = $read['cursor'];
            $CDFH += $read['data'];

            $offset = $cursor;
            $cursor = $CDFH['localFileHeaderOffset'];


            if ("\x50\x4b\x03\x04" !== $bytes = substr($this->zipped, $cursor, 4)) {
                throw new IncorrectLFHSignature();
            }
            $cursor += 4;

            $formats = [
                'versionToExtract'      => 'S',
                'generalPurposeBitFlag' => 'S',
                'compressionMethod'     => 'S',
                'modificationTime'      => 'S',
                'modificationDate'      => 'S',
                'crc32'                 => 'L',
                'compressedSize'        => 'L',
                'uncompressedSize'      => 'L',
                'filenameLength'        => 'S',
                'extraFieldLength'      => 'S',
            ];

            $read = $this->unpack($formats, $cursor);
            $cursor = $read['cursor'];
            $LFH = $read['data'];

            $formats = [
                'filename'    => ['a', $LFH['filenameLength']],
                'extraField'  => ['a', $LFH['extraFieldLength']],
            ];
            $read = $this->unpack($formats, $cursor);
            $cursor = $read['cursor'];
            $LFH += $read['data'];

            $this->files[$CDFH['filename']]['size'] = $LFH['compressedSize'];
            $this->files[$CDFH['filename']]['offset'] = $cursor;
        }
    }

    public function extract(string $file): ?string
    {
        if (!array_key_exists($file, $this->files)) {
            return null;
        }

        $zipped = substr($this->zipped, $this->files[$file]['offset'], $this->files[$file]['size']);
        return gzinflate($zipped);
    }

    private function unpack(array $def, int $cursor): array
    {
        $length = 0;
        $unpackFormat = [];
        $nullData = [];
        $result = [
            'data' => [],
            'cursor' => $cursor,
        ];

        foreach ($def as $name => $format) {
            $formatLength = 1;

            if (is_array($format)) {
                [$format, $formatLength] = $format;
            }

            if ($formatLength < 1) {
                $nullData[] = $name;
                continue;
            }

            $length += $this->lengths[$format] * $formatLength;
            $unpackFormat[] = $format . (($formatLength > 1) ? $formatLength : '') . $name;
        }
        if ($length > 0) {
            $result['data'] = unpack(implode('/', $unpackFormat), substr($this->zipped, $result['cursor'], $length));
            $result['cursor'] += $length;
        }
        foreach ($nullData as $empty) {
            $result['data'][$empty] = null;
        }
        return $result;
    }
}
