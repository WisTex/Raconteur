<?php

namespace Code\Storage;

Class Stdio {

    /**
     * @brief Pipes $infile to $outfile in $bufsize chunks
     *
     * @return int bytes written | false
     */
    static public function fpipe($infile, $outfile, $bufsize = 65535)
    {
        $size = false;
        $in = fopen($infile, 'rb');
        $out = fopen('php://output', 'wb');
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