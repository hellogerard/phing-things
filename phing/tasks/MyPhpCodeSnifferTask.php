<?php

require_once 'phing/tasks/ext/PhpCodeSnifferTask.php';
require_once 'phing/system/io/PhingFile.php';
require_once 'phing/system/io/FileWriter.php';

class MyPhpCodeSnifferTask extends PhpCodeSnifferTask {

	protected $outfile = 'none';
	protected $haltOnFailure = false;

	public function setOutfile($outfile)
	{
		$this->outfile = $outfile;
	}

	public function setHaltOnFailure($haltOnFailure)
	{
		$this->haltOnFailure = $haltOnFailure;
	}

	public function main() {
        parent::main();

        // PHP_CodeSniffer() does a chdir(), so chdir() back
        chdir($this->project->getProperty("project.basedir"));
	}

	protected function output($codeSniffer) {

        parent::output($codeSniffer);

		if ($this->outfile == 'none') {
            return;
		}

        ob_start();
        $codeSniffer->printCheckstyleErrorReport($this->showWarnings);
        $checkstyle = ob_get_contents();
        ob_end_clean();

        $dir = dirname($this->outfile);
        if (! file_exists($dir))
        {
            mkdir($dir);
        }

        $writer = new FileWriter(new PhingFile($this->outfile));
        $writer->write($checkstyle);
        $writer->close();

        // halt build on errors
		$report = $codeSniffer->prepareErrorReport($this->showWarnings);
        if ($this->haltOnFailure && $report['totals']['errors'])
        {
            throw new BuildException('One or more files had coding standard errors');
        }
	}
}
