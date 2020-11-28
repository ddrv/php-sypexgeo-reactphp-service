<?php

declare(strict_types=1);

namespace App\Database;

use App\Database\Exception\InvalidDatabaseFormat;

/**
 * Based on https://sypexgeo.net
 */
class Reader {

    /**
     * @var array
     */
    private $about;

    /**
     * @var int
     */
    private $range;

    /**
     * @var array
     */
    private $bIdx;

    /**
     * @var int
     */
    private $bIdxLen;

    /**
     * @var array
     */
    private $mIdx;

    /**
     * @var int
     */
    private $mIdxLen;

    /**
     * @var int
     */
    private $idLen;

    /**
     * @var int
     */
    private $blockLen;

    /**
     * @var int
     */
    private $maxCity;

    /**
     * @var int
     */
    private $maxRegion;

    /**
     * @var int
     */
    private $maxCountry;

    /**
     * @var int
     */
    private $dbItems;

    /**
     * @var int
     */
    private $countrySize;

    /**
     * @var string
     */
    private $pack;

    /**
     * @var string
     */
    private $db;

    /**
     * @var string
     */
    private $regionsDb;

    /**
     * @var string
     */
    private $citiesDb;

    /**
     * @var string[]
     */
    public $id2iso = [
        '', 'AP', 'EU', 'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'CW', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU',
        'AW', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BM', 'BN', 'BO', 'BR', 'BS',
        'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
        'CO', 'CR', 'CU', 'CV', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG',
        'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'SX', 'GA', 'GB', 'GD', 'GE', 'GF',
        'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN',
        'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JM', 'JO', 'JP', 'KE',
        'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR',
        'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP',
        'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI',
        'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN',
        'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG',
        'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'ST', 'SV', 'SY', 'SZ', 'TC', 'TD', 'TF',
        'TG', 'TH', 'TJ', 'TK', 'TM', 'TN', 'TO', 'TL', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM',
        'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'RS', 'ZA',
        'ZM', 'ME', 'ZW', 'A1', 'XK', 'O1', 'AX', 'GG', 'IM', 'JE', 'BL', 'MF', 'BQ', 'SS'
    ];

    /**
     * @param string $database
     * @throws InvalidDatabaseFormat
     */
    public function __construct(string $database)
    {
        $this->refresh($database);
    }

