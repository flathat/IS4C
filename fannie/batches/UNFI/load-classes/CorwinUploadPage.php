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
 * 14May2017 Note and reject empty upc.
 * 14May2017 Note and reject upc not 13 chars.
 * 14May2017 Note and reject duplicate upc.
 * 14May2017 Note and reject duplicate sku.
 * 21Dec2016 List of cost changes for items inUse=1
 *  3Mar2016 $preview_opts['default'] values.
 */
/* DONE
 * 11May2017 organic and gf flags, but not using.
 * 11May2017 Enable logManyUpdates
 */

/* #'F
 * Functionality:
 * Inserts to tables:
 *  vendorItems
 *  vendorSRPs
 * Updates tables: 
 *  vendorItems
      .sku
      .units // items/case
      .cost  // item cost
      .saleCost  // cost from catalogue, usually unit but sometimes case
 *  products - if wanted
      .cost // item cost
      NOT .numflag // organic, gluten-free flags
 *  prodExtra - if wanted
      .cost // item cost
      .case_cost // case cost
      .case_quantity // items/case
 * In future might:
 *  Create a Sale Batch
 */

include(dirname(__FILE__) . '/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class CorwinUploadPage extends \COREPOS\Fannie\API\FannieUploadPage {

    public $themed = true;
    public $title = "Fannie - Corwin Prices";
    public $header = "Upload Corwin price file";
    public $description = '[Corwin Catalogue Import] specialized vendor import tool.
        Column choices default to Corwin price file layout.';

    protected $vendorID = 103;
    protected $vendorName = 'CORWIN';
    protected $results_details = '';
    protected $problem_details = '';
    protected $use_splits = false;
    // $use_js was true
    protected $use_js = false;

    // 'default' is column-number, where A=0.
    protected $preview_opts = array(
        'upc' => array(
            'name' => 'upc',
            'display_name' => 'UPC *',
            'default' => 0,
            'required' => True
        ),
        'sku' => array(
            'name' => 'sku',
            'display_name' => 'SKU *',
            'default' => 1,
            'required' => true
        ),
        'desc' => array(
            'name' => 'desc',
            'display_name' => 'Description *',
            'default' => 2,
            'required' => True
        ),
        'case' => array(
            'name' => 'case',
            'display_name' => 'Case Specs *',
            'default' => 3,
            'required' => True
        ),
        'cost' => array(
            'name' => 'cost',
            'display_name' => 'Cost *',
            'default' => 4,
            'required' => True
        ),
        'costis' => array(
            'name' => 'costis',
            'display_name' => 'Cost Is',
            'default' => 5,
            'required' => False
        ),
    );
        /* Additions
        'sub_brand' => array(
            'name' => 'sub_brand',
            'display_name' => 'Sub-brans',
            'default' => 6,
            'required' => False
        ),
        'comment' => array(
            'name' => 'comment',
            'display_name' => 'Comment',
            'default' => 7,
            'required' => false
        ),
        'website' => array(
            'name' => 'website',
            'display_name' => 'Website',
            'default' => 8,
            'required' => False
        ),
*/

    /* New
     */
    protected $dry_run = 0;
    protected $update_products = 0;
    protected $remove_checkdigits = 0;
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
         *  since most pages don't use it. namespace?
         */
        $this->dry_run = \FormLib::get_form_value('dry_run',0);
        $this->update_products = \FormLib::get_form_value('update_products',0);
        $this->remove_checkdigits = \FormLib::get_form_value('remove_checkdigits',0);
        $this->create_sale_batch = \FormLib::get_form_value('create_sale_batch',0);
        $this->sale_batch_name = \FormLib::get_form_value('sale_batch_name','');
        $this->create_price_batch = \FormLib::get_form_value('create_price_batch',0);
        $this->price_batch_name = \FormLib::get_form_value('price_batch_name','');

        /* Cannot do this before assigning other vars because it calls process_file(),
         * i.e. everything will be done before it has the form-based values of
         * those vars to use.
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

        /* See ONFC for Sale Batch support. */

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
        $UPC = $this->get_column_index('upc');
        $SKU = $this->get_column_index('sku');
        $DESCRIPTION = $this->get_column_index('desc');
        $CASE = $this->get_column_index('case');
        $COST = $this->get_column_index('cost');
        $COSTIS = $this->get_column_index('costis');

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
        if (array_key_exists($CATEGORY_TO_DEPT_MAP,$x[$CATEGORY])) { }
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

        $extraQ = "UPDATE prodExtra
            SET cost=?,
            case_cost=?,
            case_quantity=?
            WHERE upc=?";
        $extraP = $dbc->prepare($extraQ);

        // numflag= numflag | ? | ?,
        $prodQ ='UPDATE products
            SET cost=?,
                modified=' . $dbc->now() . '
            WHERE upc=?
                AND default_vendor_id=?';
        $prodP = $dbc->prepare($prodQ);

        $findVendorItemQ = 'SELECT upc,sku,cost,vendorID
            FROM vendorItems
            WHERE upc =? AND vendorID =?';
        $findVendorItemP = $dbc->prepare($findVendorItemQ);

        $vendorItemPupdateQ = "UPDATE vendorItems SET sku=?, units=?, cost =?, " .
            "saleCost=?, vendorDept=?, " .
            "modified=" . $dbc->now() .
            " WHERE upc =? AND vendorID =?";
        $vendorItemPupdate = $dbc->prepare($vendorItemPupdateQ);
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
        $duplicate_upc = 0;
        $upcs_this_run = array();
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
        $cell = '';
        $cell2 = '';
        $upc = '';
        $work_upc = '';
        $upc_exponents = 0;

        // Corwin:
        $currentBrand = "";
        $website = "";
        $currentSubBrand ="";
        $currentComment = "";
        $costIsCase = False;
        $brandCostIsCase = False;
        $subBrandCostIsCase = False;
        $itemCostIsCase = False;
        $forceCostIsCase = False;
        $isNewLine = False;
        $lastBrand = "";
        $lastSubBrand = "";
        $brandCount = 0;
        $subBrandCount = 0;
        $displayCount = 0;
        $caseStrings = 'cases only|by the case';
        $forceCostIsCaseCount = 0;

        /* ddd During-dev diagnostic.
         */
        $ddd = True;
        if ($ddd) {
            $diagFile = '/tmp/corwin.txt';
            $df = fopen($diagFile,'w');
            // Column heads for screen display.
            $this->problem_details .= "<br />rrC#:,upc:,sku:,brand:,desc:,rawCase:,package:,units:,size:,uom:,cost:,rawCost:,isCase: ";
        }

        $rawRowCount = 0;

        //#'l
        foreach($linedata as $data) {
//if ($itemsNotFound > 10 || $itemsFound > 10) break; //if ($itemsNotFound > 100 || $itemsFound > 20000) break;
// if ($saleBatchItems > 10) break;
//break;

            $rawRowCount++;
//if ($rawRowCount > 500) break;

            if (!is_array($data)) {
                continue;
            }

            if (!isset($data[$UPC])) {
                continue;
            }

            /* Data fudged by hand. */
            if (preg_match("/SKIPLINE/i", $data[$UPC])) {
                continue;
            }

            /* Clean all now so you don't need to later.
             */
            $data[$UPC] = trim($data[$UPC],' *');
            $data[$SKU] = trim($data[$SKU]);
            $data[$DESCRIPTION] = trim($data[$DESCRIPTION],' *');
            $data[$CASE] = trim($data[$CASE],' *');
            $data[$COST] = trim($data[$COST]);
            $data[$COSTIS] = trim($data[$COSTIS]);

            /* Sometimes just a hyphen. Meaning?
             */
            if ($data[$COST] == '-') {
                $data[$COST] = '';
            }
            /* Data fudge.
             */
            if (preg_match("/NOTCASE/i", $data[$COST])) {
             $this->problem_details .= "<br />rrC#: $rawRowCount | cost: " . $data[$COST];
                $data[$COST] = '';
                $costIsCase = False;
                $brandCostIsCase = False;
                $subBrandCostIsCase = False;
                $itemCostIsCase = False;
            }

            /* Odd bits.
             */
            if ($data[$UPC] == '' && $data[$SKU] == '') {
                /* A comment alone.
                 * What would happen if these lines were skipped?
                 * If empty they can look like brand divisions.
                 * */
                if ($data[$DESCRIPTION] != '') {
                    if (preg_match("/upc code/i", $data[$DESCRIPTION])) {
                        $data[$DESCRIPTION] = '';
                        $lastWasComment = True;
                    } elseif (preg_match("/no returns/i", $data[$DESCRIPTION])) {
                        $data[$DESCRIPTION] = '';
                    } elseif (preg_match("/reduced sodium/i", $data[$DESCRIPTION])) {
                        $data[$DESCRIPTION] = '';
                    } elseif (preg_match("/legend:/i", $data[$DESCRIPTION])) {
                        $data[$DESCRIPTION] = '';
                    }
                } else {
                    // A number alone
                    if ($data[$CASE] == '' && $data[$COST] != '') {
                        if (preg_match("/\d\.\d/", $data[$COST])) {
                            $data[$COST] = '';
                        }
                    }
                }
            }

            /* Test for emtpy row.
             * ? Means new Brand starting? Always means that?
             */
            if (trim(implode('',$data)) == "") {
                $currentBrand = "";
                $website = "";
                $currentSubBrand ="";
                $currentComment = "";
                $subBrandCostIsCase = False;
                $brandCostIsCase = False;
                $costIsCase = False;
                $itemCostIsCase = False;
                $forceCostIsCase = False;
                $isNewLine = False;
                continue;
            }
            $non_empty_lines++;

            if ($data[$COSTIS] == 'case') {
                $forceCostIsCase = True;
                $forceCostIsCaseCount++;
            }

            /* See if the row is not for an item: Test for upc in first column.
             * If not upc look in first column for:
             * - Brand
             * - SubBrand
             * - URL
             * And look in Description column for:
             * - cost-is-case flag, which may be at Brand or SubBrand level.
                && $cell != ''
             */
            /* The asterisk after a UPC means "New UPC code",
             * at least sometimes it does.
             */
            $cell = '' . trim($data[$UPC],' *');
            if (strpos($cell,'E+11')) {
                $this->problem_details .= "<br />rrC#: $rawRowCount UPC: '{$cell}'
                    in exponential notation - cannot use. Fix the CSV.";
                $upc_exponents++;
                continue;
            }
            //if (!preg_match('/^\d +\d{10,12} +\d$/',$cell)
            if (!preg_match('/^\d +\d{10,12} *\d$/',$cell)
                && $data[$SKU] == ""
            ) {
                /* "cases only" at Brand level.
                 */
                if ($cell == '' ) {
                    if (preg_match("/({$caseStrings})/i", $data[$DESCRIPTION])) {
                        $brandCostIsCase = True;
                        //$costIsCase = True;
                        /* Sometimes contains a SKU range it applies to.
                         * No handling for that yet.
                         */
                        $currentComment = $data[$DESCRIPTION];
                    }
                }
                if (preg_match("/({$caseStrings})/i", $cell)) {
                    $brandCostIsCase = True;
                    //$costIsCase = True;
                }
                // Column B=SKU should always be empty.
                /* Brand
//$this->problem_details .= "<br />rrC#: $rawRowCount | cell2_before: $cell2";
//$this->problem_details .= "<br />rrC#: $rawRowCount | cell2_after: $cell2";
                 */
                $cell2 = '' . $cell;
                $c = strpos($cell2,' (');
                if ($c > 0) {
                    $cell2 = substr($cell2,0,$c);
                }
                /* Brand
                 * Unsure about cell2 test.
                 */
                if ($cell2 == strtoupper($cell2)
                    && $data[$SKU] == ""
                    && $cell2 != ""
                ) {
                    if ($currentBrand != "") {
                        /* Known problems.
                         */
                        if ($currentBrand == 'ATTITUDE' && $cell2 == 'BODY CARE') {
                            // Serves as a prefix to SubBrand
                            continue;
                        }
                        if ($currentBrand == 'DRUIDE BODY CARE' && $cell2 == 'ECOTRAIL') {
                            // Serves as a prefix to SubBrand
                            continue;
                        }
                        $this->problem_details .= "<br />rrC#: $rawRowCount '{$cell2}'
                            looks like a Brand 
                            but Brand '{$currentBrand}' is still active.";
                        //$noop = 1;
                    } elseif ($currentSubBrand != "") {
                        $this->problem_details .= "<br />rrC#: $rawRowCount '{$cell2}'
                            looks like a SubBrand 
                            but SubBrand '{$currentSubBrand}' is still active.";
                        $noop = 1;
                    } else {
                        $currentBrand = $cell2;
                        if (preg_match('/NEW LINE$/',$currentBrand)) {
                            $currentBrand =
                                preg_replace("/ *-* *NEW LINE$/","",$currentBrand);
                            $isNewLine = True;
                        }
                        $brandCount++;
                        continue;
                    }
                }
                /* website
                 */
                $cell2 = $cell;
                if (preg_match('/[-a-zA-Z0-9_]\.[a-z]{2,3}$/',$cell2)) {
                    $website = $cell2;
                    if (preg_match("/({$caseStrings})/i", $data[$DESCRIPTION])) {
                        $brandCostIsCase = True;
                        //$costIsCase = True;
                        /* Sometimes contains a SKU range it applies to.
                         * No handling for that yet.
                         */
                        $currentComment = $data[$DESCRIPTION];
                    }
                    continue;
                }
                /* Sub Brand
                 */
                if ($cell != "" ) {
                    $currentSubBrand = $cell;
                    if (preg_match("/({$caseStrings})/i", $data[$DESCRIPTION])) {
                        $subBrandCostIsCase = True;
                        /* costIsCase decision is pushed to SubBrand for the remainder
                         * of this Brand.
                         */
                        $brandCostIsCase = False;
                        //$costIsCase = True;
                        /* Sometimes contains a SKU range it applies to.
                         * No handling for that yet.
                         */
                        $currentComment = $data[$DESCRIPTION];
                    } else {
                        $subBrandCostIsCase = False;
                    }
                    $subBrandCount++;
                } else {
                    // Problem
                    $noop = 1;
                }
                continue;

            } 

            /* #'j JIGGER Brand and SubBrand scan
             */
            if (false && $currentBrand != $lastBrand) {
                $this->problem_details .= "<br />rrC#: $rawRowCount " .
                    "| Change to Brand: $currentBrand | brandCostIsCase: ";
                $this->problem_details .= ($brandCostIsCase) ? 'BYes' : 'BNo';
                $noop = 1;
            }
            if (false && $currentSubBrand != $lastSubBrand) {
                /*
                 */
                $this->problem_details .= "<br />rrC#: $rawRowCount " .
                    "| Change to SubBrand: $currentSubBrand | subBrandCostIsCase: ";
                $this->problem_details .= ($subBrandCostIsCase) ? 'SYes' : 'SNo';
                $noop = 1;
            }
            $lastBrand = $currentBrand;
            $lastSubBrand = $currentSubBrand;
            //continue;

            /* It is for an item.
             * - UPC is occasionally empty
             *   - Reject
             * - SKU is never empty
             */
            $upc = '' . $cell;
            // get other data from appropriate columns
            $sku = ($SKU !== false) ? $data[$SKU] : '';
            $brand = $currentBrand; //$data[$BRAND];
            $description = $data[$DESCRIPTION];
            $rawDesc = $description;
            $case = ($CASE !== false) ? $data[$CASE] : '';
            $cost = ($COST !== false) ? $data[$COST] : '';
            $rawCost = $cost;
            if ($upc == '') {
                $this->problem_details .= ("<br />rrC#: $rawRowCount " .
                "SKU: '{$sku}' DESC: '{$description}' " .
                " HAS NO UPC - CANNOT LOAD.");
                $no_upc++;
                continue;
            } else {
                /* Discard checkdigit?
                $upcParts = preg_split("/ +/",$upc);
                if ($this->remove_checkdigits && isset($upcParts[2])) {
                    unset($upcParts[2]);
                }
                $upc = implode("",$upcParts);
                $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
                 */
                $work_upc = '' . $upc;
                $work_upc = str_replace(' ','',$upc);
                if (strlen($work_upc) > 11) {
                    // Validate checkdigit
                    if ($this->remove_checkdigits) {
                        $work_upc = substr($work_upc,0,-1);
                    }
                }
                $upc = str_pad($work_upc, 13, '0', STR_PAD_LEFT);
                if (array_key_exists($upc,$upcs_this_run)) {
                    $this->problem_details .= ("<br />rrC#: $rawRowCount " .
                        "UPC: $upc is already used for: " .
                        $upcs_this_run["$upc"] .
                        " Cannot re-use, skipped.");
                    $duplicate_upc++;
                    continue;
                } else {
                    $upcs_this_run["$upc"] = "Brand: '{$currentBrand}'" .
                    " SKU: '{$sku}' DESC: '{$description}'";
                }
            }

            /* Description
             * Sometimes has:
             * - NEW at the end, remove
             * - "Organic", "GF", etc. that are better as flags.
             * - "Gluten Free", GF, (GF), (G/F)
             * - package and case info
             * - long further description in parens that we cannot use.
             * - short bits in parens that we can maybe use
             * Test: $BATCHESU/descTest.php
             */
            if (strpos($description, ' Display') > 0 || $case == 'Display') {
                $displayCount++;
                continue;
            }
            $description = preg_replace("/ +NEW$/","",$description);
            $description = preg_replace("/Gluten Free/i","Gluten-Free",$description);
            $dParts = preg_split("/ +/",$description);
            $dPartsLC = preg_split("/ +/",strtolower($description));
            $dParens = array();
            $organic = "";
            $gf = "";
            $nonGMO = "";
            $useDesc = "";
            $dpCount = count($dParts);
            $dpp = -1;
            $inParen = False;
            $packageBit = "";
            /* Remove and set aside parenthesized strings.
             */
            for ($dp = 0 ; $dp < $dpCount ; $dp++) {
                // Whole token is in parens.
                if (preg_match("/\([^)]+\)/",$dParts[$dp])) {
                    $dNoParens = trim($dParts[$dp],'()');
                    // Is it a package description?
                    if (preg_match('/\d\D+$/',$dNoParens)) {
                        $packageBit = $dNoParens;
                        continue;
                    }
                    $dpp++;
                    $dParens[$dpp] = $dParts[$dp];
                    continue;
                }
                // End of paren'ed token.
                if (substr($dParts[$dp],-1,1) == ')' && $inParen) {
                    $dParens[$dpp] .= (' ' . $dParts[$dp]);
                    $inParen = False;
                    continue;
                }
                // Mid-paren token.
                if ($inParen) {
                    $dParens[$dpp] .= (' ' . $dParts[$dp]);
                    continue;
                }
                // Start paren'ed token.
                if (substr($dParts[$dp],0,1) == '(') {
                    $dpp++;
                    $dParens[$dpp] = $dParts[$dp];
                    $inParen = True;
                    continue;
                }
                // Flag-words
                if ($dPartsLC[$dp] == "organic") {
                    $organic = $dParts[$dp];
                    continue;
                }
                if (
                    $dPartsLC[$dp] == "gf" ||
                    $dPartsLC[$dp] == "g/f" ||
                    $dPartsLC[$dp] == "gluten-free"
                ) {
                    $gf = $dParts[$dp];
                    continue;
                }
                /* Package info at the end: 35ml
                 * esp. when $case: \d/case
                if ($dp == ($dpCount - 1)) {}
                    if (preg_match('/\d\D+$/',$dParts[$dp])) {}
                 */
                /* Package info anywhere: 35ml
                 */
                if (true || $dp == ($dpCount - 1)) {
                    //if (preg_match('/\d(ml|g|L|kg)/',$dParts[$dp])) {}
                    if (preg_match('/^\d+\D+$/',$dParts[$dp])) {
                        $packageBit = $dParts[$dp];
                        continue;
                    }
                }

                // Keep the word
                $useDesc .= ($useDesc == '') ? '' : ' ';
                $useDesc .= $dParts[$dp];
                
            }
            /* Append parenthesized strings if they will not make the
             * description "too long".
             */
            foreach ($dParens as $paren) {
                if (strlen($paren) < 20 &&
                    ((strlen($useDesc) + strlen($paren)) < 50)
                ) {
                    $useDesc .= (' ' . $paren);
                }
            }

            /* Append organic and GF "flags" so they will be available in
             * imports from vendorItems.
             */
            if ($organic != '' && $gf != '') {
                $useDesc = substr($useDesc,0,45) . ' O GF';
            } elseif ($organic != '') {
                $useDesc = substr($useDesc,0,48) . ' O';
            } elseif ($gf != '') {
                $useDesc = substr($useDesc,0,47) . ' GF';
            } else {
                $noop = 1;
            }


            /* 
             * Case statement
             * 12x35ml
             * - $case_size
             * - $size
             * - $unitofmeasure
             * 6each
             * ?Put the raw $case somewhere in case the parsing doesn't work?
             * $BATCHESU/testCase.php
             */
             $case_size = 0;
             $size = 0;
             $package = '';
             $unitofmeasure = '';
             $rawCase = '';
             $itemCostIsCase = False;
             if ($case == '') {
                 $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku | brand: $brand | desc: $description 
                     | case: EMPTY";
                 $noop = 1;
             } else {
                 $rawCase = $case;
                 $case = trim($case,' *');
                 $case = preg_replace('/ *x */','x',$case);
                 $case = preg_replace('/ *\/ */','/',$case);
                 $case = preg_replace('/\/cs/','/case',$case); // "cs" as abbreviation for case
                 $case = preg_replace('/ ml/','ml',$case);
                 $case = preg_replace('/ oz/','oz',$case);
                 $case = preg_replace('/ *boxes/','',$case); // Needs packagBit
                 $case = preg_replace('/ *trays/','',$case); // Needs packagBit
                 $case = preg_replace('/ *units/','',$case); // Needs packagBit
                 //$case = preg_replace('/^(\d+)\/case$/','$1',$case); // handled below
                 /* This implies cost is for the whole thing
                  * 
                  $case = preg_replace('/^(\d+)pcs*$/','${1}/case',$case); // needs packageBit
                  */
                 $lower_case = strtolower($case);
                 if ($lower_case == 'each' 
                    || $lower_case == 'kit'
                 ) {
                     $case_size = 1;
                     $size = 1;
                     $unitofmeasure = 'ct'; // 'ea'?
                     $package = '1ct';
                 } elseif (strpos($case,'x')>0) {
                     if (preg_match('/(\d+)x(.+)$/',$case,$matches)) {
                         $case_size = $matches[1];
                         $package = $matches[2];
                         if (preg_match('/(\d+\.*\d*)(\D+)$/',$package,$matches)) {
                             $size = $matches[1];
                             $unitofmeasure = $matches[2];
                         }
                     } else {
                         // problem
                         $noop = 1;
                     }
                 } elseif (strpos($case,'/')>0) {
                     if (preg_match('/(\d+)\/(.+)$/',$case,$matches)) {
                         $case_size = $matches[1];
                         $package = trim($matches[2]);
                         //if (preg_match('/^(case|box)$/i',$package)) {}
                         if (
                             strtolower($package) == 'case' ||
                             strtolower($package) == 'box'
                         ) {
                             //$itemCostIsCase = True; //? not usually
                             // Assume these until you known different.
                             $package = '1ct';
                             //$size = 1;
                             //$unitofmeasure = 'ct';
                             if ($packageBit != '') {
                                 $package = trim($packageBit,'()');
                             } else {
                                 //Problem?
                                 $noop = 1;
                             }
                         }
                         // 3.5ml
                         if (preg_match('/(\d+\.*\d*)\/*(\D+)$/',$package,$matches)) {
                             $size = $matches[1];
                             $unitofmeasure = $matches[2];
                             $unitofmeasure = ($unitofmeasure == 'pkg') ? 'ct' : $unitofmeasure;
                             $package = "$size$unitofmeasure";
                         } else {
                             // What is this about?
                             $size = 'XX' . $package;
                         }
                     } else {
                         // problem
                         $noop = 1;
                     }
                 } elseif (preg_match('/(\d+)(\D+)$/',$case,$matches)) {
                     $case_size = $matches[1];
                     $package = trim($matches[2]);
                     $unitofmeasure = 'ct';
                     /*
                     $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku
                         | brand: $brand | desc: $description 
                         | case_size: $case_size | unitofmeasure: $unitofmeasure 
                         | rawCase: $rawCase LAST DITCH CASE";
                      */
                 } else {
                     $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku
                         | brand: $brand | desc: $description 
                         | rawCase: $rawCase CANNOT PARSE";
                     $noop=1;
                 }
             }

             $costIsCase = ($forceCostIsCase || $itemCostIsCase || $subBrandCostIsCase || $brandCostIsCase) ? True : False;
             if ($costIsCase) {
                 if ($case_size == 0) {
                     $this->problem_details .= "<br />rrC#: $rawRowCount " .
                         "| upc: $upc | sku: $sku | brand: $brand | desc: $description " .
                         "| units: $case_size UNITS NEEDED FOR COST_IS_CASE";
                 } else {
                     $case_cost = $cost;
                     $cost = round(($cost / $case_size),2);
                 }
             } else {
                 if ($case_size == 0) {
                     $this->problem_details .= "<br />rrC#: $rawRowCount " .
                         "| upc: $upc | sku: $sku | brand: $brand | desc: $description " .
                         "| units: $case_size | rawCase: $rawCase | case: $case " .
                         "UNITS NEEDED FOR CASE_COST";
                         $case_cost = $cost;
                 } else {
                     $case_cost = round(($cost * $case_size),2);
                 }
             }
            /* Can't process items w/o cost (usually promos/samples anyway)
             */
            $costTest = "{$cost}{$case_cost}";
            if (empty($costTest)){
                $no_cost++;
                continue;
            }

             /* #'t Test:
              * Dump what you have: line# upc, sku, brand, description, case_size, size, unitofmeasure
              * to screen or to $problems
             */
             /*
             $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku | brand: $brand | desc: $useDesc 
                | rawCase: $rawCase 
                | package: $package 
                | units: $case_size | size: $size | uom: $unitofmeasure | cost: $cost | isCase: ";
             $this->problem_details .= ($costIsCase) ? 'yes' : 'no';
             END FIRST VERSION
             $problemItem = "<br />$rawRowCount | $upc | $sku | $brand | $useDesc 
                | $rawCase 
                | $package 
                | $case_size | $size | $unitofmeasure | $cost | $rawCost | ";
             $problemItem .= ($costIsCase) ? 'yes' : 'no';
             $problemItem = str_replace(',',':',$problemItem);
             $problemItem = str_replace(' | ',',',$problemItem);
             $this->problem_details .= $problemItem;
              */
            if ($ddd) {
                if (
                    !preg_match('/^\w+$/',$unitofmeasure) ||
                    !preg_match('/^[.0-9]+$/',$size) ||
                    !preg_match('/^[.0-9]+\w+$/',$package)
                ) {
                    $dstr = "rrC#: $rawRowCount | upc: $upc";
                    // $dstr .= " | sku: $sku | brand: $brand ";
                    $dstr .= "| rawDesc: $rawDesc | rawCase: $rawCase 
                    | PACKAGE: $package | units: $case_size | size: $size | uom: $unitofmeasure";
                    /*
                    $dstr .= " | cost: $cost | isCase: ";
                    $dstr .= ($costIsCase) ? 'yes' : 'no';
                     */
                    fwrite($df, $dstr . "\n");
                }
            }

             /* #'k JIGGER for development.
              * Continue with next row from the spreadsheet if you don't want to change the database.
             continue;
              */


            /* Assignments for Corwin-style data.
            */

             /* Does not initially exist for Corwin.
              * If/when it does will likely be Brand
              */
            $vendorDept = (array_key_exists($brand,$CATEGORY_TO_DEPT_MAP))
                ? $CATEGORY_TO_DEPT_MAP[$brand]
                : 0;
            $srp = '0.00';
            $size .= $unitofmeasure;
            $qty = $case_size;
            $reg1 = sprintf("%.2f",$cost);
            $reg_case = sprintf("%.2f",$case_cost);
            $catalogue_cost = $rawCost;
            //$reg_unit = sprintf("%.2f",(!empty($reg1)) ? $reg1 : ($reg_case / $qty));
            // need unit cost, not case cost
            $reg_unit = $reg1;
            // Corwin does not have a sale cost.
            $sale_unit = '0.00';

             /* #'O upc
              */

            if (isset($SKU_TO_PLU_MAP[$sku])) {
                $upc = $SKU_TO_PLU_MAP[$sku];
                // '002' have price/lb in the upc.
                if (substr($size, -1) == '#' && substr($upc, 0, 3) == '002') {
                    $qty = teim($size, '# ');
                    $size = '#';
                } elseif (substr($size, -2) == 'LB' && substr($upc, 0, 3) == '002') {
                    $qty = trim($size, 'LB ');
                    $size = 'LB';
                }
            }

            /* syntax fixes.
             * trim $ off amounts as well as commas for the
             * occasional > $1,000 item
             */
            $reg_case = str_replace('$',"",$reg_case);
            $reg_case = str_replace(",","",$reg_case);
            $reg1 = str_replace('$',"",$reg1);
            $reg1 = str_replace(",","",$reg1);
            $catalogue_cost = str_replace('$',"",$catalogue_cost);
            $catalogue_cost = str_replace(",","",$catalogue_cost);
            $srp = str_replace('$',"",$srp);
            $srp = str_replace(",","",$srp);

            /* For assigning products.numflag with bit logic.
             * Organic is bit 2 = 2
             * GF is bit 4 = 8
             */
            $organic_flag = ($organic != '') ? 2 : 0;
            //$organic_flag = ($organic != '') ? 16 : 0;
            $gf_flag = ($gf != '') ? 8 : 0;
            //$gf_flag = ($gf != '') ? 17 : 0;

            /* Pass 1 false, Pass 2 true
                Production: if (true && ...) {}
             */
            if (True && $this->update_products && !$this->dry_run) {
                // Update prodExtra and products
                $argsExtra = array($reg_unit,$reg_case, $qty, $upc);
                $dbc->execute($extraP, $argsExtra);
                $argsProducts = array($reg_unit,$upc,$VENDOR_ID);
                //$argsProducts = array($reg_unit,$organic_flag,$gf_flag,$upc,$VENDOR_ID);
                $dbc->execute($prodP, $argsProducts);
                $updated_upcs[] = $upc;
            } else {
                //$this->results_details .=
                //"<p>Skipped prodExtra and products updates.</p>";
                $noop = 1;
            }

            $argsFVI = array($upc,$VENDOR_ID);
            $findVendorItemR = $dbc->execute($findVendorItemP,$argsFVI);
            if ($dbc->num_rows($findVendorItemR) != 0){
                $itemsFound++;
                //$this->results_details .= "<br />Found: $upc $description";
                /* Pass 1 false, pass 2 true
        $vendorItemPupdateQ = "UPDATE vendorItems SET sku=?, units=?, cost =?, " .
            "saleCost=?, vendorDept=?, " .
            "modified=" . $dbc->now() .
            " WHERE upc =? AND vendorID =?";
                          */
                if (True) {
                $argsUpdate = array(
                    $sku === false ? '' : $sku, 
                    $qty,
                    $reg_unit,
                    $catalogue_cost,
                    $vendorDept == 0 ? null : $vendorDept,
                    $upc,
                    $VENDOR_ID
                );
                }
                $fir = $dbc->fetchRow($findVendorItemR);
                if ($fir['sku'] != $sku) {
                    $sku_different++;
                    //$this->results_details .=
                    //"<br />SKU: vendorItems: {$fir['sku']} Corwin: $sku ";
                    if (strpos($fir['sku'],'dup') > 0 ) {
                        $sku = $fir['sku'];
                    }
                }
                /* How can $upc sometimes not be assigned?
                 * Empty upc not being trapped and rejected.
                          */
                if ($fir['cost'] < $reg_unit) {
                    $new_cost_higher++;
                    $this->results_details .=
                    "<br />New higher Cost: {$upc} vendorItems: {$fir['cost']} Corwin: $reg_unit ";
                } elseif ($fir['cost'] > $reg_unit) {
                    $new_cost_lower++;
                    $this->results_details .=
                    "<br />New lower Cost: {$upc} vendorItems: {$fir['cost']} Corwin: $reg_unit ";
                } else {
                    $new_cost_same++;
                }
                // Pass 1 false, pass 2 true
                if (True && !$this->dry_run) {
                    $ok = $dbc->execute($vendorItemPupdate,$argsUpdate);
                    if (!$ok) {
                        $this->results_details .= "<br />vI update of {$upc} failed";
                        break;
                    }
                }

                /* See ONFC loader for Sale Batch support that goes here.
                 */

            } else {
                $itemsNotFound++;
                // #'f Field vars needed for insert.
                $argsInsert = array(
                    $brand, 
                    $sku === false ? '' : $sku, 
                    $size === false ? '' : $size,
                    $upc,
                    $qty,
                    $reg_unit,
                    $useDesc,
                    $vendorDept == 0 ? null : $vendorDept,
                    $VENDOR_ID,
                    $catalogue_cost,
                    $srp,
                   date('Y-m-d H:i:s'),

                );
                // Pass 1 false, pass 2 true
                /* Defeat during Corwin development.
                 */
                if (True && !$this->dry_run) {
                    $ok = $dbc->execute($vendorItemPinsert,$argsInsert);
                    if (!$ok) {
                        $this->results_details .= "<br />vI insert failed";
                        break;
                    }
                }
            }

            /*
             * Defeat during development.
             * Corwin data doesn't have srp anyway.
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


        /* Pass 1 true, Pass 2 true
         * Production: true
         */
        if (true && !$this->dry_run) {
            // Copy the new values into prodUpdate.
            $updateModel = new ProdUpdateModel($dbc);
            $updateModel->logManyUpdates($updated_upcs, ProdUpdateModel::UPDATE_EDIT);
        } else {
            $this->results_details .= "<p><br />Did NOT log updates: logManyUpdates()</p>";
        }

        $wba = ($this->dry_run) ? 'Would be added' : 'Added';
        $this->results_details .= "<p><br /><strong>Run Report:</strong>" .
            "<br />Non-empty lines of data: $non_empty_lines" .
            "<br />Already in Vendor catalogue: $itemsFound" .
            "<br />Product displays skipped: $displayCount" .
            "<br /> New cost: " .
            " higher: $new_cost_higher ; " .
            " lower: $new_cost_lower ; " .
            " same: $new_cost_same" .
            "<br />{$wba} to Vendor catalogue: $itemsNotFound" .
            "<br />Notices and issues:" .
             "<br />'no upc': $no_upc" .
             "<br />duplicate upc: $duplicate_upc" .
             "<br />upc in exponential format: $upc_exponents";
            if ($upc_exponents > 0) {
                $this->results_details .= "  See: 'Data Problems' - PLEASE FIX THE CSV";
            }
             //"<br />bad_upc_pattern: $bad_upc_pattern" .
            // "<br />could_not_fix_upc: $could_not_fix_upc" .
        $this->results_details .= 
            "<br />no cost: $no_cost" .
            "<br />non-numeric prices: $non_numeric_prices" .
            "<br />forced cost to be case-cost: $forceCostIsCaseCount" .
            // "<br />all_zero_upc: $all_zero_upc" .
            // "<br />odd_pattern_count: $odd_pattern_count" .
            // "<br />itf14-type UPC: $itf14_count" .
            "<br />sku different: $sku_different" .
            '</p>';
            '';

        /* ddd During-dev diagnostic.
         */
        if ($ddd) {
            fclose($df);
        }

        return true;

    // process_file()
    }

    /* clear tables before processing */
    function split_start(){
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $idQ = "SELECT vendorID FROM vendors WHERE vendorName=? ORDER BY vendorID";
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
            Delete vendor items that are not in the catalogue being loaded';
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
            value="' . $this->vendorName . 'Price" readonly />';
        /* $upload_file_name provides no clue to the original.
            "from:  {$this->upload_file_name} " .
         */
        $ret .= '<br /><input type="checkbox" name="create_sale_batch" ' . $readonlyC . ' />
            Create Sale Batch' .
            ' | Name for the Sale Batch: '.
            '<input type=text size=10 id=sale_batch_name name=sale_batch_name
            value="' . $this->vendorName . 'Sale" readonly />';
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
                <br />Changes cost, sku, units-per-case, not description.
                <br />Also (Corwin-specific) changes "saleCost"
                to the cost from the
                catalogue, which is usually unit cost but may be
                case cost.
                </li>
                <li>Update Products
                <br />Only changes cost, case cost and units-per-case,
                but not package-size, unit-of-measure, description or flags.
                </li>
                <li>Delete vendor items that are no longer in the catalogue
                <br />Does not delete items from the store\'s product list,
                but further stock will have to be obtained from another vendor.
                </li>
            </ul>
        </p>
        ';
        $ret .= '<p><strong>Corwin Issues</strong>
        <br />Things to be aware of about the Corwin data:
            <ul>
                <li>The cost in the source data is usually the unit cost but sometimes
                the case cost and it is not always possible to tell which.
                The cost you see is the result of a best effort to determine the
                unit cost.
                <br />The database "saleCost" field contains the cost value from the
                original data in case it will help determine the correct value for
                unit cost.
                </li>
                <li>The description you see is composed from several elements in the
                source data and may not look right or even make sense;
                you will need to edit it.
                </li>
                <li>"O" appended to the description means "Organic".
                <br />"GF" appended to the description means "Gluten Free".
                </li>
            </ul>
        </p>';
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
                <li>Create Sale Batch
                    <br />Corwin data does not include sale prices.
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

