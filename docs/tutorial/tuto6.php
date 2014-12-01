<?php
require '../../Fpdf.php';

class PDF extends Fpdf
{
	var $B;
	var $I;
	var $U;
	var $HREF;

	function __construct($orientation='P', $unit='mm', $size='A4')
	{
		// Call parent constructor
		parent::__construct($orientation,$unit,$size);
		// Initialization
		$this->B = 0;
		$this->I = 0;
		$this->U = 0;
		$this->HREF = '';
	}

	function WriteHTML($html)
	{
		// HTML parser
		$html = str_replace("\n",' ',$html);
		$a = preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
		foreach($a as $i=>$e)
		{
			if($i%2==0)
			{
				// Text
				if($this->HREF)
					$this->PutLink($this->HREF,$e);
				else
					$this->write(5,$e);
			}
			else
			{
				// Tag
				if($e[0]=='/')
					$this->CloseTag(strtoupper(substr($e,1)));
				else
				{
					// Extract attributes
					$a2 = explode(' ',$e);
					$tag = strtoupper(array_shift($a2));
					$attr = array();
					foreach($a2 as $v)
					{
						if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
							$attr[strtoupper($a3[1])] = $a3[2];
					}
					$this->OpenTag($tag,$attr);
				}
			}
		}
	}

	function OpenTag($tag, $attr)
	{
		// Opening tag
		if($tag=='B' || $tag=='I' || $tag=='U')
			$this->setStyle($tag,true);
		if($tag=='A')
			$this->HREF = $attr['HREF'];
		if($tag=='BR')
			$this->ln(5);
	}

	function CloseTag($tag)
	{
		// Closing tag
		if($tag=='B' || $tag=='I' || $tag=='U')
			$this->setStyle($tag,false);
		if($tag=='A')
			$this->HREF = '';
	}

	function SetStyle($tag, $enable)
	{
		// Modify style and select corresponding font
		$this->$tag += ($enable ? 1 : -1);
		$style = '';
		foreach(array('B', 'I', 'U') as $s)
		{
			if($this->$s>0)
				$style .= $s;
		}
		$this->setFont('',$style);
	}

	function PutLink($URL, $txt)
	{
		// Put a hyperlink
		$this->setTextColor(0,0,255);
		$this->setStyle('U',true);
		$this->write(5,$txt,$URL);
		$this->setStyle('U',false);
		$this->setTextColor(0);
	}
}

$html = 'You can now easily print text mixing different styles: <b>bold</b>, <i>italic</i>,
<u>underlined</u>, or <b><i><u>all at once</u></i></b>!<br><br>You can also insert links on
text, such as <a href="http://www.fpdf.org">www.fpdf.org</a>, or on an image: click on the logo.';

$pdf = new PDF();
// First page
$pdf->addPage();
$pdf->setFont('Arial','',20);
$pdf->write(5,"To find out what's new in this tutorial, click ");
$pdf->setFont('','U');
$link = $pdf->addLink();
$pdf->write(5,'here',$link);
$pdf->setFont('');
// Second page
$pdf->addPage();
$pdf->setLink($link);
$pdf->image('logo.png',10,12,30,0,'','http://www.fpdf.org');
$pdf->setLeftMargin(45);
$pdf->setFontSize(14);
$pdf->writeHTML($html);
$pdf->output();
?>
