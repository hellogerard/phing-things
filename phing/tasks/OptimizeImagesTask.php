<?php

include_once 'phing/Task.php';

class OptimizeImagesTask extends Task
{
    protected $filesets = array();
    private $_count, $_total, $_oldsize, $_newsize = 0;

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
        // process filesets
        foreach ($this->filesets as $fs)
        {
            $ds = $fs->getDirectoryScanner($this->getProject());
            $fromDir  = $fs->getDir($this->getProject());
            $srcFiles = $ds->getIncludedFiles();

            $this->_optimize($fromDir, $srcFiles);
        }

        $pct = round(($this->_newsize / $this->_oldsize) * 100, 2);
        $this->log("Optimized {$this->_count}/{$this->_total} files ({$this->_newsize}/{$this->_oldsize} bytes or {$pct}%)");
    }


    /**
     * Runs an external program to optimize images.
     *
     * @access  private
     * @return  void
     */
    private function _optimize(&$baseDir, &$files)
    {
        for ($i = 0, $size = count($files); $i < $size; $i++)
        {
            $file = $baseDir . '/' . $files[$i];
            $type = exif_imagetype($file);
            $success = false;

            switch ($type)
            {
                case IMAGETYPE_GIF:

                    // convert GIFs to PNG
                    clearstatcache();
                    $oldsize = filesize($file);

                    $pngfile = str_replace('.gif', '.png', $file);
                    $cmd = "optipng \"$file\"";
                    exec($cmd, $dummy, $status);

                    if ($status === 0)
                    {
                        $newsize = filesize($pngfile);

                        if ($newsize < $oldsize)
                        {
                            $return = $pngfile;
                            @unlink($file);
                            $success = true;
                        }
                        else
                        {
                            @unlink($pngfile);
                        }
                    }
                    else
                    {
                        $newsize = $oldsize;
                        $this->_phing->log("optipng not found in path.", Project::MSG_WARN);
                    }

                    break;

                case IMAGETYPE_JPEG:

                    clearstatcache();
                    $oldsize = filesize($file);

                    $tmpfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($file);
                    exec("jpegtran -optimize -outfile \"$tmpfile\" \"$file\"", $dummy, $status);

                    if ($status === 0)
                    {
                        $newsize = filesize($tmpfile);

                        if ($newsize < $oldsize)
                        {
                            @rename($tmpfile, $file);
                            $success = true;
                        }
                    }
                    else
                    {
                        $newsize = $oldsize;
                        $this->_phing->log("jpegtran not found in path.", Project::MSG_WARN);
                    }

                    break;

                case IMAGETYPE_PNG:

                    clearstatcache();
                    $oldsize = filesize($file);

                    $cmd = "optipng \"$file\"";
                    exec($cmd, $dummy, $status);

                    if ($status === 0)
                    {
                        $newsize = filesize($file);

                        if ($newsize < $oldsize)
                        {
                            $success = true;
                        }
                    }
                    else
                    {
                        $newsize = $oldsize;
                        $this->_phing->log("optipng not found in path.", Project::MSG_WARN);
                    }

                    break;

                default:
                    break;
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
