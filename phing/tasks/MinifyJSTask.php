<?php

include_once 'phing/Task.php';

class MinifyJSTask extends Task
{
    protected $filesets = array();
	protected $yuiCompressorPath = "";
    private $_count = 0;
    private $_total = 0;

	function setYuiCompressorPath($path)
	{
		$this->yuiCompressorPath = $path;
	}

    /**
     * Nested creator, creates a FileSet for this task
     *
     * @access  public
     * @return  object  The created fileset object
     */
    function createFileSet()
    {
        $num = array_push($this->filesets, new FileSet());
        return $this->filesets[$num-1];
    }

    public function main()
    {
        if (empty($this->yuiCompressorPath))
        {
            throw new BuildException("'yuiCompressorPath' attribute is required");
            return;
        }

        if (! file_exists($this->yuiCompressorPath))
        {
            throw new BuildException("'yuiCompressorPath' could not be found!");
            return;
        }

        $project = $this->getProject();

        // process filesets
        $this->_count = $this->_total = 0;
        foreach ($this->filesets as $fs)
        {
            $ds = $fs->getDirectoryScanner($project);
            $fromDir  = $fs->getDir($project);
            $srcFiles = $ds->getIncludedFiles();

            $this->_minify($fromDir, $srcFiles);
        }

        $this->log("Minified {$this->_count} out of {$this->_total} JavaScript files");
    }


    /**
     * Runs an external program to minify JS files.
     *
     * @access  private
     * @return  void
     */
    private function _minify(&$baseDir, &$names)
    {
        for ($i = 0, $size = count($names); $i < $size; $i++)
        {
            $srcFile = $baseDir . '/' . $names[$i];

            $this->log("Attempting to minify $srcFile", Project::MSG_VERBOSE);

            clearstatcache();
            $oldsize = filesize($srcFile);

            $tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
            $tmpFile = tempnam($tmp, "yui");

            $command = "java -jar " . $this->yuiCompressorPath . " --type js -o $tmpFile $srcFile";
            exec($command, $dummy, $status);

            $newsize = filesize($tmpFile);

            $this->log("Oldsize: $oldsize, Newsize: $newsize", Project::MSG_VERBOSE);
            if ($status === 0 && $newsize < $oldsize)
            {
                $this->_count++;
                $newFile = str_replace('.js', '.min.js', $srcFile);
                @rename($tmpFile, $newFile);
            }
            else
            {
                @unlink($tmpFile);
            }

            $this->_total++;
        }
    }
}

?>
