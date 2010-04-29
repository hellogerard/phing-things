<?php

require_once "phing/mappers/FileNameMapper.php";

class LastModMapper implements FileNameMapper
{
    private $_base;

    /**
     * The main() method actually performs the mapping.
     *
     * @param string $sourceFilename The name to be coverted.
     * @return array The matched filenames.
     */
    public function main($sourceFilename)
    {
        $parts = pathinfo($sourceFilename);
        $base = $parts['dirname'] . '/' . $parts['filename'];
        $ext = $parts['extension'];
        $lastmod = filemtime($this->_base . '/' . $sourceFilename);

        return array($base . "-$lastmod." . $ext);
    }

    /**
     * Use the from attribute to set the base of the files we are copying. This 
     * is a bit of a hack, but not too bad.
     */
    public function setFrom($from)
    {
        $this->_base = $from;
    }

    /**
     * The "from" attribute is not needed here, but method must exist.
     */
    public function setTo($to) {}

}

?>
