<?php
/*
 *  Inputs one or more JS files and returns a link to 
 *  a single JS file that is:
 *  - combined
 *  - compressed
 *  - minified
 *  - with a timestamp-based name
 */


require_once('class.yui_compressor.php');


class JSOptimizer
{
    private $_staticWebRoot;
    private $_jsDir;

    public function __construct($staticWebRoot, $jsDir)
    {
        $this->_staticWebRoot = $staticWebRoot;
        $this->_jsDir = $jsDir;
    }

    public function build($buffer)
    {
        $output = '';

        // matched buffer comes in from preg_replace_callback() as array
        $buffer = $buffer[0];

        // gather file names
        // get URL's for each JS file in each JS tag of the HTML block
        preg_match_all('/src="([^"]*)"/', $buffer, $urls);

        // set up some variables for str_replace()
        $needles = array('/', '.js');
        $replace = array('_', '');

        // get list of all partners for expansion
        $partners = @scandir($this->_jsDir . "/partners");
        array_unshift($partners, 'core');

        // for each partner, create a combined, minified file
        foreach ($partners as $partner)
        {
            if ($partner == "." || $partner == "..")
            {
                continue;
            }

            if (($files = $this->_determineSet($urls, $latest, $partner)) !== false)
            {
                // get the final filename
                $set = str_replace($needles, $replace, implode('-', $files));

                if (($newLatest = $this->_compareSet($set, $latest)) !== false)
                {
                    // build the output file
                    // the latest mod time of the set is in output file name
                    try
                    {
                        $this->_saveSet($set, $files, $newLatest);
                    }
                    catch (Exception $e)
                    {
                        $this->log($e->getMessage(), Project::MSG_ERR);
                    }
                }

                // get a dynamic filename which we will output at the end
                $unexpanded = str_replace($partner, '`$partnerName`', $set);
            }
        }

        if (strpos($unexpanded, '$partnerName') !== false)
        {
            // output the dynamic JS call - on page load, Smarty will find the 
            // final version
            $webRoot = $this->_webRoot("$unexpanded-*.js");
            $output .= "<script src=\"$webRoot/{glob file=\"$unexpanded-*.js\"}\" type=\"text/javascript\"></script>\n";
        }
        else
        {
            // output the static JS tag
            $webRoot = $this->_webRoot("$set-$latest.js");
            $output .= "<script src=\"$webRoot/$set-$latest.js\" type=\"text/javascript\"></script>\n";
        }

        return $output;
    }

    private function _webRoot($file)
    {
        $twoBitValue = 0x3 & crc32($file);
        return str_replace("?", $twoBitValue, $this->_staticWebRoot);
    }

    private function _determineSet($urls, &$latest, $partner)
    {
        // determine correct versions and latest mod time of files
        // return the name of the combined set of files

        $files = array();
        $latest = 0;
        foreach ($urls as $file)
        {
            $file = str_replace('$partnerName', $partner, $file);
            if ( $partner != 'core' && ($mtime = @filemtime( $this->_jsDir . "/partners/$partner$file")) !== false )
            {
                $files[] = "/partners/$partner$file";
            }
            else if ( ($mtime = @filemtime( $this->_jsDir . $file)) !== false )
            {
                $files[] = $file;
            }

            if ($mtime !== false && $mtime > $latest)
            {
                $latest = $mtime;
            }
        }

        // if no files are found
        if (empty($files))
        {
            return false;
        }

        return $files;
    }

    private function _compareSet($set, $latest)
    {
        $existing = glob($this->_jsDir . "/$set-*.js");

        // if the set does not exist, create new set outright
        if (! $existing[0])
        {
            return $latest;
        }

        // else the set already exists
        else
        {
            // get the existing set's latest mod time (in the filename)
            preg_match("/-([0-9]+)\./", $existing[0], $matches);
            $oldLatest = $matches[1];

            // compare the latest mod time of the set to the existing file name
            // if $latest is greater than $oldLatest, create new file
            if ($latest > $oldLatest)
            {
                // delete the old file
                @unlink($this->_jsDir . "/$set-$oldLatest.js");

                return $latest;
            }
        }

        // else source files have not changed - no need to build bundle
        return false;
    }

    private function _saveSet($set, $files, $timestamp)
    {
        // build the output file
        if (($handle = fopen($this->_jsDir . "/$set-$timestamp.js", 'w')) === false)
        {
            throw new Exception("Unable to create {$this->_jsDir}/$set-$timestamp.js");
        }

        // combine all files into one
        foreach ($files as $file)
        {
            $jsSrcFile = $this->_jsDir . $file;
            
            // compress file contents
            $contents = trim(YuiCompress::compress ($jsSrcFile, YuiCompress::JS_TYPE));
            
            if (fwrite($handle, $contents) === false)
            {
                fclose($handle);
                throw new Exception("Unable to write to {$this->_jsDir}/$set-$timestamp.js");
            }
        }

        fclose($handle);
    }
}

?>
