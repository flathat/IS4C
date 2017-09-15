<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op
    Copyright 2015 The Tech Support Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
/* 14Sep2017 EL Works for some sets, e.g. retail, but not for others e.g. Kitchen,
 * for which it shows the queue name on an otherwise blank page.
 */

/*
    Using layouts
    1. Make a file, e.g. New_Layout.php
    2. Make a PDF class New_Layout_PDF extending FPDF
       (just copy an existing one to get the UPC/EAN/Barcode
        functions)
    3. Make a function New_Layout($data)
       Data is an array database rows containing:
        normal_price
        description
        brand
        units
        size
        sku
        pricePerUnit
        upc
        vendor
        scale
        numflag
        dept_name
    4. In your function, build the PDF. Look at 
       existings ones for some hints and/or FPDF
       documentation

    Name matching is important
*/

if (!defined('FPDF_FONTPATH')) {
	define('FPDF_FONTPATH','font/');
}

//require($FANNIE_ROOT.'src/fpdf/fpdf.php');
if (!class_exists('FPDF')) {
    require(dirname(__FILE__) . '/../../../src/fpdf/fpdf.php');
}

/****Credit for the majority of what is below for barcode generation
 has to go to Olivier for posting the script on the FPDF.org scripts
 webpage.****/

class WEFC_Inventory_Items_PDF extends FPDF
{
	var $tagdate;
	function setTagDate($str){
   		$this->tagdate = $str;
   	}
  
	function EAN13($x,$y,$barcode,$h=16,$w=.35)
	{
		$this->Barcode($x,$y,$barcode,$h,$w,13);
	}

	function UPC_A($x,$y,$barcode,$h=16,$w=.35)
	{
		$this->Barcode($x,$y,$barcode,$h,$w,12);
	}

	function GetCheckDigit($barcode)
	{
		// Compute the check digit
		$sum=0;
		for ($i=1;$i<=11;$i+=2) {
			$sum+=3*(isset($barcode[$i])?$barcode[$i]:0);
		}
		for ($i=0;$i<=10;$i+=2) {
        	$sum+=(isset($barcode[$i])?$barcode[$i]:0);
		}
      	$r=$sum%10;
      	if ($r>0) {
        	$r=10-$r;
      	}
      	return $r;
	}

	function TestCheckDigit($barcode)
	{
      	// Test validity of check digit
		$sum=0;
		for ($i=1;$i<=11;$i+=2) {
        	$sum+=3*$barcode{$i};
		}
      	for ($i=0;$i<=10;$i+=2) {
			$sum+=$barcode{$i};
      	}
      	return ($sum+$barcode{12})%10==0;
	}

	function Barcode($x,$y,$barcode,$h,$w,$len)
	{
		// Padding
      	$barcode=str_pad($barcode,$len-1,'0',STR_PAD_LEFT);
		if ($len==12) {
			$barcode='0'.$barcode;
		}
		// Add or control the check digit
      	if (strlen($barcode)==12) {
        	$barcode.=$this->GetCheckDigit($barcode);
      	} else if (!$this->TestCheckDigit($barcode)) {
        	$this->Error('This is an Incorrect check digit' . $barcode);
			// echo $x.$y.$barcode."\n";
      	}
      	
		// Convert digits to bars
		$codes=array(
			'A'=>array(
	            '0'=>'0001101','1'=>'0011001','2'=>'0010011','3'=>'0111101','4'=>'0100011',
	            '5'=>'0110001','6'=>'0101111','7'=>'0111011','8'=>'0110111','9'=>'0001011'),
	        'B'=>array(
    	        '0'=>'0100111','1'=>'0110011','2'=>'0011011','3'=>'0100001','4'=>'0011101',
        	    '5'=>'0111001','6'=>'0000101','7'=>'0010001','8'=>'0001001','9'=>'0010111'),
        	'C'=>array(
            	'0'=>'1110010','1'=>'1100110','2'=>'1101100','3'=>'1000010','4'=>'1011100',
            	'5'=>'1001110','6'=>'1010000','7'=>'1000100','8'=>'1001000','9'=>'1110100')
        	);
		
		$parities=array(
        '0'=>array('A','A','A','A','A','A'),
        '1'=>array('A','A','B','A','B','B'),
        '2'=>array('A','A','B','B','A','B'),
        '3'=>array('A','A','B','B','B','A'),
        '4'=>array('A','B','A','A','B','B'),
        '5'=>array('A','B','B','A','A','B'),
        '6'=>array('A','B','B','B','A','A'),
        '7'=>array('A','B','A','B','A','B'),
        '8'=>array('A','B','A','B','B','A'),
        '9'=>array('A','B','B','A','B','A')
		);
		
		$code='101';
		$p=$parities[$barcode{0}];
		for ($i=1;$i<=6;$i++) {
        	$code.=$codes[$p[$i-1]][$barcode{$i}];
		}
      	$code.='01010';
      	for ($i=7;$i<=12;$i++) {
        	$code.=$codes['C'][$barcode{$i}];
      	}
      	$code.='101';
		// Draw bars
      	for ($i=0;$i<strlen($code);$i++) {
        	if ($code{$i}=='1') {
            	$this->Rect($x+$i*$w,$y,$w,$h,'F');
        	}
		}
		// Omit text under barcode for RVM
		//$this->SetFont('Arial','',8);
		//$this->Text($x,$y-$h+(17/$this->k),substr($barcode,-$len).' '.$this->tagdate);
    }
}

