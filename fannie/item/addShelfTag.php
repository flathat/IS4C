<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op

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

/* --COMMENTS - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

 * 22Jan2016 EL WEFC_Toronto blocks for not-strtoupper()
 *              description size=30
 *              Code HTML input values with " so embedded ' are preserved:
 *                brand, description, vendor
 *              Re-organized fields.
 *              "Shelf Tag Batch" instead of "Barcode page"
    * 25Mar2013 AT Merged changes between CORE and flathat
    * 21Mar2013 EL Hacked FANNIE_POUNDS_AS_POUNDS until established.
    *              Use input description width 30, not 27, OK per AT.
    * 16Mar2013 Eric Lee Need to get the vendor name either from the form
    *            or from, ideally, vendors, or prodExtra.
    *            Currently the vendor name input is just text, not controlled.
    *           It would be better if it used size and unitofmeasure from the form.
    *            In update, would need a post-update shelftag create as in insertItem.php

*/

require(dirname(__FILE__) . '/../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}
if (basename(__FILE__) != basename($_SERVER['PHP_SELF'])) {
    return;
}
$dbc = FannieDB::get($FANNIE_OP_DB);

$upc = BarcodeLib::padUPC(FormLib::get('upc'));
$product = new ProductsModel($dbc);
$product->upc($upc);
$product->load();
$tagData = $product->getTagData();

$prodQ = $dbc->prepare_statement("SELECT p.*,s.superID FROM products AS p
    LEFT JOIN MasterSuperDepts AS s ON p.department=s.dept_ID
    where upc=?");
$prodR = $dbc->exec_statement($prodQ,array($upc));
$prodW = $dbc->fetchRow($prodR);
$superID = $prodW['superID'];

$price = $tagData['normal_price'];
$desc = $tagData['description'];
$brand = $tagData['brand'];
$size = $tagData['size'];
$units = $tagData['units'];
$sku = $tagData['sku'];
$vendor = $tagData['vendor'];
$ppo = $tagData['pricePerUnit'];

?>
<!doctype html>
<html>
    <head>
        <title>Add Shelf Tag</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" type="text/css" href="../src/javascript/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="../src/javascript/bootstrap-default/css/bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="../src/javascript/bootstrap-default/css/bootstrap-theme.min.css">
        <script type="text/javascript" src="../src/javascript/jquery.js"></script>
        <script type="text/javascript" src="../src/javascript/bootstrap/js/bootstrap.min.js"></script>
        <script type="text/javascript">
        $(document).ready(function(){
            $('input.focus').focus();
        });
        </script>
    </head>
<body>
<div class="container-fluid">
<form method='post' action='addShelfTag1.php'>
<div class="form-group form-inline" style="margin-top:0.75em;">
<label>Brand</label>
    <input type='text' name='brand' maxlength=15 
        class="form-control"
<?php 
if (isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto') {
    echo "value=\"".$brand."\"";
} else {
    echo "value=\"".strtoupper($brand)."\"";
}
?>
>

    <label>Description</label>
    <input type='text' name='description' maxlength=30 size=30
        class="form-control focus"
<?php
if (isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto') {
    echo "value=\"".$desc."\"";
} else {
    echo "value=\"".strtoupper($desc)."\"";
}
?>
>
</div><!-- form group -->

<div class="form-group form-inline">
<label>Case size</label>
    <input type='text' name='units' size=5
        class="form-control"
<?php
echo "value='".$units."'";
?>
>

<label>Package Size</label>
<input type='text' name='size' size=10
    class="form-control"
<?php
echo "value='".$size."'";
?>
>
</div><!-- form group -->

<div class="form-group form-inline">
<label>Price</label>
<span class="alert-success h3">
    <strong><?php printf("%.2f",$price); ?></strong>
</span>
&nbsp; <label>PricePer</label>
<input type=text name=ppo
    class="form-control"
<?php echo "value=\"$ppo\"" ?> />
</div><!-- form group -->

<div class="form-group form-inline">
<label>Vendor</label>
<input type='text' name='vendor'
    class="form-control"
<?php
echo "value=\"$vendor\"";
?>
>
<label>SKU</label>
<input type='text' name='sku' size=8
    class="form-control"
<?php
echo "value='".$sku."'";
?>
>
</div><!-- form group -->

<div class="form-group form-inline">
<label># Tags</label>
<input type="text" name="count" size="3" value="1" 
    class="form-control" />

<label>Shelf Tag batch</label>
<select name=subID class="form-control">
<?php
$qm = new ShelfTagQueuesModel($dbc);
echo $qm->toOptions($superID);
?>
</select>
</div><!-- form group -->

<div class="form-group form-inline">
<button type="submit" class="btn btn-default"
    name="submit" value="New">Create Tag</button>
</div><!-- form group -->

<input type='hidden' name=upc value='<?php echo $upc; ?>'>
<input type='hidden' name='price' size=8 value=<?php echo $price; ?> />
</form>
</div>
</body>
</html>
