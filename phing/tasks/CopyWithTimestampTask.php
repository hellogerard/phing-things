<?php

include_once 'phing/tasks/system/CopyTask.php';

class CopyWithTimestampTask extends CopyTask 
{
    private $_property;

    /**
     * The property to set if the target files are up to date. Optional.
     *
     * @param property the name of the property to set.
     */
    public function setProperty($property)
    {
        $this->_property = $property;
    }

    /**
     * Copies files. File names are modified, appending the last modification 
     * times of the files.  Old files are deleted as needed. We override 
     * CopyTask::doWork() and call it at the end.
     *
     * @access  private
     * @return  void
     * @throws  BuildException
     */
    protected function doWork()
    {
        $mapSize = count($this->fileCopyMap);
        umask(0002);

        if ($mapSize > 0)
        {
            // walks the map and actually copies the files
            foreach ($this->fileCopyMap as $from => $to)
            {
                try
                {
                    // try to delete old file
                    $glob = preg_replace("/-[0-9]+\./", "-*.", $to);
                    $matches = glob($glob);

                    if ($matches[0])
                    {
                        $toFile = new PhingFile($matches[0]);
                        $toFile->delete();
                    }
                }
                catch (IOException $ioe)
                {
                    $this->log("Failed to delete " . $glob . ": " . $ioe->getMessage(), Project::MSG_ERR);
                }
            }
        }
        else if ($this->_property)
        {
            $this->project->setProperty($this->_property, "true"); 
        }

        // this must be called last, or else we might delete any new files we 
        // copied!
        parent::doWork();

        // save the map of files actually copied
        //print_r($this->fileCopyMap);
        $this->project->setProperty("copiedFilesMap", $this->fileCopyMap);
    }
}

?>