    /**
     * @param string $database
     * @throws InvalidDatabaseFormat
     */
    public function refresh(string $database): void
    {
        $offset = 0;
        $header = substr($database, $offset, 40);
        $offset += 40;

        if (substr($header, 0, 3) != 'SxG') {
            throw new InvalidDatabaseFormat();
        }
        $format = 'Cver/'
            . 'Ntime/'
            . 'Ctype/'
            . 'Ccharset/'
            . 'CbIdx/'
            . 'nmIdx/'
            . 'nrange/'
            . 'NdbItems/'
            . 'CidLen/'
            . 'nmaxRegion/'
            . 'nmaxCity/'
            . 'NregionSize/'
            . 'NcitySize/'
            . 'nmaxCountry/'
            . 'NcountrySize/'
            . 'npackSize';
        $info = unpack($format, substr($header, 3));

        $check = $info['bIdx'] * $info['mIdx'] * $info['range'] * $info['dbItems'] * $info['time'] * $info['idLen'];
        if ($check == 0) {
            throw new InvalidDatabaseFormat();
        }
        $this->range       = $info['range'];
        $this->bIdxLen   = $info['bIdx'];
        $this->mIdxLen   = $info['mIdx'];
        $this->dbItems    = $info['dbItems'];
        $this->idLen      = $info['idLen'];
        $this->blockLen   = 3 + $this->idLen;
        $this->maxCity    = $info['maxCity'];
        $this->maxRegion  = $info['maxRegion'];
        $this->maxCountry = $info['maxCountry'];
        $this->countrySize= $info['countrySize'];
        $this->pack        = $info['packSize'] ? explode("\0", substr($database, $offset, $info['packSize'])) : '';
        $offset += $info['packSize'];

        $size = $info['bIdx'] * 4;
        $this->bIdx = array_values(unpack("N*", substr($database, $offset, $size)));
        $offset += $size;

        $size = $info['mIdx'] * 4;
        $this->mIdx = str_split(substr($database, $offset, $size), 4);
        $offset += $size;

        $size = $this->dbItems * $this->blockLen;
        $this->db  = substr($database, $offset, $size);
        $offset += $size;

        $this->regionsDb = '';
        if ($info['regionSize'] > 0) {
            $this->regionsDb = substr($database, $offset, $info['regionSize']);
            $offset += $info['regionSize'];
        }

        $this->citiesDb = '';
        if ($info['citySize'] > 0) {
            $this->citiesDb = substr($database, $offset, $info['citySize']);
            $offset += $info['citySize'];
        }

        $charsets = [
            'utf-8',
            'latin1',
            'cp1251',
        ];
        $types   = [
            'n/a',
            'SxGeo Country',
            'SxGeo City RU',
            'SxGeo City EN',
            'SxGeo City',
            'SxGeo City Max RU',
            'SxGeo City Max EN',
            'SxGeo City Max',
        ];

        $this->about = [
            'created' => date('Y.m.d', $info['time']),
            'timestamp' => $info['time'],
            'charset' => $charsets[$info['charset']],
            'type' => $types[$info['type']],
            'byte_index' => $this->bIdxLen,
            'main_index' => $this->mIdxLen,
            'blocks_in_index_item' => $this->range,
            'ip_blocks' => $this->dbItems,
            'block_size' => $this->blockLen,
            'city' => [
                'max_length' => $this->maxCity,
                'total_size' => $info['citySize'],
            ],
            'region' => [
                'max_length' => $this->maxRegion,
                'total_size' => $info['regionSize'],
            ],
            'country' => [
                'max_length' => $this->maxCountry,
                'total_size' => $info['countrySize'],
            ],
        ];
        unset($offset);
    }

    private function searchIndex(string $ipn, int $min, int $max): int
    {
        while ($max - $min > 8) {
            $offset = ($min + $max) >> 1;
            if ($ipn > $this->mIdx[$offset]) $min = $offset;
            else $max = $offset;
        }

        /** @noinspection PhpStatementHasEmptyBodyInspection */
        while ($ipn > $this->mIdx[$min] && $min++ < $max) {}
        return  (int)$min;
    }

    private function searchDatabase(string $str, string $ipn, int $min, int $max): int
    {
        if ($max - $min > 1) {
            $ipn = substr($ipn, 1);
            while($max - $min > 8) {
                $offset = ($min + $max) >> 1;
                if ($ipn > substr($str, $offset * $this->blockLen, 3)) {
                    $min = $offset;
                } else {
                    $max = $offset;
                }
            }
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            while ($ipn >= substr($str, $min * $this->blockLen, 3) && ++$min < $max) {}
        } else {
            $min++;
        }
        return hexdec(bin2hex(substr($str, $min * $this->blockLen - $this->idLen, $this->idLen)));
    }

    private function getOffsetOrCountryId(string $ip): int
    {
        $ip1n = (int)$ip;
        if ($ip1n == 0 || $ip1n == 10 || $ip1n == 127 || $ip1n >= $this->bIdxLen || false === ($ipn = ip2long($ip))) {
            return 0;
        }
        $ipn = pack('N', $ipn);

        $blocks = array('min' => $this->bIdx[$ip1n-1], 'max' => $this->bIdx[$ip1n]);

        if ($blocks['max'] - $blocks['min'] > $this->range)
        {
            $part = $this->searchIndex(
                $ipn,
                (int)floor($blocks['min'] / $this->range),
                (int)floor($blocks['max'] / $this->range) - 1
            );
            $min = $part > 0 ? $part * $this->range : 0;
            $max = $part > $this->mIdxLen ? $this->dbItems : ($part+1) * $this->range;
            if ($min < $blocks['min']) {
                $min = $blocks['min'];
            }
            if ($max > $blocks['max']) {
                $max = $blocks['max'];
            }
        } else {
            $min = $blocks['min'];
            $max = $blocks['max'];
        }
        return $this->searchDatabase($this->db, $ipn, (int)$min, (int)$max);
    }

