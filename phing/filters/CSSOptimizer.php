<?php
/*
 *  Inputs one or more CSS files and returns a link to 
 *  a single CSS file that is:
 *  - combined
 *  - compressed
 *  - minified
 *  - with a timestamp-based name
 */


require_once('class.yui_compressor.php');


class CSSOptimizer
{
    private $_staticWebRoot;
    private $_cssDir;

    public function __construct($staticWebRoot, $cssDir)
    {
        $this->_staticWebRoot = $staticWebRoot;
        $this->_cssDir = $cssDir;
    }

    public function build($buffer)
    {
        $output = '';

        // matched buffer comes in from preg_replace_callback() as array
        $buffer = $buffer[0];

        $dom = new DOMDocument();
        $dom->loadHTML($buffer);
        $html = simplexml_import_dom($dom);

        // gather file names
        // get URL's for each CSS file in each CSS tag of the HTML block
        $urls = array();
        foreach ($html->head->link as $link)
        {
            // filter out things like favicon
            if ((string) $link['rel'] == 'stylesheet')
            {
                $file = trim((string) $link['href']);
                $urls[] = $file;
            }
        }

        // set up some variables for str_replace()
        $needles = array('/', '.css');
        $replace = array('_', '');

        // get list of all partners for expansion
        $partners = @scandir($this->_cssDir . "/partners");
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
            // output the dynamic CSS call - on page load, Smarty will find the 
            // final version
            $webRoot = $this->_webRoot("$unexpanded-*.css");
            $output .= "<link href=\"$webRoot/{glob file=\"$unexpanded-*.css\"}\" rel=\"stylesheet\" type=\"text/css\" />\n";
        }
        else
        {
            // output the static CSS tag
            $webRoot = $this->_webRoot("$set-$latest.css");
            $output .= "<link href=\"$webRoot/$set-$latest.css\" rel=\"stylesheet\" type=\"text/css\" />\n";
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
            if ( $partner != 'core' && ($mtime = @filemtime( $this->_cssDir . "/partners/$partner$file")) !== false )
            {
                $files[] = "/partners/$partner$file";
            }
            else if ( ($mtime = @filemtime( $this->_cssDir . $file)) !== false )
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
        $existing = glob($this->_cssDir . "/$set-*.css");

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
                @unlink($this->_cssDir . "/$set-$oldLatest.css");

                return $latest;
            }
        }

        // else source files have not changed - no need to build bundle
        return false;
    }

    private function _saveSet($set, $files, $timestamp)
    {
        // build the output file
        if (($handle = fopen($this->_cssDir . "/$set-$timestamp.css", 'w')) === false)
        {
            throw new Exception("Unable to create {$this->_cssDir}/$set-$timestamp.css");
        }

        // combine all files into one
        foreach ($files as $file)
        {
            $cssSrcFile = $this->_cssDir . $file;
            
            // compress file contents
            $contents = trim(YuiCompress::compress ($cssSrcFile, YuiCompress::CSS_TYPE));
            
            if (fwrite($handle, $contents) === false)
            {
                fclose($handle);
                throw new Exception("Unable to write to {$this->_cssDir}/$set-$timestamp.css");
            }
        }

        fclose($handle);
    }
}

?>
