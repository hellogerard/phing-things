<?php
/*
 *  Inputs a single image tag and returns a link to 
 *  a single image file that is:
 *  - optimized
 *  - with a timestamp-based name
 */


class ImageOptimizer
{
    private $_host;
    private $_webRoot;
    private $_toDir;
    private $_phing;

    public function __construct($host, $webRoot, $toDir, $phing)
    {
        $this->_host = $host;
        $this->_webRoot = $webRoot;
        $this->_toDir = $toDir;
        $this->_phing = $phing;
    }

    public function optimize($buffer)
    {
        $output = '';

        // matched buffer comes in from preg_replace_callback() as array
        $infile = $buffer[0];

        // set up some variables for str_replace()
        $needles = array('/', '.gif');
        $replace = array('.', '.png');

        // get the final filename
        $outfile = str_replace($needles, $replace, $infile);

        // build the optimized output file
        // the latest mod time of the image is in output file name
        try
        {
            $this->_phing->log("Building {$this->_toDir}/$outfile.js", Project::MSG_VERBOSE);

            $latest = $this->_build($outfile, $infile);
        }
        catch (Exception $e)
        {
            throw new BuildException($e->getMessage());
        }

        // output the static image tag
        $twoBitValue = 0x3 & crc32("$outfile-$latest.js");
        $host = str_replace('?', $twoBitValue, $this->_host);
        $path = "http://$host/$outfile-$latest.js";
        return $path;
    }

    private function _build($outfile, $infile)
    {
        $infile = trim($infile, '/');

        $latest = filemtime("{$this->_webRoot}/$infile");

        if (! file_exists($this->_toDir) && ! mkdir($this->_toDir, 0755, true))
        {
            throw new Exception("Unable to create {$this->_toDir}");
        }

        $this->_optimize("{$this->_webRoot}/$infile");

        return $latest;
    }

    private function _optimize($file)
    {
        $type = exif_imagetype($file);

        switch ($type)
        {
            case IMAGETYPE_GIF:

                // convert GIFs to PNG
                break;

            case IMAGETYPE_JPEG:

                $this->_phing->log("Attempting to optimize $file", Project::MSG_VERBOSE);

                clearstatcache();
                $oldsize = filesize($file);

                $tmpfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($file);
                exec("jpegtran -optimize -outfile \"$tmpfile\" \"$file\"", $dummy, $status);

                $newsize = filesize($tmpfile);

                if ($status === 0 && $newsize < $oldsize)
                {
                    @rename($tmpfile, $file);
                }
                else
                {
                    @unlink($tmpfile);
                }

                break;

            case IMAGETYPE_PNG:

                $this->_phing->log("Attempting to optimize $file", Project::MSG_VERBOSE);
                $cmd = "optipng \"$file\"";
                exec($cmd, $dummy, $status);

                if ($status === 0)
                {
                }

                break;

            default:
                break;
        }
    }
}

?>
