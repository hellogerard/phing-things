<?php

require_once 'phing/filters/BaseParamFilterReader.php';
include_once 'phing/filters/ChainableReader.php';
require_once 'phing/filters/JSOptimizer.php';

class OptimizeJS extends BaseParamFilterReader implements ChainableReader
{
    /**
     * Returns the filtered stream. 
     * The original stream is first read in fully, then the HTML is updated
     * for this partner.
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

        $host = $webRoot = $toDir = $yuiPath = '';
        $params = $this->getParameters();

        // collect parameters.
        foreach ($params as $param)
        {
            //echo $param->getName() . ': ' . $param->getValue() . "\n";
            if ($param->getName() == "static.host")
            {
                $host = $param->getValue();
            }
            else if ($param->getName() == "webroot")
            {
                $webRoot = $param->getValue();
            }
            else if ($param->getName() == "todir")
            {
                $toDir = $param->getValue();
            }
            else if ($param->getName() == "yui.path")
            {
                $yuiPath = $param->getValue();
            }
        }

        if (empty($host))
        {
            throw new BuildException("\"static.host\" parameter not set");
        }
        else if (empty($webRoot))
        {
            throw new BuildException("\"webroot\" parameter not set");
        }
        else if (empty($toDir))
        {
            throw new BuildException("\"todir\" parameter not set");
        }
        else if (empty($yuiPath))
        {
            throw new BuildException("\"yui.path\" parameter not set");
        }

        // find groups of JS link tags and replace them with a single link a
        // bundled, minified, timestamped resource. ignore externally hosted
        // files (e.g. jquery).

        $optomizer = new JSOptimizer($host, $yuiPath, $webRoot, $toDir, $this);
        $pattern = '/(<script .*src="[^h|"]*".*><\/script>\s*)+/';
        $buffer = preg_replace_callback($pattern, array($optomizer, "optimize"), $buffer);


        return $buffer;
    }

    /**
     * Creates a new OptimizeJS filter using the passed in Reader for
     * instantiation.
     * 
     * @param Reader $reader A Reader object providing the underlying stream.
     *               Must not be <code>null</code>.
     * 
     * @return OptimizeJS A new filter based on this configuration, but filtering
     *         the specified reader
     */
    function chain(Reader $reader)
    {
        $newFilter = new OptimizeJS($reader);
        $newFilter->setProject($this->getProject());
        return $newFilter;
    }
}

?>
