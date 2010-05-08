<?php

include_once 'phing/Task.php';

class SmartyCompileTask extends Task
{
    protected $filesets = array();
    private $_count = 0;
    private $_total = 0;

    private $_smarty = null;
    private $_compilePath;
    private $_pluginsPath;
    private $_forceCompile = false;

    public function init()
    {
        include_once 'Smarty/libs/Smarty.class.php';
        if (! class_exists('Smarty'))
        {
            throw new BuildException("You must have Smarty.class.php on the include_path.");
        }
    }

    /**
     * Nested creator, creates a FileSet for this task. Required.
     *
     * @access  public
     * @return  object  The created fileset object
     */
    function createFileSet()
    {
        $num = array_push($this->filesets, new FileSet());
        return $this->filesets[$num-1];
    }

    /**
     * Set the force compile flag. Optional. Defaults to false.
     *
     * @param  boolean  Compile every template file even if it is older.
     * @return void
     * @access public
     */
    function setForceCompile($bool)
    {
        $this->_forceCompile = (boolean) $bool;
    }

    /**
     * Set the path for compiled templates. Required.
     *
     * @param  boolean  Path
     * @return void
     * @access public
     */
    function setCompilePath($path)
    {
        $this->_compilePath = $path;
    }

    /**
     * Set the path for plugins. Optional.
     *
     * @param  boolean  Path
     * @return void
     * @access public
     */
    function setPluginsPath($path)
    {
        $this->_pluginsPath = $path;
    }

    public function main()
    {
        if (empty($this->filesets))
        {
            throw new BuildException("You must specify a file or fileset(s).");
        }

        if (empty($this->_compilePath))
        {
            throw new BuildException("You must specify location for compiled templates.");
        }

        date_default_timezone_set("America/New_York");

        $project = $this->getProject();
        $this->_count = $this->_total = 0;

        $smartyCompilePath = new PhingFile($this->_compilePath);
        if (! $smartyCompilePath->exists())
        {
            $this->log("Compile directory does not exist, creating: " . $smartyCompilePath->getPath(), Project::MSG_VERBOSE);
            if (! $smartyCompilePath->mkdirs())
            {
                throw new BuildException("Error creating compile path for Smarty in ".$this->_compilePath);
            }
        }

        $this->_smarty = new Smarty();
        $this->_smarty->use_sub_dirs = true;
        $this->_smarty->compile_dir = $smartyCompilePath;
        $this->_smarty->plugins_dir[] = $this->_pluginsPath;
        $this->_smarty->force_compile = $this->_forceCompile;

        // process filesets
        foreach ($this->filesets as $fs)
        {
            $ds = $fs->getDirectoryScanner($project);
            $fromDir  = $fs->getDir($project);
            $srcFiles = $ds->getIncludedFiles();

            $this->_compile($fromDir, $srcFiles);
        }

        $this->log("Compiled " . $this->_count . " out of " . $this->_total . " Smarty templates");
    }

    /**
     * Compiles Smarty templates
     *
     * @access  private
     * @return  void
     */
    private function _compile(&$baseDir, &$names)
    {
        $this->_smarty->template_dir = $baseDir;

        for ($i = 0, $size = count($names); $i < $size; $i++)
        {
            $name = $names[$i];

            //echo "{$matches[1]} $name\n";

            $realpath = $this->_smarty->_get_compile_path($name);

            if (! $this->_smarty->_is_compiled($name, $realpath))
            {
                $this->log("Compiling $name", Project::MSG_VERBOSE);
                $this->_smarty->_compile_resource($name, $realpath);
                $this->_count++;
            }

            $this->_total++;
        }
    }
}

?>
