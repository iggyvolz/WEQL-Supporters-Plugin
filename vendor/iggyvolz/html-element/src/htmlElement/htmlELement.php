<?php

namespace htmlElement;

class htmlElement
{

	public $running = FALSE;
	public $opentag;
	public $closetag;

	public function __construct($tagname, $atts = NULL)
	{
		$this->closetag = '</' . $tagname . '>';
		if ($atts === NULL)
		{
			$this->opentag = '<' . $tagname . '>';
		}
		else
		{
			$this->opentag = '<' . $tagname;
			foreach ($atts as $key => $value)
			{
				$this->opentag.=' ' . $key . '="' . $value . '"';
			}
			$this->opentag.='>';
		}
	}

	public function toggle()
	{
		if ($this->running)
		{
			return $this->turnoff();
		}
		else
		{
			return $this->turnon();
		}
	}

	public function turnon()
	{
		echo $this->opentag;
		$this->running = TRUE;
		return TRUE;
	}

	public function turnoff()
	{
		echo $this->closetag;
		$this->running = FALSE;
		return FALSE;
	}

}