function WEFC_Inventory_Items($data,$offset=0)
{
    global $dbc, $id;
    global $FANNIE_OP_DB;
    $dbc = FannieDB::get($FANNIE_OP_DB);

    // How did $id get to be an array?
    $id = FormLib::get_form_value('id',False);
   /*
    * SELECT shelfTagQueueID, description FROM ShelfTagQueues WHERE shelfTagQueueID = 303
        Parameters: ArrayUnknown column '303Array' in 'where clause'
    */
    $stQ = "SELECT shelfTagQueueID, description FROM ShelfTagQueues WHERE shelfTagQueueID = ?";
    $stP = $dbc->prepare($stQ);
    $args = array($id);
    $stR = $dbc->execute($stP,$args);
    if ($dbc->num_rows($stR)>0) {
        $stW = $dbc->fetch_array($stR);
        $qName = $stW['description'];
    } else {
        $qName = "No Name available";
    }

	$pdf=new WEFC_Inventory_Items_PDF('P','mm','Letter'); //start new instance of PDF
	//$pdf->AddFont('rothman');
	//$pdf->AddFont('steelfish');
	$pdf->Open(); //open new PDF Document
	$pdf->setTagDate(date("m/d/Y"));

	$width = 175; // tag width in mm
	$height = 18.0; // RVM Large 30.8
	//$height = 25.0; // WEFC ?
    //
	$left = 6.55 + 15; // left margin + 15 for 3-hole punch.
	//$top = 20; // top margin
	$top = 13; // top margin
	$top2 = 20; // top margin

    $tagsPerRow = 1;
    $rowsPerPage = 13; // RVM 8
    $tagsPerPage = ($tagsPerRow * $rowsPerPage);

	$pdf->SetTopMargin($top);  //Set top margin of the page
	$pdf->SetLeftMargin($left);  //Set left margin of the page
	$pdf->SetRightMargin($left);  //Set the right margin of the page
	$pdf->SetAutoPageBreak(False); // manage page breaks yourself
	$pdf->AddPage();  //Add page #1

	$num = 1; // count tags 
	$x = $left;
	$y = $top;

    if ($offset > 0 && $offset < $tagsPerPage) {
        while ($num < ($offset+1)) {
            // move right by tag width
            $x += $width;
            if ($num % $tagsPerRow == 0) {
                $x = $left;
                $y += $height;
            }
            $num++;
		}
    }

        // DIMS Dimensions
        // widths of columns.
        $wid1 = 20; // PLU/UPC
        $wid2 = 65; // Brand - Description
        $wid3 = 10; // Size
        $wid4 = 8; // UoM
        $wid5 = 20; // Quantity
        $wid6 = 35; // Barcode
        // X-offsets for columns.
        $xoff1 = 0; // PLU/UPC
        $xoff2 = $xoff1 + $wid1; // Brand - Description
        $xoff3 = $xoff2 + $wid2; // Size
        $xoff4 = $xoff3 + $wid3; // UoM
        $xoff5 = $xoff4 + $wid4; // Quantity Box
        $xoff6 = $xoff5 + $wid5; // Barcode
        // Y-offsets for lines
        $baseline = 12;
        $upper = ($baseline - 6);

        //
        $pageCount = 0;

        /*
        */
		$pdf->SetFont('Arial','',12);
        $pdf->SetXY($x, $y);
		$pdf->Cell($w, 0, "$qName", 0, 0, 'L');
        $pageCount++;
        $pdf->SetXY($x, $y);
		$pdf->Cell(175, 0, "p. $pageCount", 0, 0, 'R');
        //$top = $top2;
        $y = $top2;

	// cycle through result array of query
	foreach ($data as $row) {

		// extract & format data
		$price = $row['normal_price'];
		$desc = substr($row['description'],0,30);
		$brand = substr($row['brand'],0,30);
		$pak = $row['units'];
		$size = $row['size'];
        $uom = '';
        if (strpos($size,' ') > 0) {
            list($size,$uom) = explode(' ', $size,2);
        }
		$sku = ($row['sku']) ? '-'.substr($row['sku'],0,10) : '';
		$ppu = $row['pricePerUnit'];
        // Skip non-PLU's
        if (!preg_match("/^0{8}/",$row['upc'])) {
            continue;
        }
		$upc = ltrim($row['upc'],0);
		$check = $pdf->GetCheckDigit($upc);
		$vendor = substr($row['vendor'],0,7);
        $dept = ($row['dept_name']) ? strtoupper(substr($row['dept_name'],0,4)).'-'
            : '';

        /* Border around the item.
         */
		$pdf->Rect($x,$y,$width,$height);

        /* Y-axis starts from top at 0, higher numbers move it down.
         * X-axis starts from left at 0, higher numbers move it to the right.
         */        

		/* Per Unit Cost Numerical
        list($ppu_price, $ppu_unit) = explode('/', $ppu, 2);
        $dollars = '$';
        $cents = '';
        if ((float)$ppu_price < 1.00) {
            $cents = chr(162);
            $dollars = '';
            // Does this need sprintf()?
            $ppu_price = $ppu_price * 100;
        } else {
            $ppu_price = sprintf("%.2f", (float)$ppu_price);
        }
		$pdf->SetFont('steelfish', '', 44);
		$pdf->SetXY($x, $y+11.5); // 11May2015 was +9
		$pdf->Cell($w, 0, sprintf("{$dollars}%s{$cents}", $ppu_price), 0, 0, 'L');
         */

		/* Per Unit Cost Unit
		$pdf->SetFont('Arial','',12);
		$pdf->SetXY($x, $y+17);
		$pdf->MultiCell(30, 0,"/ $ppu_unit", 0, 0, 'R');
         */

		/* Price
		$pdf->SetFont('rothman','',78);
		$pdf->SetXY($x, $y+20);
		$pdf->Cell(66, 0, sprintf('$%.2f', $price), 0, 0, 'R');
         */

		// UPC
		$pdf->SetFont('Arial','',12);
        $pdf->SetXY($x, $y+$baseline);
        $plu = ltrim($upc,'0');
        if (strlen($plu) > 5) {
            $plu = substr($plu,-5,5);
        }
		$pdf->Cell($wid1, 0, $plu, 0, 0, 'C');

		// Brand
		$pdf->SetFont('Arial','',12);
		$pdf->SetXY($x+$xoff2, $y+$upper);
		$pdf->Cell($w, 0, $brand, 0, 0, 'L');

		// Description
		$pdf->SetFont('Arial','',12);
		$pdf->SetXY($x+$xoff2, $y+$baseline);
		$pdf->Cell($w, 0, $desc, 0, 0, 'L');

		// Size
		$pdf->SetFont('Arial','',12);
		$pdf->SetXY(($x+$xoff3+1.5), $y+$baseline);
		$pdf->Cell(($wid4+2), 0, $size, 0, 0, 'R');
		// #'u Unit of measure
		$pdf->SetFont('Arial','',12);
		$pdf->SetXY(($x+$xoff4-0), $y+$baseline);
		$pdf->Cell($w, 0, "$uom", 0, 0, 'L');

		/* #'q Quantity, a box.
         * Coordinates are for upper-left corner.
         */
		$pdf->Rect(($x+$xoff5),($y+2),($wid5-2),12);

		// Barcode
		//$newUPC = $upc . $check; //add check digit to upc
        /* generate barcode and place on label
         * Coordinates are for upper-left corner.
         * Maybe print PLU below, centered in white box.
         * $pdf->SetFillColor(255,255,255); // white background for Cell?
         * $pdf->SetDrawColor(128,0,0);
         */
        $pdf->SetFillColor(0,0,0);
		if (strlen($upc) <= 11) {
			$pdf->UPC_A($x+$xoff6,($y+2.5),$upc,11);
		} else {
			$pdf->EAN13($x+$xoff6,$y+$baseline,$upc,16);
        }

        // PLU, under the barcode
        $pdf->SetFillColor(255,255,255);
        $pdf->Rect(($x+$xoff6+10),($y+11.5),12,3,'F');
        $pdf->SetFillColor(0,0,0);
        /* */
		$pdf->SetFont('Arial','B',9);
        $pdf->SetXY(($x+$xoff6+11),($y+13.0));
        // First arg is width. Right edge of cell is that far from SetXY().
		$pdf->Cell(10, 0, $plu, 0, 0, 'C');

        // "Checkbox" for Entered:
        $pdf->Rect(($x+$xoff6+$wid6+3),($y+5.0),4.8,4.8);
		$pdf->SetFont('Arial','',8);
        $pdf->SetXY(($x+$xoff6+$wid6+0),($y+12.5));
		$pdf->Cell(10, 0, "Entered", 0, 0, 'C');

		// move right by tag width
		$x += $width;

		// if it's the end of a page, add a new
		// one and reset x/y top left margins
		// otherwise if it's the end of a line,
		// reset x and move y down by tag height
		if ($num % $tagsPerPage == 0 && count($data)!=$tagsPerPage) {
			$pdf->AddPage();
    		$x = $left;
    		$y = $top;
            /* Page Heading and page number*/
            $pdf->SetFont('Arial','',12);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 0, "$qName", 0, 0, 'L');
            $pageCount++;
            $pdf->SetXY($x, $y);
            $pdf->Cell(175, 0, "p. $pageCount", 0, 0, 'R');
            $y = $top2;
		} else if ($num % $tagsPerRow == 0) {
			$x = $left;
			$y += $height;
		}

		$num++;
	}

    $pdf->Output();  //Output PDF file to screen.
}
