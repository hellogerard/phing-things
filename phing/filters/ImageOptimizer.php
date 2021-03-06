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
    private $_type;

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
        $buffer = $buffer[0];

        // get URL's for each image files in each image tag
        // assume it's a CSS first, then assume it's image tag

        preg_match_all("/url\('?(.*)'?\)/", $buffer, $urls);
        $this->_type = 'css';
        if (empty($urls[1][0]))
        {
            preg_match_all('/src="([^"]*)"/', $buffer, $urls);
            $this->_type = 'img';
        }

        $infile = $urls[1][0];
        $infile = trim(trim($infile), '/');

        // build the optimized output file
        // the latest mod time of the image is in output file name
        try
        {
            $outfile = $this->_build($infile);
        }
        catch (Exception $e)
        {
            throw new BuildException($e->getMessage());
        }

        // output the static image tag
        $twoBitValue = 0x3 & crc32($outfile);
        $host = str_replace('?', $twoBitValue, $this->_host);
        $path = "http://$host/$outfile";

        $output = "url($path)";
        if ($this->_type == 'img')
        {
            $output = "src=\"$path\"";
        }

        return $output;
    }

    private function _build($infile)
    {
        if (! file_exists($this->_toDir) && ! mkdir($this->_toDir, 0755, true))
        {
            throw new Exception("Unable to create {$this->_toDir}");
        }

        // get the last modified time
        $mtime = filemtime("{$this->_webRoot}/$infile");

        // set up some variables for str_replace()
        $needles = array('/', '.gif', '.png', '.jpg');
        $replace = array('.', "-$mtime.gif", "-$mtime.png", "-$mtime.jpg");

        // get the final filename
        $outfile = str_replace($needles, $replace, $infile);

        // copy the output file
        if (! copy("{$this->_webRoot}/$infile", "{$this->_toDir}/$outfile"))
        {
            throw new Exception("Unable to create {$this->_toDir}/$outfile");
        }

        $outfile = $this->_optimize("{$this->_toDir}/$outfile");

        return $outfile;
    }

    private function _optimize($file)
    {
        $type = exif_imagetype($file);
        $return = $file;
        $oldsize = $newsize = 0;
        $success = false;

        switch ($type)
        {
            case IMAGETYPE_GIF:

                // convert GIFs to PNG
                clearstatcache();
                $oldsize = filesize($file);

                $pngfile = str_replace('.gif', '.png', $file);
                $cmd = "optipng \"$file\"";
                exec($cmd, $dummy, $status);

                if ($status === 0)
                {
                    $newsize = filesize($pngfile);

                    if ($newsize < $oldsize)
                    {
                        $return = $pngfile;
                        @unlink($file);
                        $success = true;
                    }
                    else
                    {
                        @unlink($pngfile);
                    }
                }
                else
                {
                    $newsize = $oldsize;
                    $this->_phing->log("optipng not found in path.", Project::MSG_WARN);
                }

                break;

            case IMAGETYPE_JPEG:

                clearstatcache();
                $oldsize = filesize($file);

                $tmpfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($file);
                exec("jpegtran -optimize -outfile \"$tmpfile\" \"$file\"", $dummy, $status);

                if ($status === 0)
                {
                    $newsize = filesize($tmpfile);

                    if ($newsize < $oldsize)
                    {
                        @rename($tmpfile, $file);
                        $success = true;
                    }
                }
                else
                {
                    $newsize = $oldsize;
                    $this->_phing->log("jpegtran not found in path.", Project::MSG_WARN);
                }

                break;

            case IMAGETYPE_PNG:

                clearstatcache();
                $oldsize = filesize($file);

                $cmd = "optipng \"$file\"";
                exec($cmd, $dummy, $status);

                if ($status === 0)
                {
                    $newsize = filesize($file);

                    if ($newsize < $oldsize)
                    {
                        $success = true;
                    }
                }
                else
                {
                    $newsize = $oldsize;
                    $this->_phing->log("optipng not found in path.", Project::MSG_WARN);
                }

                break;

            default:
                break;
        }

        $pct = round(($newsize / $oldsize) * 100, 2);

        if ($success)
        {
            $this->_phing->log("Optimized $file ($newsize/$oldsize bytes or {$pct}%)", Project::MSG_VERBOSE);
        }
        else
        {
            $this->_phing->log("Skipped $file ($newsize/$oldsize bytes or {$pct}%)", Project::MSG_VERBOSE);
        }

        return basename($return);
    }
}

?>
