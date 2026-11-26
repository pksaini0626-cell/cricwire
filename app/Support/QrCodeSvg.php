<?php

namespace App\Support;

/**
 * Pure-PHP ISO/IEC 18004 QR Code SVG Generator (Byte Mode, Version 1-10, ECC Level L).
 * Zero external dependencies, deterministic, and scannable by standard phone cameras.
 */
class QrCodeSvg
{
    // Total data codewords & EC codewords per block for Versions 1..10 at ECC Level L
    private const VERSION_SPECS_L = [
        1  => ['total' => 26,  'data' => 19,  'ec_per_block' => 7,  'blocks' => [1 => 19]],
        2  => ['total' => 44,  'data' => 34,  'ec_per_block' => 10, 'blocks' => [1 => 34]],
        3  => ['total' => 70,  'data' => 55,  'ec_per_block' => 15, 'blocks' => [1 => 55]],
        4  => ['total' => 100, 'data' => 80,  'ec_per_block' => 20, 'blocks' => [1 => 80]],
        5  => ['total' => 134, 'data' => 108, 'ec_per_block' => 26, 'blocks' => [1 => 108]],
        6  => ['total' => 172, 'data' => 136, 'ec_per_block' => 18, 'blocks' => [2 => 68]],
        7  => ['total' => 196, 'data' => 156, 'ec_per_block' => 20, 'blocks' => [2 => 78]],
        8  => ['total' => 242, 'data' => 194, 'ec_per_block' => 24, 'blocks' => [2 => 97]],
        9  => ['total' => 292, 'data' => 232, 'ec_per_block' => 30, 'blocks' => [2 => 116]],
        10 => ['total' => 346, 'data' => 274, 'ec_per_block' => 18, 'blocks' => [2 => 68, 200 => 69]],
    ];

    private const ALIGNMENT_POSITIONS = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    // Precomputed format strings for ECC L (01) and masks 0..7
    private const FORMAT_BITS_L = [
        0 => 0x77C4,
        1 => 0x72F3,
        2 => 0x7DAA,
        3 => 0x789D,
        4 => 0x662F,
        5 => 0x6318,
        6 => 0x6C41,
        7 => 0x6976,
    ];

    // Precomputed version info bits for versions 7..10
    private const VERSION_BITS = [
        7 => 0x07C94,
        8 => 0x085BC,
        9 => 0x09A99,
        10 => 0x0A4D3,
    ];

