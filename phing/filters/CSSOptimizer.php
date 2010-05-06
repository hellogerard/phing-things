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

        // set up some variables for str_replace()
        $needles = array('/', '.css');
        $replace = array('.', '');

        // get the final filename
        $bundlefile = str_replace($needles, $replace, join('-', $files));

        // build the combined, minified output file
        // the latest mod time of the set is in output file name
        try
        {
            $this->_phing->log("Building {$this->_toDir}/$bundlefile.css", Project::MSG_VERBOSE);

            $latest = $this->_build($bundlefile, $files);
        }
        catch (Exception $e)
        {
            throw new BuildException($e->getMessage());
        }

        // output the static CSS tag
        $twoBitValue = 0x3 & crc32("$bundlefile-$latest.css");
        $host = str_replace('?', $twoBitValue, $this->_host);
        $path = "http://$host/$bundlefile-$latest.css";
        $output = "<link href=\"$path\" rel=\"stylesheet\" type=\"text/css\" />\n";
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

            $buffer .= $this->_minify("{$this->_webRoot}/$file") . "\n";
        }

        // open the output file
        if (! file_exists($this->_toDir) && ! mkdir($this->_toDir, 0755, true))
        {
            throw new Exception("Unable to create {$this->_toDir}");
        }
        
        if (($handle = fopen("{$this->_toDir}/$bundlefile-$latest.css", 'w')) === false)
        {
            throw new Exception("Unable to create {$this->_toDir}/$bundlefile-$latest.css");
        }

        if (fwrite($handle, $buffer) === false)
        {
            fclose($handle);
            throw new Exception("Unable to write to {$this->_toDir}/$bundlefile-$latest.css");
        }

        fclose($handle);

        return $latest;
    }

    private function _minify($file)
    {
        $this->_phing->log("Attempting to minify $file", Project::MSG_VERBOSE);

        $tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
        $tmpFile = tempnam($tmp, "yui");

        $command = "java -jar " . $this->_yuiPath . " --type css -o $tmpFile $file";
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
