<?php
/*
 *  Inputs one or more JS files and returns a link to 
 *  a single JS file that is:
 *  - combined
 *  - compressed
 *  - minified
 *  - with a timestamp-based name
 */


class JSOptimizer
{
    private $_host;
    private $_yuiPath;
    private $_webRoot;
    private $_toDir;
    private $_phing;

    public function __construct($host, $yuiPath, $webRoot, $toDir, $phing)
    {
        $this->_host = $host;
        $this->_yuiPath = $yuiPath;
        $this->_webRoot = $webRoot;
        $this->_toDir = $toDir;
        $this->_phing = $phing;
    }

    public function optimize($buffer)
    {
        $output = '';

        // matched buffer comes in from preg_replace_callback() as array
        $buffer = $buffer[0];

        // get URL's for each JS files in each JS tag
        preg_match_all('/src="([^"]*)"/', $buffer, $urls);
        $files = array();
        foreach ($urls[1] as $url)
        {
            $files[] = trim(trim($url), '/');
        }

        // build the combined, minified output file
        // the latest mod time of the set is in output file name
        try
        {
            $outfile = $this->_getName($files);

            $this->_phing->log("Building {$this->_toDir}/$outfile");

            $this->_build($outfile, $files);
        }
        catch (Exception $e)
        {
            throw new BuildException($e->getMessage());
        }

        // output the static JS tag
        $twoBitValue = 0x3 & crc32("$outfile");
        $host = str_replace('?', $twoBitValue, $this->_host);
        $path = "http://$host/$outfile";
        $output = "<script src=\"$path\" type=\"text/javascript\"></script>\n";
        return $output;
    }

    private function _getName($files)
    {
        // set up some variables for str_replace()
        $needles = array('/', '.js');
        $replace = array('.', '');

        // get the final filename
        $bundlefile = str_replace($needles, $replace, join('-', $files));

        $latest = 0;

        foreach ($files as $file)
        {
            $file = trim($file, '/');

            $mtime = filemtime("{$this->_webRoot}/$file");
            if ($mtime > $latest)
            {
                // get the latest modification time
                $latest = $mtime;
            }
        }

        return "$bundlefile-$latest.js";
    }

    private function _build($outfile, $files)
    {
        static $alreadyLoaded = array();

        $buffer = '';

        foreach ($files as $file)
        {
            if (isset($alreadyLoaded[$file]))
            {
                continue;
            }
            else
            {
                $alreadyLoaded[$file] = true;
            }

            $file = trim($file, '/');

            // skip files that appear to be minified or packed already
            if (strpos($file, '.min.') !== false || strpos($file, '.pack.') !== false)
            {
                $buffer .= file_get_contents("{$this->_webRoot}/$file") . "\n";
            }
            else
            {
                $buffer .= $this->_minify("{$this->_webRoot}/$file") . "\n";
            }
        }

        // open the output file
        if (! file_exists($this->_toDir) && ! mkdir($this->_toDir, 0755, true))
        {
            throw new Exception("Unable to create {$this->_toDir}");
        }
        
        if (($handle = fopen("{$this->_toDir}/$outfile", 'w')) === false)
        {
            throw new Exception("Unable to create {$this->_toDir}/$outfile");
        }

        if (fwrite($handle, $buffer) === false)
        {
            fclose($handle);
            throw new Exception("Unable to write to {$this->_toDir}/$outfile");
        }

        fclose($handle);
    }

    private function _minify($file)
    {
        $success = false;
        clearstatcache();
        $oldsize = filesize($file);

        $tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
        $tmpFile = tempnam($tmp, "yui");

        $command = "java -jar " . $this->_yuiPath . " --type js -o $tmpFile $file";
        exec($command, $dummy, $status);

        $newsize = filesize($tmpFile);

        if ($status === 0)
        {
            $buffer = file_get_contents($tmpFile);
            $success = true;
        }
        else
        {
            // if error, return original file
            $buffer = file_get_contents($file);
        }

        @unlink($tmpFile);

        if ($success)
        {
            $pct = round(($newsize / $oldsize) * 100, 2);
            $this->_phing->log("Minified $file ($newsize/$oldsize bytes or {$pct}%)", Project::MSG_VERBOSE);
        }
        else
        {
            $this->_phing->log("Skipped $file", Project::MSG_VERBOSE);
        }

        return $buffer;
    }
}

?>
