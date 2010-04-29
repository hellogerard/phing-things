<?php

include_once 'phing/Task.php';

class BuildNamesTask extends Task
{
    private $_filesets = array();
    private $_staticFileMap = array(); // asoc array containing mapped file names
    private $_total = 0;

    /**
     * Nested creator, creates a FileSet for this task
     *
     * @access  public
     * @return  object  The created fileset object
     */
    function createFileSet()
    {
        $num = array_push($this->_filesets, new FileSet());
        return $this->_filesets[$num-1];
    }

    public function main()
    {
        $project = $this->getProject();
        $this->_total = 0;

        // process filesets
        foreach ($this->_filesets as $fs)
        {
            $ds = $fs->getDirectoryScanner($project);
            $srcFiles = $ds->getIncludedFiles();

            $this->_buildMap($srcFiles, $this->_staticFileMap);
        }

        //print_r($this->_staticFileMap);
        $this->project->setProperty("staticFileMap", $this->_staticFileMap);
        $this->log("Built a list of " . $this->_total . " files");
    }


    /**
     * Builds a map of static resources to their timestamped versions
     *
     * @access  private
     * @return  void
     */
    private function _buildMap(&$names, &$map)
    {
        for ($i = 0, $size = count($names); $i < $size; $i++)
        {
            $original = "/" . str_replace(DIRECTORY_SEPARATOR, "/", $names[$i]);
            $original = preg_replace("/-[0-9]+\./", ".", $original);

            $new = "/" . str_replace(DIRECTORY_SEPARATOR, "/", $names[$i]);

            $map[$original] = $new;
            $this->_total++;
        }
    }
}

?>
