<?php

include_once 'phing/Task.php';

class MinifyJSTask extends Task
{
    protected $filesets = array();
	protected $yuiCompressorPath = "";
    private $_count, $_total, $_oldsize, $_newsize = 0;

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

        // process filesets
        foreach ($this->filesets as $fs)
        {
            $ds = $fs->getDirectoryScanner($this->getProject());
            $fromDir  = $fs->getDir($this->getProject());
            $srcFiles = $ds->getIncludedFiles();

            $this->_minify($fromDir, $srcFiles);
        }

        $pct = round(($this->_newsize / $this->_oldsize) * 100, 2);
        $this->log("Minified {$this->_count}/{$this->_total} files ({$this->_newsize}/{$this->_oldsize} bytes or {$pct}%)");
    }


    /**
     * Runs an external program to minify JS files.
     *
     * @access  private
     * @return  void
     */
    private function _minify(&$baseDir, &$files)
    {
        for ($i = 0, $size = count($files); $i < $size; $i++)
        {
            $file = $baseDir . '/' . $files[$i];
            $success = false;

            clearstatcache();
            $oldsize = filesize($file);

            $tmp = (function_exists('sys_get_temp_dir')) ? sys_get_temp_dir() : '/tmp';
            $tmpfile = tempnam($tmp, "yui");

            $command = "java -jar " . $this->yuiCompressorPath . " --type js -o $tmpfile $file";
            exec($command, $dummy, $status);

            if ($status === 0)
            {
                $newsize = filesize($tmpfile);

                if ($newsize < $oldsize)
                {
                    $newFile = str_replace('.js', '.min.js', $file);
                    @rename($tmpfile, $newFile);
                    $success = true;
                }
            }
            else
            {
                @unlink($tmpfile);
            }

            $this->_oldsize += $oldsize;
            $this->_total++;
            $pct = round(($newsize / $oldsize) * 100, 2);

            if ($success)
            {
                $this->_count++;
                $this->_newsize += $newsize;
                $this->log("Optimized $file ($newsize/$oldsize bytes or {$pct}%)", Project::MSG_VERBOSE);
            }
            else
            {
                $this->_newsize += $oldsize;
                $this->log("Skipped $file ($newsize/$oldsize bytes or {$pct}%)", Project::MSG_VERBOSE);
            }
        }
    }
}

?>