    /**
     * Render a crisp SVG QR code for the given payload.
     */
    public static function render(string $payload, int $size = 200, string $fg = '#020617', string $bg = '#ffffff'): string
    {
        $bytes = array_values(unpack('C*', $payload) ?: []);
        $len = count($bytes);

        $version = self::chooseVersion($len);
        $spec = self::VERSION_SPECS_L[$version];
        $dim = $version * 4 + 17;

        $codewords = self::encodeData($bytes, $version, $spec);
        $stream = self::interleaveWithEcc($codewords, $spec);

        $matrix = array_fill(0, $dim, array_fill(0, $dim, null));
        $reserved = array_fill(0, $dim, array_fill(0, $dim, false));

        self::placeFunctionPatterns($matrix, $reserved, $version, $dim);
        self::placeDataBits($matrix, $reserved, $stream, $dim);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestMatrix = $matrix;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $reserved, $mask, $dim);
            self::placeFormatInfo($candidate, $mask, $dim);
            $penalty = self::evaluatePenalty($candidate, $dim);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestMatrix = $candidate;
            }
        }

        $quiet = 2;
        $totalModules = $dim + ($quiet * 2);

        $paths = [];
        for ($r = 0; $r < $dim; $r++) {
            for ($c = 0; $c < $dim; $c++) {
                if ($bestMatrix[$r][$c] === 1) {
                    $x = $c + $quiet;
                    $y = $r + $quiet;
                    $paths[] = "M{$x} {$y}h1v1h-1z";
                }
            }
        }

        $d = implode('', $paths);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" shape-rendering="crispEdges" role="img" aria-label="Player QR Code"><rect width="%1$d" height="%1$d" fill="%3$s"/><path d="%4$s" fill="%5$s"/></svg>',
            $totalModules,
            $size,
            htmlspecialchars($bg, ENT_QUOTES, 'UTF-8'),
            $d,
            htmlspecialchars($fg, ENT_QUOTES, 'UTF-8')
        );
    }

    private static function chooseVersion(int $byteLen): int
    {
        foreach (self::VERSION_SPECS_L as $ver => $spec) {
            $headerBits = $ver >= 10 ? 20 : 12;
            $neededBytes = (int) ceil(($headerBits + ($byteLen * 8)) / 8);
            if ($neededBytes <= $spec['data']) {
                return $ver;
            }
        }

        return 10;
    }

    /**
     * @param  array<int, int>  $bytes
     * @return array<int, int>
     */
    private static function encodeData(array $bytes, int $version, array $spec): array
    {
        $bits = [];
        $pushBits = static function (int $val, int $count) use (&$bits): void {
            for ($i = $count - 1; $i >= 0; $i--) {
                $bits[] = ($val >> $i) & 1;
            }
        };

        // Byte mode indicator (0100)
        $pushBits(0b0100, 4);

        // Character count
        $countBits = $version >= 10 ? 16 : 8;
        $maxBytes = $spec['data'] - ($version >= 10 ? 3 : 2);
        $slice = array_slice($bytes, 0, max(0, $maxBytes));
        $pushBits(count($slice), $countBits);

        foreach ($slice as $b) {
            $pushBits($b, 8);
        }

        $capacityBits = $spec['data'] * 8;
        $terminator = min(4, $capacityBits - count($bits));
        for ($i = 0; $i < $terminator; $i++) {
            $bits[] = 0;
        }

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $codewords[] = $byte;
        }

        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (count($codewords) < $spec['data']) {
            $codewords[] = $padBytes[$padIdx % 2];
            $padIdx++;
        }

        return $codewords;
    }

    /**
     * @param  array<int, int>  $dataCodewords
     * @return array<int, int>
     */
    private static function interleaveWithEcc(array $dataCodewords, array $spec): array
    {
        $ecLen = $spec['ec_per_block'];
        $blockDefs = [];
        if (isset($spec['blocks'][1])) {
            $blockDefs = array_fill(0, 1, $spec['blocks'][1]);
        } elseif (isset($spec['blocks'][200])) {
            // Version 10-L: 2 blocks of 68, 2 blocks of 69
            $blockDefs = [68, 68, 69, 69];
        } else {
            $blockDefs = array_fill(0, 2, $spec['blocks'][2]);
        }

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;

        foreach ($blockDefs as $len) {
            $block = array_slice($dataCodewords, $offset, $len);
            $offset += $len;
            $dataBlocks[] = $block;
            $ecBlocks[] = self::reedSolomon($block, $ecLen);
        }

        $result = [];
        $maxDataLen = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $ec) {
                $result[] = $ec[$i];
            }
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $data
     * @return array<int, int>
     */
    private static function reedSolomon(array $data, int $ecLen): array
    {
        // Build GF(256) exp/log tables using primitive polynomial 0x11D
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }

        $gfMul = static function (int $a, int $b) use ($exp, $log): int {
            if ($a === 0 || $b === 0) {
                return 0;
            }
            return $exp[$log[$a] + $log[$b]];
        };

        // Build generator polynomial
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            $factor = $exp[$i];
            for ($j = 0; $j < count($gen); $j++) {
                $next[$j] ^= $gen[$j];
                $next[$j + 1] ^= $gfMul($gen[$j], $factor);
            }
            $gen = $next;
        }

        $rem = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $rem[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $rem[$i + $j] ^= $gfMul($gen[$j], $coef);
                }
            }
        }

        return array_slice($rem, count($data));
    }

    private static function placeFunctionPatterns(array &$matrix, array &$reserved, int $version, int $dim): void
    {
        // Finder patterns + separators
        self::placeFinder($matrix, $reserved, 0, 0, $dim);
        self::placeFinder($matrix, $reserved, 0, $dim - 7, $dim);
        self::placeFinder($matrix, $reserved, $dim - 7, 0, $dim);

        // Alignment patterns
        $centers = self::ALIGNMENT_POSITIONS[$version];
        foreach ($centers as $r) {
            foreach ($centers as $c) {
                if (($r === 6 && $c === 6) || ($r === 6 && $c === $dim - 7) || ($r === $dim - 7 && $c === 6)) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $val = (max(abs($dr), abs($dc)) === 2 || ($dr === 0 && $dc === 0)) ? 1 : 0;
                        $matrix[$r + $dr][$c + $dc] = $val;
                        $reserved[$r + $dr][$c + $dc] = true;
                    }
                }
            }
        }

        // Timing patterns
        for ($i = 8; $i < $dim - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $bit;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $bit;
                $reserved[$i][6] = true;
            }
        }

        // Dark module
        $matrix[$dim - 8][8] = 1;
        $reserved[$dim - 8][8] = true;

        // Reserve format info areas
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $reserved[8][$i] = true;
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$dim - 1 - $i] = true;
            if ($i < 7) {
                $reserved[$dim - 1 - $i][8] = true;
            }
        }

        // Version info for versions >= 7
        if ($version >= 7 && isset(self::VERSION_BITS[$version])) {
            $vBits = self::VERSION_BITS[$version];
            for ($i = 0; $i < 18; $i++) {
                $bit = ($vBits >> $i) & 1;
                $r = intdiv($i, 3);
                $c = $dim - 11 + ($i % 3);
                $matrix[$r][$c] = $bit;
                $reserved[$r][$c] = true;
                $matrix[$c][$r] = $bit;
                $reserved[$c][$r] = true;
            }
        }
    }

    private static function placeFinder(array &$matrix, array &$reserved, int $top, int $left, int $dim): void
    {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $top + $r;
                $cc = $left + $c;
                if ($rr < 0 || $rr >= $dim || $cc < 0 || $cc >= $dim) {
                    continue;
                }

                if ($r === -1 || $r === 7 || $c === -1 || $c === 7) {
                    $val = 0;
                } elseif ($r === 0 || $r === 6 || $c === 0 || $c === 6) {
                    $val = 1;
                } elseif ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4) {
                    $val = 1;
                } else {
                    $val = 0;
                }

                $matrix[$rr][$cc] = $val;
                $reserved[$rr][$cc] = true;
            }
        }
    }

    private static function placeDataBits(array &$matrix, array $reserved, array $stream, int $dim): void
    {
        $totalBits = count($stream) * 8;
        $bitIdx = 0;
        $upward = true;

        for ($col = $dim - 1; $col >= 1; $col -= 2) {
            if ($col === 6) {
                $col--;
            }

            for ($step = 0; $step < $dim; $step++) {
                $row = $upward ? ($dim - 1 - $step) : $step;
                for ($offset = 0; $offset < 2; $offset++) {
                    $c = $col - $offset;
                    if ($reserved[$row][$c]) {
                        continue;
                    }
                    $bit = 0;
                    if ($bitIdx < $totalBits) {
                        $byte = $stream[intdiv($bitIdx, 8)];
                        $bit = ($byte >> (7 - ($bitIdx % 8))) & 1;
                        $bitIdx++;
                    }
                    $matrix[$row][$c] = $bit;
                }
            }
            $upward = !$upward;
        }
    }

    private static function applyMask(array $matrix, array $reserved, int $mask, int $dim): array
    {
        $out = $matrix;
        for ($r = 0; $r < $dim; $r++) {
            for ($c = 0; $c < $dim; $c++) {
                if ($reserved[$r][$c]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => (($r + $c) % 2) === 0,
                    1 => ($r % 2) === 0,
                    2 => ($c % 3) === 0,
                    3 => (($r + $c) % 3) === 0,
                    4 => ((intdiv($r, 2) + intdiv($c, 3)) % 2) === 0,
                    5 => ((($r * $c) % 2) + (($r * $c) % 3)) === 0,
                    6 => (((($r * $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                    default => false,
                };
                if ($invert) {
                    $out[$r][$c] = $out[$r][$c] ? 0 : 1;
                }
            }
        }

        return $out;
    }

    private static function placeFormatInfo(array &$matrix, int $mask, int $dim): void
    {
        $fmt = self::FORMAT_BITS_L[$mask];

        for ($i = 0; $i < 6; $i++) {
            $bit = ($fmt >> $i) & 1;
            $matrix[8][$i] = $bit;
            $matrix[$dim - 1 - $i][8] = $bit;
        }
        $matrix[8][7] = ($fmt >> 6) & 1;
        $matrix[$dim - 7][8] = ($fmt >> 6) & 1;
        $matrix[8][8] = ($fmt >> 7) & 1;
        $matrix[8][$dim - 8] = ($fmt >> 7) & 1;
        $matrix[7][8] = ($fmt >> 8) & 1;
        $matrix[8][$dim - 7] = ($fmt >> 8) & 1;

        for ($i = 9; $i < 15; $i++) {
            $bit = ($fmt >> $i) & 1;
            $matrix[14 - $i][8] = $bit;
            $matrix[8][$dim - 15 + $i] = $bit;
        }
    }

    private static function evaluatePenalty(array $matrix, int $dim): int
    {
        $penalty = 0;

        // Rule 1: 5+ consecutive modules in row or column
        for ($r = 0; $r < $dim; $r++) {
            $runRow = 1;
            $runCol = 1;
            for ($c = 1; $c < $dim; $c++) {
                if ($matrix[$r][$c] === $matrix[$r][$c - 1]) {
                    $runRow++;
                } else {
                    if ($runRow >= 5) {
                        $penalty += 3 + ($runRow - 5);
                    }
                    $runRow = 1;
                }

                if ($matrix[$c][$r] === $matrix[$c - 1][$r]) {
                    $runCol++;
                } else {
                    if ($runCol >= 5) {
                        $penalty += 3 + ($runCol - 5);
                    }
                    $runCol = 1;
                }
            }
            if ($runRow >= 5) {
                $penalty += 3 + ($runRow - 5);
            }
            if ($runCol >= 5) {
                $penalty += 3 + ($runCol - 5);
            }
        }

        // Rule 2: 2x2 blocks
        for ($r = 0; $r < $dim - 1; $r++) {
            for ($c = 0; $c < $dim - 1; $c++) {
                $v = $matrix[$r][$c];
                if ($v === $matrix[$r][$c + 1] && $v === $matrix[$r + 1][$c] && $v === $matrix[$r + 1][$c + 1]) {
                    $penalty += 3;
                }
            }
        }

        return $penalty;
    }
}
