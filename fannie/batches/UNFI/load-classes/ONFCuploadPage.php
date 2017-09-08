<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
/* TODO
 */

include(dirname(__FILE__) . '/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class ONFCuploadPage extends \COREPOS\Fannie\API\FannieUploadPage {

    public $themed = true;
    public $title = "Fannie - ONFC Prices";
    public $header = "Upload ONFC price file";
    public $description = '[ONFC Catalogue Import] specialized vendor import tool.
        Column choices default to ONFC price file layout.';

    protected $vendorName = 'ONFC';
    protected $results_details = '';
    protected $problem_details = '';
    protected $use_splits = false;
    // $use_js was true
    protected $use_js = false;

    // 'default' is column-number, where A=0.
    protected $preview_opts = array(
        'sku' => array(
            'name' => 'sku',
            'display_name' => 'SKU *',
            'default' => 0,
            'required' => true
        ),
        'upc' => array(
            'name' => 'upc',
            'display_name' => 'UPC *',
            'default' => 1,
            'required' => True
        ),
        'brand' => array(
            'name' => 'brand',
            'display_name' => 'Brand *',
            'default' => 2,
            'required' => True
        ),
        'category' => array(
            'name' => 'category',
            'display_name' => 'Category *',
            'default' => 3,
            'required' => True
        ),
        'desc' => array(
            'name' => 'desc',
            'display_name' => 'Description *',
            'default' => 4,
            'required' => True
        ),
        'qty' => array(
            'name' => 'qty',
            'display_name' => 'Case Size *',
            'default' => 5,
            'required' => True
        ),
        'size' => array(
            'name' => 'size',
            'display_name' => 'Item Size',
            'default' => 6,
            'required' => False
        ),
        'single' => array(
            'name' => 'single',
            'display_name' => 'Single Cost (Reg) *',
            'default' => 7,
            'required' => True
        ),
        'cost' => array(
            'name' => 'cost',
            'display_name' => 'Case Cost (Reg) *',
            'default' => 8,
            'required' => True
        ),
        'saleTerms' => array(
            'name' => 'saleTerms',
            'display_name' => 'Sale Terms',
            'default' => 9,
            'required' => false
        ),
        'saleCost' => array(
            'name' => 'saleCost',
            'display_name' => 'Case Cost (Sale)',
            'default' => 10,
            'required' => false
        ),
        'saleDetails' => array(
            'name' => 'saleDetails',
            'display_name' => 'Sale Details',
            'default' => 11,
            'required' => false
        ),
        'cataloguePage' => array(
            'name' => 'cataloguePage',
            'display_name' => 'ONFC Catelogue Page #',
            'default' => 12,
            'required' => false
        ),
        'flags' => array(
            'name' => 'flags',
            'display_name' => 'Flags',
            'default' => 13,
            'required' => false
        ),
        'storage' => array(
            'name' => 'storage',
            'display_name' => 'Storage',
            'default' => 14,
            'required' => false
        ),
        'taxable' => array(
            'name' => 'taxable',
            'display_name' => 'Taxable',
            'default' => 15,
            'required' => false
        ),
    );

    /* New in ONFC
     */
    protected $dry_run = 0;
    protected $update_products = 0;
    protected $create_sale_batch = 0;
    protected $sale_batch_name = '';
    protected $create_price_batch = 0;
    protected $price_batch_name = '';

    public function preprocess()
    {
         /*
        $ret = true;
        if ($ret) {
        }
         */
        /* Wish I understood the significance of the '\' before FormLib
         *  since most pages don't use it.
         */
        $this->dry_run = \FormLib::get_form_value('dry_run',0);
        $this->update_products = \FormLib::get_form_value('update_products',0);
        $this->create_sale_batch = \FormLib::get_form_value('create_sale_batch',0);
        $this->sale_batch_name = \FormLib::get_form_value('sale_batch_name','');
        $this->create_price_batch = \FormLib::get_form_value('create_price_batch',0);
        $this->price_batch_name = \FormLib::get_form_value('price_batch_name','');

        /* Cannot do this before assigning other vars because it calls process_file(),
         * i.e. everything will be done before it the form-based values of those vars
         * to use.
         */
        $ret = parent::preprocess();
        return $ret;

    // preprocess
    }

    function process_file($linedata)
    {
        global $FANNIE_OP_DB;
        //$this->results_details = "Begin process_file()";
        $dbc = FannieDB::get($FANNIE_OP_DB);
        //$dbc = FannieDB::get($this->config->get('OP_DB'));
        $idQ = "SELECT vendorID FROM vendors WHERE vendorName=? ORDER BY vendorID";
        //$idQ = "SELECT vendorID FROM vendors WHERE vendorName='ONFC' ORDER BY vendorID";
        $idP = $dbc->prepare($idQ);
        $args = array("{$this->vendorName}");
        $idR = $dbc->execute($idP,$args);
        //$idR = $dbc->execute($idP);
        if ($dbc->num_rows($idR) == 0){
            $this->error_details = "Cannot find vendor: {$this->vendorName}";
            return False;
        }
        $idW = $dbc->fetchRow($idR);
        $VENDOR_ID = $idW['vendorID'];
        //$this->results_details .= "<br />VENDOR_ID: $VENDOR_ID";

        $today = date('y-m-d');
        // All ONFC items on sale.
        $saleItems = 0;
        // Items in the sale batch.
        $saleBatchItems = 0;
        $saleItemList = array();
        /*
        -- BatchesModel
        'batchID' => array('type'=>'INT', 'primary_key'=>True, 'increment'=>True),
        'startDate' => array('type'=>'DATETIME'),
        'endDate' => array('type'=>'DATETIME'),
        'batchName' => array('type'=>'VARCHAR(80)'),
        'batchType' => array('type'=>'SMALLINT'),
        'discountType' => array('type'=>'SMALLINT'),
        'priority' => array('type'=>'INT'),
        'owner' => array('type'=>'VARCHAR(50)'),
        'transLimit' => array('type'=>'TINYINT', 'default'=>0),
        -- BatchListModel
        'listID' => array('type'=>'INT', 'primary_key'=>True, 'increment'=>True),
        'upc' => array('type'=>'VARCHAR(13)','index'=>True),
        'batchID' => array('type'=>'INT','index'=>True),
        'salePrice' => array('type'=>'MONEY'),
        'groupSalePrice' => array('type'=>'MONEY'),
        'active' => array('type'=>'TINYINT'),
        'pricemethod' => array('type'=>'SMALLINT','default'=>0),
        'quantity' => array('type'=>'SMALLINT','default'=>0),
        'signMultiplier' => array('type'=>'TINYINT', 'default'=>1),
         */
        /* #'F
         * Add to batch if:
         * item has a $sale_unit (cost)
         * AND products.upc AND products.default_vendor_id
         * ?AND products.cost > $sale_unit
         * Need to calculate a price:
         * - get department from products
         * - get deptMargin.margin .dept_ID = products.department
         * - calculate price from cost and margin: cost/(1-margin)
         * */
        if ($this->create_sale_batch) {
            $sb = new BatchesModel($dbc);
            if (strlen($this->sale_batch_name) > 50) {
                $this->sale_batch_name = substr($this->sale_batch_name,0,50);
            }
            $sb->batchName($this->sale_batch_name);
            $batchStartDate = $today . ' 00:00:00';
            $sb->startDate($batchStartDate);
            $batchEndDate = $today . ' 00:00:00';
            $sb->endDate($batchEndDate);
            $batchType = 2;
            $sb->batchType($batchType);
            $batchDiscountType = 2;
            $sb->discountType($batchDiscountType);
            $batchPriority = 0;
            $sb->priority($batchPriority);
            $batchOwner = 'RETAIL';
            $sb->owner($batchOwner);
            $saleBatchID = $sb->save();
            $this->results_details .= "<p>Created Sale Batch $saleBatchID : {$this->sale_batch_name}</p>";

            /* #'G 10
            $pdmArgs = array($upc,$VENDOR_ID);
            $pdmR = $dbc->execute($pdmS,$pdmArgs);
            if ($dbc->num_rows($pdmR) > 0) {
                $pdmRow = $dbc->fetchRow($pdmR);
                $saleItemMargin = $pdmRow['margin'];
                $saleItemNormalPrice = $pdmRow['normal_price'];
            }
            if ($saleItemMargin != 'NULL' && $saleItemMargin > 0) {
                $saleItemPrice = ($sale_unit / (1-$saleItemMargin));
            }
             */

        /* I don't know how important these are. From $BATCHES/BatchFromSearch.php
            if ($this->config->get('STORE_MODE') === 'HQ') {
                StoreBatchMapModel::initBatch($batchID);
            }

            if ($dbc->tableExists('batchowner')) {
                $insQ = $dbc->prepare("insert batchowner values (?,?)");
                $insR = $dbc->execute($insQ,array($batchID,$owner));
            }
         */

         /* add items to batch
        for($i=0; $i<count($upcs); $i++) {
            $upc = $upcs[$i];
            $price = isset($prices[$i]) ? $prices[$i] : 0.00;
            $list = new BatchListModel($dbc);
            $list->upc(BarcodeLib::padUPC($upc));
            $list->batchID($batchID);
            $list->salePrice($price);
            $list->groupSalePrice($price);
            $list->active(0);
            $list->pricemethod(0);
            $list->quantity(0);
            $list->save();
        }
         */

        } else {
            //$this->results_details .= "<p>No Sale Batch | name:{$this->sale_batch_name}:</p>";
            $noop = 1;
        }

        /* May be needed regardless of doing sales batch. */
        $pdmQ = "SELECT margin, brand, description, size, unitofmeasure,
              cost, normal_price
            FROM products p
            LEFT JOIN deptMargin m on m.dept_ID = p.department
            WHERE upc = ? AND default_vendor_id = ?";

        $pdmS = $dbc->prepare($pdmQ);
        /* The values for $CAPS will be false if the index doesn't exist.
         * Some falses s/b show-stoppers.
         */
        $SKU = $this->get_column_index('sku');
        $UPC = $this->get_column_index('upc');
        $BRAND = $this->get_column_index('brand');
        $CATEGORY = $this->get_column_index('category');
        $DESCRIPTION = $this->get_column_index('desc');
        $QTY = $this->get_column_index('qty');
        $SIZE1 = $this->get_column_index('size');
        // item regular cost
        $COST1 = $this->get_column_index('single');
        // case regular cost
        $REG_COST = $this->get_column_index('cost');
        $SALE_TERMS = $this->get_column_index('saleTerms');
        // case sale cost
        $SALE_COST = $this->get_column_index('saleCost');
        $SALE_DETAILS = $this->get_column_index('saleDetails');
        $CATALOGUE_PAGE = $this->get_column_index('cataloguePage');
        $FLAGS = $this->get_column_index('flags');
        $STORAGE = $this->get_column_index('storage');
        $TAXABLE = $this->get_column_index('taxable');

        /* PLU items have different internal UPCs
         * map vendor SKUs to the internal PLUs
         */
        $SKU_TO_PLU_MAP = array();
        $skusQ = 'SELECT sku, upc FROM vendorSKUtoPLU WHERE vendorID=?';
        $skusP = $dbc->prepare($skusQ);
        $skusR = $dbc->execute($skusP, array($VENDOR_ID));
        while($skusW = $dbc->fetch_row($skusR)) {
            $SKU_TO_PLU_MAP[$skusW['sku']] = $skusW['upc'];
        }

        /* Vendor Departments = Categories = Subcategories
         * Map values in Column D Categories to vendorDepartments.
         * Assign vendorItems.vendorDept
        if (array_key_exists($CATEGORY_TO_DEPT_MAP,$x[$CATEGORY])) {
        }
         */
        $CATEGORY_TO_DEPT_MAP = array();
        $vdepQ = 'SELECT deptID, name FROM vendorDepartments WHERE vendorID=?';
        $vdepP = $dbc->prepare($vdepQ);
        $vdepR = $dbc->execute($vdepP, array($VENDOR_ID));
        $ctdm = count($CATEGORY_TO_DEPT_MAP);
        while($vdepW = $dbc->fetch_row($vdepR)) {
            $CATEGORY_TO_DEPT_MAP[$vdepW['name']] = $vdepW['deptID'];
            $ctdm++;
        }
        //$this->results_details .= "<p>Loaded CATEGORY_TO_DEPT_MAP: $ctdm </p>";
        $this->results_details .= "<p>Loaded $ctdm Vendor Category to Fannie Dept. Maps</p>";

        $extraP = $dbc->prepare("UPDATE prodExtra
            SET cost=?,
            case_cost=?,
            case_quantity=?
            WHERE upc=?");
        $prodQ ='UPDATE products
            SET cost=?,
                numflag= numflag | ? | ?,
                modified=' . $dbc->now() . '
            WHERE upc=?
                AND default_vendor_id=?';
        $prodP = $dbc->prepare($prodQ);
        $vendorItemPupdateQ = "UPDATE vendorItems SET sku=?, units=?, cost =?, " .
            "vendorDept=?, " .
            "modified=" . $dbc->now() .
            " WHERE upc =? AND vendorID =?";
        $vendorItemPupdate = $dbc->prepare($vendorItemPupdateQ);
        //$vendorItemPupdateQ = "UPDATE vendorItems SET srp=?, saleCost =? WHERE upc =? AND vendorID =?";
        $vendorItemPinsertQ = "INSERT INTO vendorItems (
                brand, 
                sku,
                size,
                upc,
                units,
                cost,
                description,
                vendorDept,
                vendorID,
                saleCost,
                srp,
                modified
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" . 
            ")";
        $vendorItemPinsert = $dbc->prepare($vendorItemPinsertQ);
        $srpP = false;
        if ($dbc->tableExists('vendorSRPs')) {
            $srpP = $dbc->prepare("INSERT INTO vendorSRPs (vendorID, upc, srp) VALUES (?,?,?)");
        }
        $updated_upcs = array();
        $rounder = new \COREPOS\Fannie\API\item\PriceRounder();

        $non_empty_lines = 0;
        $no_upc = 0;
        $bad_upc_pattern = 0;
        $could_not_fix_upc = 0;
        $no_cost = 0;
        $non_numeric_prices = 0;
        $all_zero_upc = 0;
        $itf14_count = 0;
        $odd_pattern_count = 0;
        $itemsFound = 0;
        $itemsNotFound = 0;
        $sku_different = 0;
        $new_cost_higher = 0;
        $new_cost_lower = 0;
        $new_cost_same = 0;

        //#'l
        foreach($linedata as $data) {
//if ($itemsNotFound > 10 || $itemsFound > 10) break; //if ($itemsNotFound > 100 || $itemsFound > 20000) break;
// if ($saleBatchItems > 10) break;
//break;

            if (!is_array($data)) {
                continue;
            }

            if (!isset($data[$UPC])) {
                continue;
            }
            if (trim($data[$UPC]) == "") {
                continue;
            }

            // grab data from appropriate columns
            $sku = ($SKU !== false) ? $data[$SKU] : '';
            //$sku = str_pad($sku, 7, '0', STR_PAD_LEFT);
            $brand = $data[$BRAND];
            $description = $data[$CATEGORY] . ' ' . $data[$DESCRIPTION];
            $qty = $data[$QTY];
            $size = ($SIZE1 !== false) ? $data[$SIZE1] : '';
            $prodInfo = ($FLAGS !== false) ? $data[$FLAGS] : '';
            $flag = 0;

            $non_empty_lines++;

            /* upc
             */
            $upc = trim($data[$UPC]);
            // "no upc" is bulk. Ignore for the moment but has SKU
            if ($upc == "no upc") {
                $no_upc++;
                continue;
            }
            $upc = preg_replace("/[^-0-9]/",'',$upc);
            $upc2 = $upc;
            /* 
             * Test for the normal pattern.
             * Later, report or even try to fix if they seem to be errors.
             */
            $upcFixed = 0;
            if (! preg_match("/^\d-\d{5}-\d{5}-\d$/",$upc)) {
                $upc1 = str_replace('-',"",$upc);
                // ITF-14 container UPCs
                if (strlen($upc1) == 14) {
                    $itf14_count++;
                    $upc = substr($upc1,0,-1);
                // Odd hyphenation of normal UPCs
                } elseif (strlen($upc1) == 12) {
                    $odd_pattern_count++;
                    $upc = substr($upc1,0,-1);
                } else {
                    $bad_upc_pattern++;
                    $this->problem_details .= "<br />UPC problem: $upc $brand $description";
                    // Try to fix it
                    $first = ""; $second = ""; $third = ""; $fourth = "";
                    /* A few instances of "Undefined offset".
                     * Maybe should assume all there? Don't really understand the message.
                     */
                    list($first,$second,$third,$fourth) = explode('-',$upc);
                    $first = ltrim($first,'0');
                    if (strlen($first) == 6 && strlen($second) == 5 && empty($fourth)) {
                        $upcFixed = 1;
                        $fourth = $third;
                        $third = $second;
                        $second = substr($first,1);
                        $first = substr($first,0,1);
                    }
                    if (strlen($third) == 6 && empty($fourth)) {
                        $upcFixed = 1;
                        $fourth = substr($third,-1,1);
                        $third = substr($third,0,5);
                    }
                    while (strlen($second) > 5 && substr($second,0,1) == '0') {
                        $upcFixed = 1;
                        $second = substr($second,1);
                    }
                    while (strlen($third) > 5 && substr($third,0,1) == '0') {
                        $upcFixed = 1;
                        $third = substr($third,1);
                    }
                    if (strlen($first) > 1 || strlen($second) != 5 || strlen($third) != 5) {
                        $this->problem_details .= "<br /> Could not fix $upc as $first : $second : $third";
                        $could_not_fix_upc++;
                        continue;
                    }
                    $upc = sprintf("%s%05d%05d",$first,$second,$third);
                }
            } else {
                $upc = str_replace('-',"",$upc);
                $upc = substr($upc,0,-1);
                $noop =1;
            }
            $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
            // zeroes isn't a real item, skip it. A UNFI convention?
            if ($upc == "0000000000000") {
                $all_zero_upc++;
                continue;
            }

            $upc2 = str_replace('-',"",$upc2);
            $upc2 = substr($upc2,0,-1);
            $upc2 = str_pad($upc2, 13, '0', STR_PAD_LEFT);

            if (isset($SKU_TO_PLU_MAP[$sku])) {
                $upc = $SKU_TO_PLU_MAP[$sku];
                if (substr($size, -1) == '#' && substr($upc, 0, 3) == '002') {
                    $qty = trim($size, '# ');
                    $size = '#';
                } elseif (substr($size, -2) == 'LB' && substr($upc, 0, 3) == '002') {
                    $qty = trim($size, 'LB ');
                    $size = 'LB';
                }
            }

            $size = str_replace('grams','g',$size);

            /* The value in vendorItems.vendorDept is an integer.
             * ONFC Category is a string
            $category = $data[$CATEGORY];
             */
            $vendorDept = (array_key_exists($data[$CATEGORY],$CATEGORY_TO_DEPT_MAP))
                ? $CATEGORY_TO_DEPT_MAP[$data[$CATEGORY]]
                : 0;
            $regCase = trim($data[$REG_COST]);
            $reg1 = trim($data[$COST1]);
            $saleCase = ($SALE_COST !== false) ? trim($data[$SALE_COST]) : 0.00;
            if (! preg_match("/^\d{1,2}\%$/",$data[$SALE_TERMS])) {
                $saleCase = 0.00;
            }
            // blank spreadsheet cell
            if (empty($saleCase)) {
                $saleCase = 0;
            }
            // can't process items w/o cost (usually promos/samples anyway)
            $regTest = "{$regCase}{$reg1}";
            if (empty($regTest)){
                $no_cost++;
                continue;
            }
            $srp = "0.00";
            /*
             * $srp = trim($data[$SRP]);
             * can't process items w/o price (usually promos/samples anyway)
             * EL Not sure if $regCase may be legitimately empty since $reg1
             *    might exist instead.
            if (empty($regCase) or empty($srp)) {
                continue;
            }
             */

            // syntax fixes. kill apostrophes in text fields,
            // trim $ off amounts as well as commas for the
            // occasional > $1,000 item
            //$brand = str_replace("'","",$brand);
            //$description = str_replace("'","",$description);
            $regCase = str_replace('$',"",$regCase);
            $regCase = str_replace(",","",$regCase);
            $reg1 = str_replace('$',"",$reg1);
            $reg1 = str_replace(",","",$reg1);
            $saleCase = str_replace('$',"",$saleCase);
            $saleCase = str_replace(",","",$saleCase);
            $srp = str_replace('$',"",$srp);
            $srp = str_replace(",","",$srp);

            // sale price isn't really a discount
            // -> How to work $reg1 into this?
            if ($regCase == $saleCase) {
                $saleCase = 0;
            }

            /* skip the item if prices aren't numeric
             * this will catch the 'label' line in the first CSV split
             * since the splits get returned in file system order,
             * we can't be certain *when* that chunk will come up
             * if (!is_numeric($regCase) or !is_numeric($srp)) {}
             */
            if (!is_numeric($regCase) && !is_numeric($reg1)) {
                $non_numeric_prices++;
                continue;
            }

            //$srp = $rounder->round($srp);

            // set organic flag on OG1 (100%) or OG2 (95%)
            $organic_flag = 0;
            //if (strstr($prodInfo, 'OG2') || strstr($prodInfo, 'OG1')) {}
            if (strstr($prodInfo, 'O')) {
                $organic_flag = 17;
            }
            // set gluten-free flag on g
            $gf_flag = 0;
            if (strstr($prodInfo, 'Gf')) {
                $gf_flag = 18;
            }

            // need unit cost, not case cost
            $reg_unit = sprintf("%.2f",(!empty($reg1)) ? $reg1 : ($regCase / $qty));
            $sale_unit = sprintf("%.2f",($saleCase / $qty));

            // Pass 1 false, Pass 2 true
            if (true && $this->update_products && !$this->dry_run) {
                // Update prodExtra and products
                $dbc->execute($extraP, array($reg_unit,$regCase, $qty, $upc));
                $dbc->execute($prodP, array($reg_unit,$organic_flag,$gf_flag,$upc,$VENDOR_ID));
            }
            $updated_upcs[] = $upc;

            //$findVendorItemQ = 'SELECT upc,sku,cost,vendorID FROM vendorItems where sku =? AND vendorID =?';
            $findVendorItemQ = 'SELECT upc,sku,cost,vendorID FROM vendorItems where upc =? AND vendorID =?';
            $findVendorItemP = $dbc->prepare($findVendorItemQ);
            //$findVendorItemR = $dbc->execute($findVendorItemP,array($sku,$VENDOR_ID));
            $findVendorItemR = $dbc->execute($findVendorItemP,array($upc,$VENDOR_ID));
            if ($dbc->num_rows($findVendorItemR) != 0){
                $itemsFound++;
//$this->results_details .= "<br />Found: $upc $description";
                // Pass 1 false, pass 2 true
                if (true) {
                /*$vendorItemPupdateQ = "UPDATE vendorItems SET sku=?, units=?, cost =?, modified =?
                 * WHERE upc =? AND vendorID =?";
                    $brand, 
                    $size === false ? '' : $size,
                    $upc,
                    $description,
                    $vendorDept,
                    $VENDOR_ID,
                    $sale_unit,
                    $srp
                    date('Y-m-d H:i:s'),
                 */
                $argsUpdate = array(
                    $sku === false ? '' : $sku, 
                    $qty,
                    $reg_unit,
                    $vendorDept == 0 ? null : $vendorDept,
                    $upc,
                    $VENDOR_ID
                );
                }
                // fetchRow is alias fetchArray()
                $fir = $dbc->fetchRow($findVendorItemR);
                if ($fir['sku'] != $sku) {
                    $sku_different++;
//$this->results_details .= "<br />SKU: vendorItems: {$fir['sku']} ONFC: $sku ";
                    if (strpos($fir['sku'],'dup') > 0 ) {
                        $sku = $fir['sku'];
                    }
                }
                if ($fir['cost'] < $reg_unit) {
                    $new_cost_higher++;
                } elseif ($fir['cost'] > $reg_unit) {
                    $new_cost_lower++;
                } else {
                    $new_cost_same++;
                }
                /* Only for cost study
                $argsUpdate = array(
                    $reg_unit,
                    $sale_unit,
                    $upc,
                    $VENDOR_ID
                );
                 */
                // Pass 1 false, pass 2 true
                if (true && !$this->dry_run) {
                    $ok = $dbc->execute($vendorItemPupdate,$argsUpdate);
                    if (!$ok) {
                        $this->results_details .= "<br />vI update failed";
                        break;
                    }
                }

                /*
                 * if ($this->create_sale_batch && $sale_unit > 0) {}
                 * There is a report line, what-if that may be wanted even
                 *  if the sale batch isn't.
                if ($this->create_sale_batch && $sale_unit > 0) {}
                 */
                if ($sale_unit > 0) {
                    $saleItems++;
                    $pdmArgs = array($upc,$VENDOR_ID);
                    $saleItemMargin = 0.00;
                    $pdmR = $dbc->execute($pdmS,$pdmArgs);
                    if ($dbc->num_rows($pdmR) > 0) {
                        $pdmRow = $dbc->fetchRow($pdmR);
                        $saleItemMargin = $pdmRow['margin'];
                        $saleItemNormalPrice = $pdmRow['normal_price'];
                    }
                    if ($saleItemMargin != 'NULL' && $saleItemMargin > 0) {
                        $saleBatchItems++;
                        $saleItemPrice = ($sale_unit / (1-$saleItemMargin));
                        $saleItemPrice = sprintf('%.2f', $saleItemPrice);
                        //$saleItemPrice = $rounder->round($saleItemPrice);
                        $saleItemList[] = sprintf("%s %s %s %s%s current: %s sale: %s",
                            $upc,
                            $pdmRow['brand'],
                            $pdmRow['description'],
                            $pdmRow['size'],
                            $pdmRow['unitofmeasure'],
                            $pdmRow['normal_price'],
                            $saleItemPrice
                        );
                        if ($this->create_sale_batch && ! $this->dry_run) {
                            /* Write the salesbatchitem
                             */
                            $sbli = new BatchListModel($dbc);
                            $sbli->upc($upc);
                            $sbli->batchID($saleBatchID);
                            $sbli->salePrice($saleItemPrice);
                            $sbli->groupSalePrice($saleItemPrice);
                            $sbli->active(0);
                            $sbli->pricemethod(0);
                            $sbli->quantity(0);
                            //$sbli->signMultiplier(1);
                            $sbli->save();
                        } else {
                            $noop = 1;
                        }
                    }
                }

            } else {
                $itemsNotFound++;
                if ($upc != $upc2 && ! $upcFixed) {
//$this->results_details .= "<br />Not: upc: $upc upc2: $upc2 $description";
                    $noop = 1;
                }
                /*Input array does not match ?:
                 * 'Annie Chun\'s'
                    'ACH001'
                    '155 g'
                    '0076566710110'
                    '6'
                    '4.22'
                    'Soup Bowls Miso Soup'
                    'Soup Bowls'
                    '2'
                    '0.00'
                    '0.00'
                    '2016-04-01 22:17:57'
                 */
                $argsInsert = array(
                    $brand, 
                    $sku === false ? '' : $sku, 
                    $size === false ? '' : $size,
                    $upc,
                    $qty,
                    $reg_unit,
                    $description,
                    $vendorDept == 0 ? null : $vendorDept,
                    $VENDOR_ID,
                    $sale_unit,
                    $srp,
                   date('Y-m-d H:i:s'),

                );
                // Pass 1 false, pass 2 true
                //   date('Y-m-d H:i:s'),
                if (true && !$this->dry_run) {
                    $ok = $dbc->execute($vendorItemPinsert,$argsInsert);
                    if (!$ok) {
                        $this->results_details .= "<br />vI insert failed";
                        break;
                    }
                }
            }

            /*
             * Defeat during ONFC development.
             * ONFC data doesn't have srp anyway.
             * Prefer to run Recalculate SRPs after the load.
            if (false && $srpP && !$this->dry_run) {
                $dbc->execute($srpP,array($VENDOR_ID,$upc,$srp));
            }
             */

        // each row of spreadsheet data
        }

        if (!empty($this->problem_details)) {
            $this->results_details .=
                '<p><br /><strong><a href="" onclick=\'$("#prob_details").toggle(); return false;\'>
                Data Problems:</a></strong> ' .
                ': (click to reveal/hide)';
            $this->results_details .= '<div id="prob_details" style="display:none;">' .
                $this->problem_details .
                "</div>";
            $this->results_details .= '</p>';
        }

        if (!empty($saleItemList)) {
            $this->results_details .=
                '<p><br /><strong><a href="" onclick=\'$("#sale_items").toggle(); return false;\'>
                Potential Sale Items:</a></strong> ' .
                count($saleItemList) .
                ': (click to reveal/hide)<br />';
            $this->results_details .= '<div id="sale_items" style="display:none;">' .
            implode('<br />',$saleItemList) .
            "</div>";
            $this->results_details .= '</p>';
        }


        // Pass 1 true, Pass 2 true
        if (true) {
            // Copy the new values into prodUpdate.
        $updateModel = new ProdUpdateModel($dbc);
        $updateModel->logManyUpdates($updated_upcs, ProdUpdateModel::UPDATE_EDIT);
        }

        $wba = ($this->dry_run) ? 'Would be added' : 'Added';
        $this->results_details .= "<p><br /><strong>Run Report:</strong>" .
            "<br />Non-empty lines of data: $non_empty_lines" .
            "<br />Already in Vendor catalogue: $itemsFound" .
            "<br /> New cost: " .
            " higher: $new_cost_higher" .
            " lower: $new_cost_lower" .
            " same: $new_cost_same" .
            "<br />{$wba} to Vendor catalogue: $itemsNotFound" .
            "<br />Notices and issues:" .
            "<br />'no upc' - implies Bulk: $no_upc" .
            "<br />bad_upc_pattern: $bad_upc_pattern" .
            "<br />could_not_fix_upc: $could_not_fix_upc" .
            "<br />no cost: $no_cost" .
            "<br />non_numeric_prices: $non_numeric_prices" .
            "<br />all_zero_upc: $all_zero_upc" .
            "<br />odd_pattern_count: $odd_pattern_count" .
            "<br />itf14-type UPC: $itf14_count" .
            "<br />sku different: $sku_different" .
            '</p>';
            '';

        return true;

    // process_file()
    }

    /* clear tables before processing */
    function split_start(){
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $idQ = "SELECT vendorID FROM vendors WHERE vendorName=? ORDER BY vendorID";
        //$idQ = "SELECT vendorID FROM vendors WHERE vendorName='ONFC' ORDER BY vendorID";
        $idP = $dbc->prepare($idQ);
        $args = array("{$this->vendorName}");
        $idR = $dbc->execute($idP,$args);
        if ($dbc->num_rows($idR) == 0){
            $this->error_details = "Cannot find vendor: {$this->vendorName}";
            return False;
        }
        $idW = $dbc->fetchRow($idR);
        $VENDOR_ID = $idW['vendorID'];

        if ($this->delete_vendor_items && !$this->dry_run) {
            $viP = $dbc->prepare("DELETE FROM vendorItems WHERE vendorID=?");
            $vsP = $dbc->prepare("DELETE FROM vendorSRPs WHERE vendorID=?");
            $dbc->execute($viP,array($VENDOR_ID));
            $dbc->execute($vsP,array($VENDOR_ID));
        }
    }

    /* Appears before the set of selects for fields in the uploaded data.
     */
    function preview_content(){
        $ret = '';
        $ret .= '<br /><input type="checkbox" name="dry_run" />
            Status report only - no changes';
        $ret .= '<br /><input type="checkbox" name="update_products" checked />
            Update costs in current products list';
        $ret .= '<br /><input type="checkbox" name="update_vendor_items" checked />
            Update rather than replace vendor items';
        $ret .= '<br /><input type="checkbox" name="delete_vendor_items" />
            Delete vendor items that are no longer in the catalogue';
        $ret .= '<br /><input type="checkbox" name="remove_checkdigits" checked />
            Remove check digits from UPCs';

        $ret .= '<br /><span style="font-size:1.3em; font-weight:normal; color:gray;">Possible Options,
            not currently inplemented</span> (See Help)';
        $readonlyC = 'onclick="return false;" onkeydown="return false;"';
        $ret .= '<span style="color:gray;">';
        $ret .= '<br /><input type="checkbox" name="add_to_products" ' . $readonlyC . ' />
            Add new items to products, set to Not in Use';
        $ret .= '<br /><input type="checkbox" name="create_price_batch" ' . $readonlyC . ' />
            Create Price Change Batch' .
            ' | Name for the Price Change Batch: '. 
            '<input type=text size=10 id=price_batch_name name=price_batch_name
            value="ONFC Price" readonly />';
        /* $upload_file_name provides no clue to the original.
            "from:  {$this->upload_file_name} " .
         */
        $ret .= '<br /><input type="checkbox" name="create_sale_batch" ' . $readonlyC . ' />
            Create Sale Batch' .
            ' | Name for the Sale Batch: '.
            '<input type=text size=10 id=sale_batch_name name=sale_batch_name
            value="ONFC Sale" readonly />';
        $ret .= '</span>';

        return $ret;  
    }

    /* The whole page that appears after the upload/processing.
     */
    function results_content(){
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $ret = "<p>Price data import complete</p>";
        if ($this->error_details != '') {
            $ret .= "<p>{$this->error_details}</p>";
        }
        if ($this->results_details != '') {
            $ret .= "<p>{$this->results_details}</p>";
        }
        $ret .= '<p><a href="'.$_SERVER['PHP_SELF'].'">Upload Another</a></p>';
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $ret .= '<p>If you are finished uploading proceed to
                <em><a href="../RecalculateVendorSRPs.php">Recalculate SRPs</a></em>
                </p>';
        }

        return $ret;
    }

    /* The whole page that appears after the upload/processing.
     */
    function helpContent(){

        $ret = '';
        $ret .= parent::helpContent();
        $ret .= '<p><strong>Columns in Preview</strong>
        <br />The choices in the dropdowns in column heads should be correct
        but you may change them if necessary.
        </p>
        ';
        $ret .= '<p><strong>Options</strong>
            <ul>
                <li>Status report only
                <br />Report on changes that would be made
                and on problems, but don\'t make the changes.
                </li>
                <li>Update rather than replace vendor items
                <br />Only changes cost, not description.
                </li>
                <li>Delete vendor items that are no longer in the catalogue
                <br />Does not delete items from the store\'s product list,
                but further stock will have to be obtained from another vendor.
                </li>
            </ul>
        </p>
        ';
        if ($this->config->get('COOP_ID') == 'WEFC_Toronto') {
            $ret .= '<p><strong>Possible Options</strong>
            <br />These could be done by this load but at the moment I think it is
            better to use the regular tools and sequence of actions
            to create Price-Change and Sale batches and add items to the store\'s product list.
            <ul>
                <li>Add new items in the vendor catalogue to the product list,
                    marking them Not In Use.
                    <br /> It is already fairly straightforward to to find a Vendor Item
                    from Item Maintenance and activate it.
                    </li>
                <li>Create Price Change Batch
                    <br />There is an intermediate step for this that allows review and edit
                    of suggested price changes.
                    </li>
                <li>Create Sale Batch
                    <br />This is at best experimental and may be moving too fast at this point.
                    <br />There is a list "Potential Sale Items" available on the screen
                    that reports the results of the upload.
                    </li>
            </ul>
            </p>
            ';
        }

        return $ret;
    }
}

FannieDispatch::conditionalExec(false);

