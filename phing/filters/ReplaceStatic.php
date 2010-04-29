<?php

require_once 'phing/filters/BaseParamFilterReader.php';
include_once 'phing/filters/ChainableReader.php';

class ReplaceStatic extends BaseParamFilterReader implements ChainableReader
{
    /**
     * Returns the filtered stream. 
     * The original stream is first read in fully, then all the HTML links to 
     * static resources are replaced.
     * 
     * @param int $len Required $len for Reader compliance.
     * 
     * @return mixed The filtered stream, or -1 if the end of the resulting stream has been reached.
     * 
     * @exception IOException if the underlying stream throws an IOException
     * during reading
     */
    function read($len = null)
    {
        // read from next filter up the chain
        $buffer = $this->in->read($len);

        if ($buffer === -1)
        {
            return -1;
        }

        if (! $this->project->getProperty("staticFileMap"))
        {
            // buildNames task was not run
            $this->log("Does not appear that <buildNames> task was run before this task", Project::MSG_WARN);
            return $buffer;
        }

        $prefixes = array();
        $staticWebRoot = '';
        $params = $this->getParameters();

        // collect parameters.
        foreach ($params as $param)
        {
            //echo $param->getName() . ': ' . $param->getValue() . "\n";
            if ($param->getName() == "prefix")
            {
                $prefixes[] = $param->getValue();
            }
            else if ($param->getName() == "staticWebRoot")
            {
                $staticWebRoot = $param->getValue();
            }
        }

        // may have 0 or more prefixes. if none, insert a blank one.
        if (empty($prefixes))
        {
            $prefixes[] = '';
        }

        // must have exactly one static web root
        if (empty($staticWebRoot))
        {
            $this->log("\"staticWebRoot\" parameter not set", Project::MSG_WARN);
        }

        // filter buffer
        $search = array();
        $replace = array();

        $subdomains = array();
        $subdomains[0] = str_replace("?", "0", $staticWebRoot);
        $subdomains[1] = str_replace("?", "1", $staticWebRoot);
        $subdomains[2] = str_replace("?", "2", $staticWebRoot);
        $subdomains[3] = str_replace("?", "3", $staticWebRoot);

        foreach ($this->project->getProperty("staticFileMap") as $old => $new)
        {
            $twoBitValue = 0x3 & crc32($new);
            $new = $subdomains[$twoBitValue] . $new;
            foreach ($prefixes as $prefix)
            {
                $search[] = $prefix . $old;
                $replace[] = $prefix . $new;
            }
        }

        $buffer = str_replace($search, $replace, $buffer);
        return $buffer;
    }

    /**
     * Creates a new ReplaceStatic filter using the passed in
     * Reader for instantiation.
     * 
     * @param Reader $reader A Reader object providing the underlying stream.
     *               Must not be <code>null</code>.
     * 
     * @return ReplaceStatic A new filter based on this configuration, but filtering
     *         the specified reader
     */
    function chain(Reader $reader)
    {
        $newFilter = new ReplaceStatic($reader);
        $newFilter->setProject($this->getProject());
        return $newFilter;
    }
}

?>
