<?php
/*******************************************************************************

    Copyright 2011 Whole Foods Co-op
    Copyright 2013 West End Food Co-op, Toronto

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

/* This snippet is include()ed by e.g. ../genLabels.php
 * 
 */

/* TODO
 * Backport environment changes and/or notes to WEFC_No_Barcode
 * Proper Abbreviations of Flags and Vendor names.
 */

/* Fonts are probably in:
 * $FANNIE_ROOT.'src/fpdf/fpdf.php'
 */
if (!defined('FPDF_FONTPATH')) {
  define('FPDF_FONTPATH','font/');
}

if (!class_exists('FPDF')) {
    require($FANNIE_ROOT.'src/fpdf/fpdf.php');
    //require(dirname(__FILE__) . '/../../../src/fpdf/fpdf.php');
}

/*
    HOWTO layouts
    1. Make a file, e.g. New_Layout.php
    2. Make a PDF class New_Layout_PDF extending FPDF
       (just copy an existing one to get the UPC/EAN/Barcode
        functions)
       The contents of the class aren't needed if the label
        doesn't have barcodes.
       The include()ing script may get them anyway.
    3. Make a function New_Layout($data)
       $data is an array database rows containing:
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
       It is produced by a function defined in ProductsModel.php
        that may be overridden by a plugin.
    4. In your function, build the PDF. Look at 
       existing ones for some hints and/or FPDF documentation.

    Name matching is important
*/

class WEFC_No_Barcode_4_PDF extends FPDF {}

