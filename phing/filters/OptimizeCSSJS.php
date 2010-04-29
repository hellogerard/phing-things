<?php

require_once 'phing/filters/BaseParamFilterReader.php';
include_once 'phing/filters/ChainableReader.php';
require_once 'phing/filters/CSSOptimizer.php';
require_once 'phing/filters/JSOptimizer.php';

class OptimizeCSSJS extends BaseParamFilterReader implements ChainableReader
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

        $params = $this->getParameters();
        $staticWebRoot = $params[0]->getValue();

        $dest = '';
        $staticWebRoot = '';
        $params = $this->getParameters();

        // collect parameters.
        foreach ($params as $param)
        {
            //echo $param->getName() . ': ' . $param->getValue() . "\n";
            if ($param->getName() == "cssDir")
            {
                $dest = $param->getValue();
            }
            else if ($param->getName() == "staticWebRoot")
            {
                $staticWebRoot = $param->getValue();
            }
        }

        if (empty($dest))
        {
            $this->log("\"cssDir\" parameter not set", Project::MSG_WARN);
        }

        // must have exactly one static web root
        if (empty($staticWebRoot))
        {
            $this->log("\"staticWebRoot\" parameter not set", Project::MSG_WARN);
        }



        // find groups of CSS link tags and replace them with a single PHP call to our
        // Smarty plugin
        $opto = new CSSOptimizer($staticWebRoot, $dest);
        $pattern = '/(<link .*href=".*".*\/>\s*)+/';
        $buffer = preg_replace_callback($pattern, array($opto, "build"), $buffer);

        // find groups of JS link tags and replace them with a single PHP call to our
        // Smarty plugin
        $jsopto = new JSOptimizer($staticWebRoot, $dest);
        $pattern = '/(<script .*src=".*".*><\/script>\s*)+/';
        $buffer = preg_replace_callback($pattern, array($jsopto, "build"), $buffer);


        return $buffer;
    }

    /**
     * Creates a new OptimizeCSSJS filter using the passed in
     * Reader for instantiation.
     * 
     * @param Reader $reader A Reader object providing the underlying stream.
     *               Must not be <code>null</code>.
     * 
     * @return OptimizeCSSJS A new filter based on this configuration, but filtering
     *         the specified reader
     */
    function chain(Reader $reader)
    {
        $newFilter = new OptimizeCSSJS($reader);
        $newFilter->setProject($this->getProject());
        return $newFilter;
    }
}

?>
