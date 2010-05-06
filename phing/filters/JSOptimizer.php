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

        // set up some variables for str_replace()
        $needles = array('/', '.js');
        $replace = array('.', '');

        // get the final filename
        $bundlefile = str_replace($needles, $replace, join('-', $files));

        // build the combined, minified output file
        // the latest mod time of the set is in output file name
        try
        {
            $this->_phing->log("Building {$this->_toDir}/$bundlefile.js", Project::MSG_VERBOSE);

            $latest = $this->_build($bundlefile, $files);
        }
        catch (Exception $e)
        {
            throw new BuildException($e->getMessage());
        }

        // output the static JS tag
        $twoBitValue = 0x3 & crc32("$bundlefile-$latest.js");
        $host = str_replace('?', $twoBitValue, $this->_host);
        $path = "http://$host/$bundlefile-$latest.js";
        $output = "<script src=\"$path\" type=\"text/javascript\"></script>\n";
        return $output;
    }

    private function _build($bundlefile, $files)
    {
        static $alreadyLoaded = array();

        $buffer = '';
        $latest = 0;

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

            $mtime = filemtime("{$this->_webRoot}/$file");
            if ($mtime > $latest)
            {
                // get the latest modification time
                $latest = $mtime;
            }

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
        
        if (($handle = fopen("{$this->_toDir}/$bundlefile-$latest.js", 'w')) === false)
        {
            throw new Exception("Unable to create {$this->_toDir}/$bundlefile-$latest.js");
        }

        if (fwrite($handle, $buffer) === false)
        {
            fclose($handle);
            throw new Exception("Unable to write to {$this->_toDir}/$bundlefile-$latest.js");
        }

        fclose($handle);

        return $latest;
    }

    private function _minify($file)
    {
        $this->_phing->log("Attempting to minify $file", Project::MSG_VERBOSE);

        $tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
        $tmpFile = tempnam($tmp, "yui");

        $command = "java -jar " . $this->_yuiPath . " --type js -o $tmpFile $file";
        exec($command, $dummy, $status);

        if ($status === 0)
        {
            $buffer = file_get_contents($tmpFile);
        }
        else
        {
            // if error, return original file
            $buffer = file_get_contents($file);
        }

        @unlink($tmpFile);
        return $buffer;
    }
}

?>
