<?php

include_once 'phing/Task.php';

class OptimizeImagesTask extends Task
{
    protected $filesets = array();
    private $_count = 0;
    private $_total = 0;

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
        if (strpos(PHP_OS, "WIN") !== false)
        {
            $this->log("Cannot run this task on Windows", Project::MSG_WARN);
            return;
        }

        $this->_count = $this->_total = 0;
        if (! $this->project->getProperty("copiedFilesMap"))
        {
            $project = $this->getProject();

            // process filesets
            foreach ($this->filesets as $fs)
            {
                $ds = $fs->getDirectoryScanner($project);
                $fromDir  = $fs->getDir($project);
                $srcFiles = $ds->getIncludedFiles();

                $this->_optimize($fromDir, $srcFiles);
            }
        }
        else
        {
            $this->_optimize($fromDir, array_keys($this->project->getProperty("copiedFilesMap")));
        }

        $this->log("Optimized " . $this->_count . " out of " . $this->_total . " image files");
    }


    /**
     * Runs an external program to optimize images.
     *
     * @access  private
     * @return  void
     */
    private function _optimize(&$baseDir, &$names)
    {
        for ($i = 0, $size = count($names); $i < $size; $i++)
        {
            $name = $baseDir . '/' . $names[$i];
            $arr = getimagesize($name);
            $type = $arr[2];

            switch ($type)
            {
                case IMAGETYPE_GIF:

                    // gif not yet implemented
                    break;

                case IMAGETYPE_JPEG:

                    $this->log("Attempting to optimize $name", Project::MSG_VERBOSE);

                    clearstatcache();
                    $oldsize = filesize($name);

                    $outfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($name);
                    exec("jpegtran -optimize -outfile \"$outfile\" \"$name\"", $dummy, $status);

                    $newsize = filesize($outfile);

                    if ($status === 0 && $newsize < $oldsize)
                    {
                        $this->_count++;
                        @rename($outfile, $name);
                    }
                    else
                    {
                        @unlink($outfile);
                    }

                    break;

                case IMAGETYPE_PNG:

                    $this->log("Attempting to optimize $name", Project::MSG_VERBOSE);
                    $cmd = "optipng \"$name\"";
                    exec($cmd, $dummy, $status);

                    if ($status === 0)
                    {
                        $this->_count++;
                    }

                    break;

                default:
                    break;
            }

            $this->_total++;
        }
    }
}

?>
