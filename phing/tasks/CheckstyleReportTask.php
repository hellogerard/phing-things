<?php

require_once 'phing/Task.php';
require_once 'phing/system/io/PhingFile.php';
require_once 'phing/system/io/FileWriter.php';
require_once 'phing/util/ExtendedFileStream.php';

class CheckstyleReportTask extends Task
{
	private $format = "frames-errors";
	private $styleDir = "";
	private $toDir = "";

	// the directory where the results XML can be found
	private $inFile = "checkstyle.xml";

	function setInFile($inFile)
	{
		$this->inFile = $inFile;
	}

	function setFormat($format)
	{
		$this->format = $format;
	}

	function setStyleDir($styleDir)
	{
		$this->styleDir = $styleDir;
	}

	function setToDir($toDir)
	{
		$this->toDir = $toDir;
	}
	
	private function getStyleSheet()
	{
		$xslname = "checkstyle-" . $this->format . ".xsl";

		if ($this->styleDir)
		{
			$file = new PhingFile($this->styleDir, $xslname);
		}
		else
		{
			$path = Phing::getResourcePath("phing/etc/$xslname");
			
			if ($path === NULL)
			{
				$path = Phing::getResourcePath("etc/$xslname");

				if ($path === NULL)
				{
					throw new BuildException("Could not find $xslname in resource path");
				}
			}
			
			$file = new PhingFile($path);
		}

		if (!$file->exists())
		{
			throw new BuildException("Could not find file " . $file->getPath());
		}

		return $file;
	}
	
	private function transform(DOMDocument $document)
	{
		$dir = new PhingFile($this->toDir);
		
		if (!$dir->exists())
		{
			throw new BuildException("Directory '" . $this->toDir . "' does not exist");
		}
		
		$xslfile = $this->getStyleSheet();

		$xsl = new DOMDocument();
		$xsl->load($xslfile->getAbsolutePath());

		$proc = new XSLTProcessor();
		$proc->importStyleSheet($xsl);

		if ($this->format == "noframes")
		{
			$writer = new FileWriter(new PhingFile($this->toDir, "checkstyle-noframes.html"));
			$writer->write($proc->transformToXML($document));
			$writer->close();
		}
		else
		{
			ExtendedFileStream::registerStream();

			// no output for the framed report
			// it's all done by extension...
			$dir = new PhingFile($this->toDir);
			$proc->setParameter('', 'output.dir', $dir->getAbsolutePath());
			$proc->transformToXML($document);
		}
	}
	
	private function fixPackages(DOMDocument $document)
	{
		$testsuites = $document->getElementsByTagName('testsuite');
		
		foreach ($testsuites as $testsuite)
		{
			if (!$testsuite->hasAttribute('package'))
			{
				$testsuite->setAttribute('package', 'default');
			}
		}
	}

	public function main()
	{
		$this->log("Transforming Checkstyle report");

		$testSuitesDoc = new DOMDocument();
		$testSuitesDoc->load($this->inFile);
		
		$this->fixPackages($testSuitesDoc);
		
		$this->transform($testSuitesDoc);
	}
}
