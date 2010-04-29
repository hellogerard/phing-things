<?php

include_once 'phing/Task.php';

class GrepAndTouchTask extends Task
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
        if (! $this->project->getProperty("copiedFilesMap"))
        {
            // no static resources were updated.
            return;
        }

        $project = $this->getProject();
        $this->_count = $this->_total = 0;

        // process filesets
        foreach ($this->filesets as $fs)
        {
            $ds = $fs->getDirectoryScanner($project);
            $fromDir  = $fs->getDir($project);
            $srcFiles = $ds->getIncludedFiles();

            $this->_grepAndTouch($fromDir, $srcFiles);
        }

        $this->log("Touched " . $this->_count . " out of " . $this->_total . " files");
    }


    /**
     * Grep for image names in files, then if an updated image name is found in
     * the file, the file is touched to update its last-modified time.
     *
     * @access  private
     * @return  void
     */
    private function _grepAndTouch(&$baseDir, &$names)
    {
        $copiedFilesMap = $this->project->getProperty("copiedFilesMap");

        // for each file, grep for image file names
        for ($i = 0, $size = count($names); $i < $size; $i++)
        {
            $name = $baseDir . '/' . $names[$i];
            $buffer = file_get_contents($name);

            foreach ($copiedFilesMap as $original => $tstamped)
            {
                $pattern = basename($original);

                if (strpos($buffer, $pattern) !== false)
                {
                    // found one - touch it
                    touch($name);
                    $this->_count++;

                    // only need to touch it once
                    break;
                }
            }
        }

        $this->_total += count($names);
    }
}

?>
