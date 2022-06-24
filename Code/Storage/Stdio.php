<?php

namespace Code\Storage;

use Exception;

Class Stdio {

    static public function mkdir($path, $mode = 0777, $recursive = false) {
        $result = false;
        try {
            $oldumask = umask(0);
            $result = mkdir($path, $mode, $recursive);
        } catch (\Exception $e) {
            // null operation
        } finally {
            umask($oldumask);
        }

        return $result;
    }


    /**
     * @brief Pipes $infile to $outfile in $bufsize chunks
     *
     * @return int bytes written | false
     */
    static public function fcopy($infile, $outfile, $bufsize = 65535, $read_mode = 'rb', $write_mode = 'wb')
    {
        $size = false;
        $in = fopen($infile, $read_mode);
        $out = fopen($outfile, $write_mode);
        if ($in && $out) {
            $size = self::pipe_streams($in, $out, $bufsize);
        }
        fclose($in);
        fclose($out);
        return $size;
    }

    /**
     * @brief Pipes $in to $out in $bufsize (or 64KB) chunks.
     *
     * @param resource $in File pointer of input
     * @param resource $out File pointer of output
     * @param int $bufsize size of chunk, default 65535
     * @return number with the size
     */
    static public function pipe_streams($in, $out, $bufsize = 65535)
    {
        $size = 0;
        while (!feof($in)) {
            $size += fwrite($out, fread($in, $bufsize));
        }
        return $size;
    }

}