<?php namespace tool_stdlogarchiver\util;

class compression_helper {

    private const CHUNK = 524288; // 512 KB

    public static function gzip(string $input, string $output, int $level = 9): void {
        $in  = fopen($input, 'rb');
        $out = gzopen($output, 'wb' . $level);
        try {
            while (!feof($in)) {
                gzwrite($out, fread($in, self::CHUNK));
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    public static function gunzip(string $input, string $output): void {
        $in  = gzopen($input, 'rb');
        $out = fopen($output, 'wb');
        try {
            while (!gzeof($in)) {
                fwrite($out, gzread($in, self::CHUNK));
            }
        } finally {
            gzclose($in);
            fclose($out);
        }
    }
}