    private function readData(int $seek, int $max, int $type): array
    {
        $raw = '';
        if($seek && $max) {
            $raw = substr($type == 1 ? $this->regionsDb : $this->citiesDb, $seek, $max);
        }
        return $this->unpack($this->pack[$type], $raw);
    }

    private function parseCity(int $offset, bool $full = false): ?array
    {
        if (!$this->pack) {
            return null;
        }
        $onlyCountry = false;
        if ($offset < $this->countrySize) {
            $country = $this->readData($offset, $this->maxCountry, 0);
            $city = $this->unpack($this->pack[2]);
            $city['lat'] = $country['lat'];
            $city['lon'] = $country['lon'];
            $onlyCountry = true;
        } else {
            $city = $this->readData($offset, $this->maxCity, 2);
            $country = [
                'id' => $city['country_id'],
                'iso' => $this->id2iso[$city['country_id']]
            ];
            unset($city['country_id']);
        }
        if ($full) {
            $region = $this->readData($city['region_seek'], $this->maxRegion, 1);
            if (!$onlyCountry) {
                $country = $this->readData($region['country_seek'], $this->maxCountry, 0);
            }
            unset($city['region_seek']);
            unset($region['country_seek']);
            return [
                'city' => $city,
                'region' => $region,
                'country' => $country,
            ];
        } else {
            unset($city['region_seek']);
            return [
                'city' => $city,
                'country' => [
                    'id' => $country['id'],
                    'iso' => $country['iso']
                ],
            ];
        }
    }

    private function unpack($pack, $item = ''): array
    {
        $unpacked = [];
        $empty = empty($item);
        $pack = explode('/', $pack);
        $pos = 0;
        foreach ($pack as $p) {
            list($type, $name) = explode(':', $p);
            $type0 = $type{0};
            if ($empty) {
                $unpacked[$name] = $type0 == 'b' || $type0 == 'c' ? '' : 0;
                continue;
            }
            switch ($type0) {
                case 't':
                case 'T': $l = 1; break;
                case 's':
                case 'n':
                case 'S': $l = 2; break;
                case 'm':
                case 'M': $l = 3; break;
                case 'd': $l = 8; break;
                case 'c': $l = (int)substr($type, 1); break;
                case 'b': $l = strpos($item, "\0", $pos)-$pos; break;
                default: $l = 4;
            }
            $val = substr($item, $pos, $l);
            $v = null;
            switch ($type0) {
                case 't': $v = unpack('c', $val); break;
                case 'T': $v = unpack('C', $val); break;
                case 's': $v = unpack('s', $val); break;
                case 'S': $v = unpack('S', $val); break;
                case 'm': $v = unpack('l', $val . (ord($val{2}) >> 7 ? "\xff" : "\0")); break;
                case 'M': $v = unpack('L', $val . "\0"); break;
                case 'i': $v = unpack('l', $val); break;
                case 'I': $v = unpack('L', $val); break;
                case 'f': $v = unpack('f', $val); break;
                case 'd': $v = unpack('d', $val); break;

                case 'n': $v = current(unpack('s', $val)) / pow(10, $type{1}); break;
                case 'N': $v = current(unpack('l', $val)) / pow(10, $type{1}); break;

                case 'c': $v = rtrim($val, ' '); break;
                case 'b': $v = $val; $l++; break;
            }
            $pos += $l;
            $unpacked[$name] = is_array($v) ? current($v) : $v;
        }
        return $unpacked;
    }

    public function define(string $ip)
    {
        return $this->maxCity ? $this->defineCity($ip) : $this->defineCountry($ip);
    }

    public function defineCountry(string $ip): string
    {
        if ($this->maxCity) {
            $tmp = $this->parseCity($this->getOffsetOrCountryId($ip));
            return $tmp['country']['iso'];
        }
        return $this->id2iso[$this->getOffsetOrCountryId($ip)];
    }

    public function defineCity(string $ip): ?array
    {
        $seek = $this->getOffsetOrCountryId($ip);
        return $seek ? $this->parseCity($seek, true) : null;
    }

    public function about(): array
    {
        return $this->about;
    }
}
