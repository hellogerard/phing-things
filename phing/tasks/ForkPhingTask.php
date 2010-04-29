<?php

include_once 'phing/Task.php';

class ForkPhingTask extends ExecTask
{
    protected $filesets = array();

    function createFileSet()
    {
        $num = array_push($this->filesets, new FileSet());
        return $this->filesets[$num-1];
    }

    public function main()
    {
        $project = $this->getProject();

        // process filesets
        foreach ($this->filesets as $fs)
        {
            $ds = $fs->getDirectoryScanner($project);
            $files = $ds->getIncludedFiles();

            for ($i = 0, $size = count($files); $i < $size; $i++)
            {
                $this->execute("phing -logger phing.listener.DefaultLogger -f {$files[$i]}");
            }
        }
    }

    public function execute($command)
    {
        // test if os match
        $myos = Phing::getProperty("os.name");
        $this->log("Myos = " . $myos, Project::MSG_VERBOSE);
        if (($this->os !== null) && (strpos($this->os, $myos) === false)) {
            // this command will be executed only on the specified OS
            $this->log("Not found in " . $this->os, Project::MSG_VERBOSE);
            return 0;
        }

        if ($this->dir !== null) {
            if ($this->dir->isDirectory()) {
                $currdir = getcwd();
                @chdir($this->dir->getPath());
            } else {
                throw new BuildException("Can't chdir to:" . $this->dir->__toString());
            }
        }


        if ($this->escape == true) {
            // FIXME - figure out whether this is correct behavior
            $command = escapeshellcmd($command);
        }

        if ($this->error !== null) {
            $command .= ' 2> ' . $this->error->getPath();
            $this->log("Writing error output to: " . $this->error->getPath());
        }

        if ($this->output !== null) {
            $command .= ' 1> ' . $this->output->getPath();
            $this->log("Writing standard output to: " . $this->output->getPath());
        } elseif ($this->spawn) {
            $command .= ' 1>/dev/null';
            $this->log("Sending ouptut to /dev/null");
        }

        // If neither output nor error are being written to file
        // then we'll redirect error to stdout so that we can dump
        // it to screen below.

        if ($this->output === null && $this->error === null) {
            $command .= ' 2>&1';
        }

        // we ignore the spawn boolean for windows
        if ($this->spawn) {
            $command .= ' &';
        }

        $this->log("Executing command: " . $command);

        $return = null;

        if ($this->passthru)
        {
            passthru($command, $return);
        }
        else
        {
            $output = array();
            exec($command, $output, $return);
        }

        if ($this->dir !== null) {
            @chdir($currdir);
        }

        if($return != 0 && $this->checkreturn)
        {
            throw new BuildException("Task exited with code $return");
        }

        return $return;
    }
}

?>
