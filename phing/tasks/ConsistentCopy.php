<?php

include_once 'phing/tasks/system/CopyTask.php';
include_once 'phing/util/FileUtils.php';

class ConsistentCopier extends FileUtils
{
    function copyFile(PhingFile $sourceFile, PhingFile $destFile, $overwrite = false, $preserveLastModified = true, &$filterChains = null, Project $project) {
        // writes to tmp file first, then rename it to avoid file locking race
        // conditions
       
        $parent = $destFile->getParentFile();

        $tmpFile = new PhingFile($parent, substr(md5(time()), 0, 8));

        parent::copyFile($sourceFile, $tmpFile, $overwrite, $preserveLastModified, $filterChains, $project);

        $tmpFile->renameTo($destFile);
    }
}

class ConsistentCopy extends CopyTask 
{
    function __construct()
    {
        // the fileUtils member does the actual file copying. we want to
        // override this method, so inject our own fileUtils.
        $this->fileUtils = new ConsistentCopier();
    }
}