/* Based on WEFC_No_Barcode.
 * 4-up on 8.5x11" stock.
 * 9 rows, each ~1 1/8" (~30mm) high.
*/
function WEFC_No_Barcode_4_up($data,$offset=0) {

    global $FANNIE_OP_DB;
    global $FANNIE_COOP_ID;
    $dbc = FannieDB::get($FANNIE_OP_DB);

    $pdf=new WEFC_No_Barcode_4_PDF('P','mm','Letter'); //start new instance of PDF
    $pdf->SetTitle("WEFC No Barcode 4-up Shelf Labels",1); // Title, UTF-8 EL+
    // See $SRC/fpdf/font
    $pdf->AddFont('Scala','','Scala.php');
    $pdf->AddFont('Scala','B','Scala-Bold.php');
    $pdf->AddFont('ScalaSans','','ScalaSans.php');
    $pdf->AddFont('ScalaSans','B','ScalaSans-Bold.php');
    /*
    helveticab.php
    helvetica.php
    Scala-Bold.php
    Scala-Bold.z
    Scala-Italic.php
    Scala-Italic.z
    Scala.php
    ScalaSans-Bold.php
    ScalaSans-Bold.z
    ScalaSans-Italic.php
    ScalaSans-Italic.z
    ScalaSans.php
    ScalaSans.z
    Scala.z
    */

    $pdf->Open();
    // Set initial cursor position.
    //  Later X,Y settings are absolute, NOT relative to these.
    $pdf->SetTopMargin(15);
    $pdf->SetLeftMargin(3);
    $pdf->SetRightMargin(0);
    // Manage page breaks yourself
    $pdf->SetAutoPageBreak(False);
    // Start the first page
    $pdf->AddPage();


    /* x axis is horizontal, y is vertical
     * x=0,y=0 is top-left
    */
    /* $down is depth (height) of label, y-offset to the next label.
     * i.e. the distance to the same element on the next label down.
     * Includes any space between labels,
     *  so is NOT the height of the printing on the label or of the label stock.
     */
    $down = 30.5;
    /* $across is width
     * i.e. the distance to the same element on the next label across
     * width of label, x-offset to the next label.
     * Includes any space between labels (gutter),
     *  so is NOT the width of the printing on the label or of the label stock.
     */
    $across = 51.0;
    //$across = 103.0;

    // Distance from the edge of the paper to the first printable character.
    // This varies by printer.
    $printerMargin = 3; // 19Jan2016 was 3
    /* You may not need to change anything in the rest of this block
     *  once down, across and printerMargin above are defined.
    */
    $pageWidth = (8.5 * 25.4)-(2*$printerMargin); //209.9 For 8.5"
    $pageDepth = (11.0 * 25.4)-(2*$printerMargin); //273.4 For 11"
    $leftMargin = $pdf->GetX();
    $left = $leftMargin;
    $topMargin = $pdf->GetY();
    $top = $topMargin;
    $labelsPerRow = 1;
        while ((($labelsPerRow+1) * $across) <= ($pageWidth-$leftMargin))
            $labelsPerRow++;
    $rowsPerPage = 1;
        while ((($rowsPerPage+1) * $down) <= ($pageDepth-$topMargin))
            $rowsPerPage++;
    $labelsPerPage = $rowsPerPage*$labelsPerRow;
    // Right-most x of a field.  Larger offset implies need for a new line.
    $maxLeft = $left + (($labelsPerRow - 1)*$across);
    // Bottom-most y of a field.  Larger offset implies need for a new page.
    $maxTop = $top + (($rowsPerPage - 1)*$down);
    // End of definitions you may not need to change.

    /* Each '.' in the diagram below is starting place of a cell, i.e.
     *  the bottom-left, or base-line of the text.
     *  Letters are built to the right and up from this point.
     *  Text placed at 0,0 is not visible, but at 0,5 is at least partly visible.
     * Give it a name and assign its left=x=horizontal and top=y=vertical
     *  coordinates as offsets
     *  relative to the upper-left corner of the page as 0,0.
     * Not all of these may actually be used.
     * They are incremented to establish coordinates for the cell
     *  in subsequent labels in the row and in succeeding rows.
     * All initial left=x-coordinates are relative to the left edge of the page, not SetLeftMargin()
     * All initial top=y-coordinates are relative to the top edge of the page, not SetTopMargin()
     * These are in addition to the margins required by the printer.
     *
     * To specify a differnt style of label:
     * - draw a diagram
     * - change the values in the coords array to the starting point for each element
    */
    /*
     * 4-up
    +------------------------+
     .Brand - Flags .     sku|
     .Description            |
     .  PRICE     } .     Pkg|
     .  PRICE / lb} .     UPC|
     .PP/u   .vend  .   d/m/y|
    +------------------------+
     * 2-up
    +-------------------------------------------+
     .Brand - Flags                             |
     .Description                               |
     .          PRICE                  .     Pkg|
     .    PRICE / lb                   .     UPC|
     .PP/u       .vendor : sku         .   d/m/y|
    +-------------------------------------------+
    */
    $fontSizeSmallest = 7; // 2-up: 8
    // May be better to merge $fontSize with $coords.
    $fontSize = array();
    $fontSize['brand'] = 9; // 2-up: 10
    $fontSize['desc'] = 12; // 2-up: 18
    $fontSize['price'] = 24; // 2-up: 30
    $fontSize['ppu'] = $fontSizeSmallest;

    /* 4-up */
    $fontSize['vendor'] = $fontSizeSmallest;
    $fontSize['sku'] = $fontSizeSmallest;
    $fontSize['pkg'] = 10; // 2-up: 14
    $fontSize['itemID'] = $fontSizeSmallest;
    $fontSize['today'] = $fontSizeSmallest;

    $coords = array();
    $coords['brand'] = array('left'=>$left, 'top'=>$top);
    $coords['desc'] = array('left'=>$left, 'top'=>$top+7);
    $coords['price'] = array('left'=>$left, 'top'=>$top+16);
    $coords['ppu'] = array('left'=>$left, 'top'=>$top+23);

    /* 4-up */
    $coords['sku'] = array('left'=>$left+30, 'top'=>$top+0);
    $coords['vendor'] = array('left'=>$left+15, 'top'=>$top+23);
    $coords['pkg'] = array('left'=>$left+30, 'top'=>$top+13);
    $coords['itemID'] = array('left'=>$left+30, 'top'=>$top+19);
    $coords['today'] = array('left'=>$left+30, 'top'=>$top+23);

    /* 2-up
    $coords['vendor'] = array('left'=>$left+50, 'top'=>$top+23);
    $coords['pkg'] = array('left'=>$left+68, 'top'=>$top+13);
    $coords['itemID'] = array('left'=>$left+68, 'top'=>$top+19);
    $coords['today'] = array('left'=>$left+68, 'top'=>$top+23);
     */

    /* Find the name of the top, left cell in the label,
     *  lowest left and lowest top values.
     * You don't need to change this.
    */
    $firstCell = ""; $lastCell = "";
    $fi = 99999;
    $fj = 0; $fk = 0;
    foreach(array_keys($coords) as $key) {
        $fj = $coords["$key"]['left'] + $coords["$key"]['top'];
        if ($fj < $fi) {
            $fi = $fj;
            $firstCell = $key;
        }
        if ($fj > $fk) {
            $fk = $fj;
            $lastCell = $key;
        }
    }

    /* 'o If not starting to print on the first label of the page
     * move the cursor to the starting point.
     $labelsPerRow
     $rowsPerPage
    */
    if ($offset > 0) {
        $offsetRows=0;
        $offsetCols=0;
        $offsetRows = $offset / $labelsPerRow;
        $offsetRows = (int)$offsetRows;
        $offsetCols = $offset % $labelsPerRow;
        foreach(array_keys($coords) as $key) {
            $coords["$key"]['top'] += ($down*$offsetRows);
            $coords["$key"]['left'] += ($across*$offsetCols);
        }
    }

    // Make a local array of Product Flags
    $productFlags = array();
    $pQ = "SELECT bit_number, description FROM prodFlags";
    $pS = $dbc->prepare($pQ);
    $pR = $dbc->execute($pS,array());
    if ($pR) {
        while($pf = $dbc->fetch_row($pR)){
            // Scrunch
            // Crude abbreviation: capital letters only.
            $productFlags[$pf['bit_number']] = preg_replace("/[- a-z]/", "",$pf['description']);
            //$productFlags[$pf['bit_number']] = $pf['description'];
        }
    } else {
        $dbc->logger("Failed: $pQ");
    }

    /* 'h Page heading
    $pdf->SetFont('Arial','',10);
    // Is this placed at the initial settings of LeftMargin and TopMargin?
    //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
    $pdf->SetXY($leftMargin,($topMargin-5));
    $pdf->Cell(0,5, "offset: $offset offsetRows: $offsetRows offsetCols: $offsetCols ", 0);
    //$pdf->Cell(0,5, "rowsPerPage: $rowsPerPage maxTop: $maxTop ", 0);
    //$pdf->Cell(0,5, "firstCell: $firstCell fi: $fi lastCell: $lastCell fk: $fk ",0);
    //$pdf->Cell(0,5,"Top Left of Page maxLeft: $maxLeft maxLeft2: $maxLeft2 maxTop: $maxTop maxTop2: $maxTop2 ",1);
    //$pdf->Cell(0,0,"Top Left of Page leftMargin: $leftMargin from earlier GetX  topMargin: $topMargin from earlier GetY",1);
    */

    // Cycle through result array of query
    // There is one row for each label
    foreach($data as $row) {

        // If there's not room for this label in the row
        //  start another row.
        if($coords["$firstCell"]['left'] > $maxLeft){
            foreach(array_keys($coords) as $key) {
                $coords["$key"]['top'] += $down;
                $coords["$key"]['left'] -= ($across*$labelsPerRow);
            }
            // If there's not room on the page for the new row,
            //  start another page, at the top.
            if($coords["$firstCell"]['top'] > $maxTop) {
                $pdf->AddPage();
                foreach(array_keys($coords) as $key) {
                    $coords["$key"]['top'] -= ($down*$rowsPerPage);
                }
            }
        }

        // Prepare the data.
        $scale = $row['scale'];
        $price = '$'.$row['normal_price'];
        // Scrunched from " / lb"
        // May need to scrunch font size.
            $price .= ($scale==1)?"/lb":"";
        /* Why is it in caps to begin with?
         * 19Jan2016 EL $ITEM/addShelfTag.php no longer folds to upper
         *           for WEFC_Toronto
         */
        $desc = $row['description'];
        if ($desc == strtoupper($desc)) {
            $desc = ucwords(strtolower($desc));
        }
        // Remove "(BULK)", remove 1st char if "^[PB][A-Z][a-z]"
        $brand = $row['brand'];
        $pkg = $row['size'];
        // units is #/case, we don't want to display that.
        //$size = $row['units'] . "-" . $row['size'];
        $ppu = strtolower($row['pricePerUnit']);
        $upc = ltrim($row['upc'],0);
        $sku = ($row['sku'] == $upc) ? '' : $row['sku'];
        $tagdate = date('jMy');
        /* Scrunching for 4-up
         * Prefer vendorAbbreviation, but need data-getting plugin for that.
         */
        $vendor = substr($row['vendor'],0,10);
        /* 
            $vendor .= ($vendor && $sku) ? ':' : '';
            $vendor .= $sku;
         */
        // A string of Product Flags.
        $flagSet = "";
        $numflag = (int)$row['numflag'];
        if ($numflag !== 0) {
            $flags = array();
            for($fpt=0;$fpt<30;$fpt++) {
                if ((1<<$fpt) & $numflag) {
                    $bit_number = $fpt + 1;
                    $flags[] = '' . $productFlags[$bit_number];
                }
            }
            $flagSet = ' - ' . implode(' : ',$flags);
        }
        $itemID = '';
        // 19Jan2016 WEFC_Toronto no longer uses order codes.
        if (False && isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto') {
            $oQ = "SELECT order_code, description
                           FROM products_$FANNIE_COOP_ID WHERE upc = ?";
            $oS = $dbc->prepare($oQ);
            $oV = array($row['upc']);
            $oR = $dbc->execute($oS,$oV);
            if ($oR) {
               while ($oRow = $dbc->fetch_row($oR)) {
                   // Override the one from products.
                   if ($oRow['description'] != '')
                       $desc = $oRow['description'];
                   if (ctype_digit($oRow['order_code'])) {
                       $itemID = 'ORDER #'.$oRow['order_code'];
                       break;
                   }
               }
            } else {
               $dbc->logger("Failed: $oQ with {$oV[0]}");
            }
        }
        if ($itemID == '' && $upc != '') {
           $itemID = "$upc";
           //$itemID = "UPC $upc";
        }
        
        // Further massaging.
        $brand .= $flagSet;
        // Scrunch
        $maxBrandWidth = 30; // If Vendor on same line: 30
        //$maxBrandWidth = 90; // If Vendor on same line: 65
        $pdf->SetFont('Arial','',$fontSize['brand']);
        $i=0;
        while ($i<10 && ($pdf->GetStringWidth($brand) > $maxBrandWidth) && strlen($brand) > 20) {
            $brand = substr($brand,0,-1);
            $i++;
        }

        // Scrunch
        $maxDescWidth = 45;
        //$maxDescWidth = 90;
        $pdf->SetFont('ScalaSans','B',$fontSize['desc']);
        $i=0;
        while ($i<10 && ($pdf->GetStringWidth($desc) > $maxDescWidth) && strlen($desc) > 20) {
            $desc = substr($desc,0,-1);
            $i++;
        }

        /* Start putting out a label 
        * For each element:
        *  1. Define the font if different than the current one.
        *  2. Move the cursor to the x,y starting point,
        *      unless it continues after the previous element.
        *     The elements can be put out in any order.
        *  3. Set attributes such as border, text alignment
        *  4. Stream the text. The command may include #2 and #3.
        */

        $cell = 'brand';
        $pdf->SetFont('Arial','',$fontSize["$cell"]);
        // A line above
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']-4);
        $pdf->Cell(($across-3),0," ",'T',0,'L');
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        $pdf->Cell(0,0,"$brand",'',0,'L');

        $cell = 'desc';
        $pdf->SetFont('ScalaSans','B',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        $pdf->Cell(0,0,"$desc",0,0,'L');

        $cell = 'price';
        $pdf->SetFont('Arial','B',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        // Scrunch
        $pdf->Cell(33,0,"$price",0,0,'C'); // 2-up width: 67
        //$pdf->Cell(67,0,"$price",0,0,'C');

        $cell = 'ppu';
        $pdf->SetFont('Arial','',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        $pdf->Cell(0,0,"$ppu",0,0,'L');

        /* Vendor on bottom
         */
        $cell = 'vendor';
        $pdf->SetFont('Arial','',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        $pdf->Cell(0,0,"$vendor",0,0,'L');

        /* SKU on top
        */
        $cell = 'sku';
        $pdf->SetFont('Arial','',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        $pdf->Cell(18,0,"$sku",0,0,'R');

        $cell = 'pkg';
        $pdf->SetFont('Arial','B',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        // Scrunch. Could width 0 be used.
        //  $left + $width == right-margin. So c/b formula.
        $pdf->Cell(18,0,"$pkg",0,0,'R'); // 2-up width: 30

        $cell = 'itemID';
        $pdf->SetFont('Arial','',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        // Scrunch
        $pdf->Cell(18,0,"$itemID",0,0,'R'); // 2-up width: 30

        $cell = 'today';
        $pdf->SetFont('Arial','',$fontSize["$cell"]);
        $pdf->SetXY($coords["$cell"]['left'],$coords["$cell"]['top']);
        //    Cell(width, line-height,content,no-border,cursor-position-after,text-align)
        // Scrunch
        $pdf->Cell(18,0,"$tagdate",0,0,'R'); // 2-up width: 30

        // Increment the cursor coordinates for each cell to the next label to the right.
        //  The need to move down the page is handled later.
        foreach(array_keys($coords) as $key) {
            $coords["$key"]['left'] += $across;
        }

    // each label
    }

    $pdf->Output();  //Output PDF file to browser PDF handler.

// WEFC_No_Barcode_4_up()
}

?>
