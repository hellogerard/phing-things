<?php
/*
 *  Inputs one or more CSS files and returns a link to 
 *  a single CSS file that is:
 *  - combined
 *  - compressed
 *  - minified
 *  - with a timestamp-based name
 */


class CSSOptimizer
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

        $dom = new DOMDocument();
        $dom->loadHTML($buffer);
        $html = simplexml_import_dom($dom);

        // gather file names -
        // get URL's for each CSS file in each CSS tag of the HTML block
        $files = array();
        foreach ($html->head->link as $link)
        {
            // filter out things like favicon
            if ((string) $link['rel'] == 'stylesheet')
            {
                $file = trim((string) $link['href']);
                $files[] = trim($file, '/');
            }
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

        // output the static CSS tag
        $twoBitValue = 0x3 & crc32("$outfile");
        $host = str_replace('?', $twoBitValue, $this->_host);
        $path = "http://$host/$outfile";
        $output = "<link href=\"$path\" rel=\"stylesheet\" type=\"text/css\" />\n";
        return $output;
    }

    private function _getName($files)
    {
        // set up some variables for str_replace()
        $needles = array('/', '.css');
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

        return "$bundlefile-$latest.css";
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

            $buffer .= $this->_minify("{$this->_webRoot}/$file") . "\n";
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

        $command = "java -jar " . $this->_yuiPath . " --type css -o $tmpFile $file";
        exec($command, $dummy, $status);

        if ($status === 0)
        {
            $newsize = filesize($tmpFile);
            $buffer = file_get_contents($tmpFile);
            $success = true;
        }
        else
        {
            // if error, return original file
            $newsize = $oldsize;
            $buffer = file_get_contents($file);
        }

        @unlink($tmpFile);
        $pct = round(($newsize / $oldsize) * 100, 2);

        if ($success)
        {
            $this->_phing->log("Minified $file ($newsize/$oldsize bytes or {$pct}%)", Project::MSG_VERBOSE);
        }
        else
        {
            $this->_phing->log("Skipped $file ($newsize/$oldsize bytes or {$pct}%)", Project::MSG_VERBOSE);
        }

        return $buffer;
    }
}

?>
