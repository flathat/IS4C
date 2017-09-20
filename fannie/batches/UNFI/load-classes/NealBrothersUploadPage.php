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
 * 21Dec2016 List of cost changes for items inUse=1
 *  3Mar2016 $preview_opts['default'] values.
 */
/* June 2017
 * New layout with separate Brand field and UPC in a different place.
 * - Validate UPCs
 *   - UPC-A
 *   - EAN13
 */
/* May 2017
 * [] A lot of undigested Corwin
 * [] Try validating vendorItems on SKU + vendorID instead of upc
 *    No, many sku's are not current NB
 *    In fact, update vendorItmes.sku from the data.
 * [] BRAND - Description
 *    Except:
 *    [] If BRAND /^NB / then
 *        $brand = 'NB',
 *        prefix the rest of brand to $description
 *    [] If BRAND /^CHS / same
 *    TAZO
 *    TEA SQUARED
 *    THREE FARMERS
 *    "WOW BAKING CO" -> WOW
 *    WOW
 *    There may be others but most not worth worrying about
 */

include(dirname(__FILE__) . '/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class NealBrothersUploadPage extends \COREPOS\Fannie\API\FannieUploadPage {

    public $themed = true;
    public $title = "Fannie - Neal Brothers Prices";
    public $header = "Upload Neal Brothers price file";
    public $description = '[Neal Brothers Catalogue Import] specialized vendor import tool.
        Column choices default to Neal Brothers price file layout.';

    protected $vendorID = 37;
    protected $vendorName = 'NEALBROTHERS';
    protected $results_details = '';
    protected $problem_details = '';
    protected $use_splits = false;
    // $use_js was true
    protected $use_js = false;

    // #'Preview 'default' is column-number, where A=0.
    protected $preview_opts = array(
        'sku' => array(
            'name' => 'sku',
            'display_name' => 'SKU *',
            'default' => 0,
            'required' => true
        ),
        'brand' => array(
            'name' => 'brand',
            'display_name' => 'Brand *',
            'default' => 1,
            'required' => True
        ),
        'desc' => array(
            'name' => 'desc',
            'display_name' => 'Description *',
            'default' => 2,
            'required' => True
        ),
        'upcx' => array(
            'name' => 'upcx',
            'display_name' => 'UPC Exp',
            'default' => 3,
            'required' => True
        ),
        'case_size' => array(
            'name' => 'case_size',
            'display_name' => 'Case Size *',
            'default' => 4,
            'required' => True
        ),
        'package' => array(
            'name' => 'package',
            'display_name' => 'Package *',
            'default' => 5,
            'required' => True
        ),
        'hst' => array(
            'name' => 'hst',
            'display_name' => 'HSTaxable',
            'default' => 6,
            'required' => True
        ),
        'case_cost' => array(
            'name' => 'case_cost',
            'display_name' => 'Case Cost *',
            'default' => 7,
            'required' => True
        ),
        'upc' => array(
            'name' => 'upc',
            'display_name' => 'UPC *',
            'default' => 8,
            'required' => True
        ),
    /* Others maybe some day
     */
        'unit_cost' => array(
            'name' => 'unit_cost',
            'display_name' => 'Unit Cost',
            'default' => 9,
            'required' => False
        ),
        'srp' => array(
            'name' => 'srp',
            'display_name' => 'Sugg. Price',
            'default' => 10,
            'required' => False
        ),
    );

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

        // NEW with Neal Brothers
        $this->rows_to_preview = 10;

        /* Cannot do this before assigning other vars because it calls process_file(),
         * i.e. everything will be done before it has the form-based values of
         * those vars to use.
         */
        $ret = parent::preprocess();
        return $ret;

    // preprocess
    }

    public function process_file($linedata, $indexes)
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
        $SKU = $this->get_column_index('sku');
        $UPC = $this->get_column_index('upc');
        $BRAND = $this->get_column_index('brand');
        // up to ' - ' is probably brand
        $DESCRIPTION = $this->get_column_index('desc');
        $CASE_SIZE = $this->get_column_index('case_size');
        $PACKAGE = $this->get_column_index('package');
        $CASE_COST = $this->get_column_index('case_cost');
        $HST = $this->get_column_index('hst');
        // These don't exist.
        $UNIT_COST = $this->get_column_index('unit_cost');
        $SRP = $this->get_column_index('srp');

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

        $prodQ ='UPDATE products
            SET cost=?,
                numflag= numflag | ? | ?,
                modified=' . $dbc->now() . '
            WHERE upc=?
                AND default_vendor_id=?';
        $prodP = $dbc->prepare($prodQ);

        $findVendorItemQ = 'SELECT upc,sku,cost,vendorID FROM vendorItems ' .
            'WHERE upc =? AND vendorID =?';
        $findVendorItemP = $dbc->prepare($findVendorItemQ);

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
        $non_item_upc = 0;
        $non_item_sku = 0;

        // Corwin:
        $currentBrand = "";
        $website = "";
        $currentSubBrand ="";
        $currentComment = "";
        $costIsCase = False;
        $isNewLine = False;
        $lastBrand = "";
        $lastSubBrand = "";
        $brandCount = 0;
        $subBrandCount = 0;
        $displayCount = 0;

        /* Neal Brothers:
         */
        $xx_upc = 0;
        $brand = '';

        /* ddd During-dev diagnostic.
         */
        $ddd = True;
        if ($ddd) {
            $diagFile = '/tmp/nealbrothers.txt';
            $df = fopen($diagFile,'w');
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

            // Noob: Can be empty and still set.
            if (!isset($data[$UPC])) {
                continue;
            }

            /* Clean all now so you don't need to later.
             */
            $data[$UPC] = trim($data[$UPC],' ');
            $data[$SKU] = trim($data[$SKU]);
            $data[$BRAND] = trim($data[$BRAND]);
            $data[$DESCRIPTION] = trim($data[$DESCRIPTION],' ');
            if ($CASE_COST !== False) {
                $data[$CASE_COST] = trim($data[$CASE_COST]);
            }
            if ($CASE_SIZE !== False) {
                $data[$CASE_SIZE] = trim($data[$CASE_SIZE],' $');
            }
            if ($UNIT_COST !== False) {
                $data[$UNIT_COST] = trim($data[$UNIT_COST],' $');
            }
            if ($PACKAGE !== False) {
                $data[$PACKAGE] = trim($data[$PACKAGE]);
            }
            if ($HST !== False) {
                $data[$HST] = trim($data[$HST]);
            }
            if ($SRP !== False) {
                $data[$SRP] = trim($data[$SRP]);
            }

            /* Odd bits.
             */

            /* Test for emtpy row.
             * HOW IF AT ALL SHOULD/COULD THIS BE USED FOR NB?
             *   If used at all s/p/b reset for each row.
             */
            if (True || trim(implode('',$data)) == "") {
                $currentBrand = "";
                $website = "";
                $currentSubBrand ="";
                $currentComment = "";
                $subBrandCostIsCase = False;
                $brandCostIsCase = False;
                $costIsCase = False;
                $itemCostIsCase = False;
                $isNewLine = False;
                //continue;
            }
            $non_empty_lines++;

            /* See if the row is not for an item: Test for upc in first column.
             * If not upc look in first column for:
             * - Brand
             * - SubBrand
             * - URL
             * And look in Description column for:
             * - cost-is-case flag, which may be at Brand or SubBrand level.
                && $cell != ''
             */
            $upc = $data[$UPC];
            if ($upc == '' || $upc == 0) {
                $no_upc++;
                continue;
            }
            // A Corwin thing?
            if (substr($upc,-3,3) == "XXX") {
                $xx_upc++;
                continue;
            }
            //$cell = trim($data[$UPC],' *');

            /* Neal Brothers - have checkdigits, to be removed.
             * 84114129628
             * 56932203057
             */

            /*
             * Neal:
             * It is for an item.
             * ?- UPC is occasionally empty
             * ?- SKU is never empty
             */
            $upc = str_replace(' ','',$upc);
            $checkdigit = '';
            if (preg_match('/^\d{10,14}$/',$upc)) {
                /* Discard checkdigit? */
                if ($this->remove_checkdigits) {
                    $checkdigit = substr($upc,-1,1);
                    $upc = substr($upc,0,-1);
                }
                /* Is this the place to validate the UPC?
                 * Do it before padding.
                 $calcCheck = generate_upc_checkdigit($upc);
                 * */
                $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
            } else {
                $non_item_upc++;
                //$this->problem_details .=
                $this->results_details .=
                "<br />Non-item UPC: $upc FOR SKU: $sku DESC: " . $data[$DESCRIPTION];
                continue;
            }

            // get data from appropriate columns
            $sku = ($SKU !== false) ? $data[$SKU] : '';
            /* Neal
             * Items are 4 digits.
             * alpha suffixes are usually but not always displays, upc = 0.
             * */
            if (!preg_match("/^\d{4}/",$sku)) {
                $non_item_sku++;
                //$this->problem_details .=
                $this->results_details .=
                "<br />Bad SKU: $sku FOR UPC: $upc DESC: " . $data[$DESCRIPTION];
                continue;
            }

            /* b1 Make $brand match the beginning of $description.
             * In some cases put it back later.
             */
            $brand = ($BRAND !== false) ? $data[$BRAND] : '';
            switch ($brand) {
                case "ARTISANA":
                    $brand = "PO ARTISANA";
                    break;
                case "CAPE HERB":
                    $brand = "CHS";
                    break;
                case "CHATHAM":
                    $brand = "CV";
                    break;
                case "DINE ALONE":
                    $brand = "DINE ALONE FOODS";
                    break;
                case "EARTH CHOICE":
                    $brand = "EARTH'S CHOICE";
                    break;
                case "FRED":
                case "FREDS":
                    $brand = "FRED'S BREAD";
                    break;
                case "HIPPIE":
                    $brand = "HIPPIE FOODS";
                    break;
                case "KH":
                    $brand = "KH COFFEE";
                    break;
                case "KOZLIK":
                    $brand = "KOZLIK'S";
                    break;
                case "LATE JULY":
                    $brand = "LJ";
                    break;
                case "OLEUM":
                    $brand = "OLEUM PRIORAT";
                    break;
                case "RAINCOAST":
                    $brand = "LS";
                    break;
                case "SENCHA":
                    $brand = "SENCHA NATURALS";
                    break;
                case "WOW":
                    $brand = "WOW BAKING COMPANY";
                    break;
            }
            /* Description and Brand
             * June 2017:
             * UPC with space inserted to avoid exponential notation
             *
             * Separate Brand and Description columns.
             * If there is no ' - ' in Description maybe:
             *  - ignore brand
             *  - revert to January-style Description - No, most of it relies on ' - '
             *
             * B: Brand. Generally keep as $brand.
             *    Jigger so the rest will work:
             *    FREDS -> FRED'S BREAD
             *    HIPPIE -> HIPPIE FOODS
             *    KOZLIK -> KOZLIK'S
             *    OLEUM -> OLEUM PRIORAT
             *    RAINCOAST -> LS RAINCOAST
             *    SENCHA -> SENCHA NATURALS
             *    WOW -> WOW BAKING COMPANY,
             *      also massage description as below but keep caps
             * C: Description
             * If ' - ' in Description: $parts = explode($desc, ' - ', 2)
             *   if count($parts) == 2
             *   Remove the part before ' - ' as $d1
             *   If $brand matches $d1 at the beginning: strpos($brand, $d1) === 0
             *     remove brand from $d1 and prefix anything remaining to $d2 as $desc
             *     str_replace, preg_replace
             * E.g.
             * Brand: NB, later -> "Neal Brothers"
             * Description: NB NATURAL SALSA - Habanero
             * $brand: NB
             * $description: NATURAL SALSA - Habanero
             *
             *
             * January 2017
             * Usually but not always: BRAND - Description
             *
             * 22May2017: The below is probably too complicated
             *  and will fail anyway.
             *  Instead:
             *  A few specific cases:
             *
             *  January 2017:
             * If the first word of $description is 3 chars or less
             *  and in some other cases: KOZLICK's
             * the 2nd+ words of $brand should be restored (prefixed) to description.
             * Except: 'LA TOURANGELLE'
             * LAT -> 'LA TOURANGELLE'
             *
             * Suppress any word that matches a package pattern /^\d+.+$/ or == $package
             *
             * A few cases:
             *  KETTLE where 'Chips' needs to be prefixed to description
             *   Unless $brand contains 'POPCORN'
             *  KHC -> 'KH' and Coffee prefixed to description
             *  KH and KHC $brand = 'KICKING HORSE'
             */
            $description = $data[$DESCRIPTION];
            $rawDesc = $description;
            if (strpos($description, "PO ARTISANA ") === 0) {
                if (strpos($description,"PO ARTISANA ORGANIC ") === 0) {
                    $description = str_replace("PO ARTISANA ORGANIC ",
                        "PO ARTISANA - ORGANIC ",$description);
                }
            }
            if (substr($description,0,4) == "WOW ") {
                if (strpos($description,"WOW BAKING CO ") === 0) {
                    $description = str_replace("WOW BAKING CO ",
                        "WOW BAKING COMPANY - ",$description);
                } elseif (strpos($description,"WOW BAKING ") === 0) {
                    $description = str_replace("WOW BAKING ",
                        "WOW BAKING COMPANY - ",$description);
                } elseif (strpos($description,"WOW ") === 0) {
                    $description = str_replace("WOW ",
                        "WOW BAKING COMPANY - ",$description);
                } else {
                    $noop = 1;
                }
            }
            /*
             *   If $brand matches $d1 at the beginning: strpos($brand, $d1) === 0
             *     remove brand from $d1 and prefix anything remaining to $d2 as $desc
             */
            if (strpos($description,' - ') !== False) {
                list($d1,$d2) = explode(' - ',$description,2);
                if (strpos($d1,$brand) === 0) {
                    $d1 = ($d1 == $brand) ? "" : substr($d1,strlen($brand));
                    $d1 = trim($d1);
                    if ($d1 != "") {
                        // Remove package size
                        $dBits = explode(" ",$d1);
                        for ($i=0 ; $i < count($dBits) ; $i++) {
                            if (preg_match('/\d(g|kg|ml|L)$/', $dBits[$i])) {
                                $dBits[$i] = '';
                            }
                        }

                        $d1 = implode(' ',$dBits);
                        $d1 = trim($d1);
                        //$d1 = (trim(implode(' ',$dBits)));
                    }
                    $description = ($d1 == "") ? $d2 : $d1 . ' ' . $d2;
                }
            }

            /* b2 June. Bits not dealt with:
             * $brand GFB squints GLUTEN FREE BAR, GLUTEN FREE BITES
             * $brand LAT OIL squints LAT OIL, LAT OIL SPRAY
             */
            /* June. Brands that are abbreviaions
             */
            switch ($brand) {
                case "CHS":
                    $brand = 'Cape Herb & Spice Co.';
                    break;
                case "CV":
                    $brand = 'Chatham Village';
                    break;
                case "NB":
                    $brand = "Neal Brothers";
                    break;
                case "KETTLE":
                    $brand = "Kettle Foods";
                    break;
                case "KH COFFEE":
                    $brand = "Kicking Horse Coffee";
                    break;
                case "LAT OIL":
                    $brand = 'La Tourangelle';
                    break;
                case "LJ":
                    $brand = "Late July";
                    break;
                case "LS":
                    //$brand = "Raincoast";
                    $brand = 'Lesley Stowes';
                    break;
            }

            /* January: No ' - ' to break on
            if (strpos($description,' - ') === False) {
                // Un-solved:
                // LS RAINCOAST CRISPS ON-THE-GO CRANBERRY HAZELNUT single serve
                if ($description ==
                    'LS RAINCOAST CRISPS ON-THE-GO CRANBERRY HAZELNUT single serve')
                {
                    $description =
                        'LS RAINCOAST CRISPS ON-THE-GO - CRANBERRY HAZELNUT single serve';
                } elseif ($description ==
                    'HIPPIE FOODS COCONUT CHIPS'
                )
                {
                    $description =
                        'HIPPIE FOODS - COCONUT CHIPS';
                }
                //$noop = 1;
            }
             */

            /* January: Massage $description before parsing.
            if (strpos($description, "PO ARTISANA ") === 0) {
                if (strpos($description,"PO ARTISANA ORGANIC ") === 0) {
                    $description = str_replace("PO ARTISANA ORGANIC ",
                        "PO ARTISANA - ORGANIC ",$description);
                }
            }
            if (substr($description,0,4) == "WOW ") {
                if (strpos($description,"WOW BAKING CO ") === 0) {
                    $description = str_replace("WOW BAKING CO ",
                        "Wow Baking Company - ",$description);
                } elseif (strpos($description,"WOW BAKING ") === 0) {
                    $description = str_replace("WOW BAKING ",
                        "Wow Baking Company - ",$description);
                } elseif (strpos($description,"WOW ") === 0) {
                    $description = str_replace("WOW ",
                        "Wow Baking Company - ",$description);
                } else {
                    $noop = 1;
                }
            }
            if (strpos($description, "LOTUS ") === 0) {
                if (strpos($description,"LOTUS FOODS RAMEN 4 PACK ") === 0) {
                    $description = str_replace("LOTUS FOODS RAMEN 4 PACK ",
                        "Lotus Foods ",$description) . ' 4-PACK';
                } elseif (strpos($description,"LOTUS FOODS RAMEN ") === 0) {
                    $description = str_replace("LOTUS FOODS RAMEN ",
                        "Lotus Foods ",$description);
                } elseif (strpos($description,"LOTUS FOODS RICE BOWLS ") === 0) {
                    $description = str_replace("LOTUS FOODS RICE BOWLS ",
                        "Lotus Foods ",$description) . ' BOWLS';
                } elseif (strpos($description,"LOTUS FOODS RICE ") === 0) {
                    $description = str_replace("LOTUS FOODS RICE ",
                        "Lotus Foods ",$description);
                } else {
                    $noop = 1;
                }
            }
             */

            /* January. Parse brand from description.
            $brand = '';
            $rawBrand = '';
            $dBits = explode(' - ', $description,2);
            if (count($dBits) == 2) {
                $brand = $dBits[0];
                $rawBrand = $dBits[0];
                $description = $dBits[1];
            }
            // No ' - ' to break on
            if ($brand == '') {
                $noop = 1;
            }
             */

            /* January: Cases where $rawBrand is too narrow, is only part of the brand.
             * Prefix part of it to $description
             */
            /* Multi-word changes:
             * WOW [alone] -> Wow Baking Company
             * DINE ALONE
             * GAGA FOR GLUTEN FREE -> GAGA
             * HIPPIE G -> HIPPIE FOODS G
             * KH COFFEE -> KHC
             */
            /* Other odd ones:
             * LA TOURANGEL et al.
             *  ...
             * LOTUS FOODS RAMEN -> 'LOTUS FOODS'
             * LOTUS FOODS RICE BOWLS
             *   $description .= " BOWL";
             *   -> LOTUS FOODS
             * PO ARTISANA ORGANIC with no ' - '
             *  'POS ARTISANA - '
             *  $organicFlag
             *
             */
            /* First two words of more than two
             *   Similar to Single word except for two
             * LOVE CHILD
             * NONA PIA'S
             * SENCHA NATURALS
             * TEA SQUARED
             * THREE FARMERS
             * Unsolved:
             * -> [] HIPPIE GRANOLA
             */
            // Single words
            /* January
            $bBits = explode(' ', $brand);
            $bDone = False;
            if (count($bBits) > 2) {
                $firstTwo = $bBits[0] . ' ' . $bBits[1];
                switch ($firstTwo) {
                    case "LA TOURANGELLE":
                    case "LOVE CHILD":
                    case "NONNA PIA'S":
                    case "SENCHA NATURALS":
                    case "TEA SQUARED":
                    case "THREE FARMERS":
                        $brand = $firstTwo;
                        unset($bBits[1]);
                        unset($bBits[0]);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        $bDone = True;
                        break;
                    case "KH COFFEE":
                        $brand = "Kicking Horse Coffee";
                        if (isset($bBits[2]) && substr($bBits[2],-1,1) == 'g') {
                            unset($bBits[2]);
                        }
                        unset($bBits[1]);
                        unset($bBits[0]);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        $bDone = True;
                        break;
                }
            }
            January */
            /* January
            if (!$bDone && count($bBits) > 1) {
                $firstWord = $bBits[0];
                switch ($bBits[0])
                {
                    case "CLIF":
                    case "ENERJIVE":
                    case "KOZLIK'S":
                    case "PACIFIC":
                    case "SENCHA":
                    case "SHASHA":
                    case "TAZO":
                        $brand = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                    case "KETTLE":
                        // Needs "chips" somewhere? Except for POPCORN.
                        if (substr($brand,-1,1) == 'g') {
                            //$brand = $bBits[0];
                            $brand = 'Kettle Foods';
                        } else {
                            //$brand = array_shift($bBits);
                            unset($bBits[0]);
                            $brand = 'Kettle Foods';
                            $b = 0;
                            for($b=0;$b < count($bBits);$b++) {
                                if (substr($bBits[$b],-1,1) == 'g') {
                                    $bBits[0] = "";
                                }
                            }
                            $description = implode(' ',$bBits) . ' ' . $description;
                            $description = preg_replace("/  +/",' ',$description);
                        }
                        break;
                    case "KHC":
                        // Needs "coffee" somewhere?
                        if (substr($brand,-1,1) == 'g') {
                            $brand = "Kicking Horse Coffee";
                            //$brand = $bBits[0];
                        } else {
                            $brand = array_shift($bBits);
                            $description = implode(' ',$bBits) . ' ' . $description;
                        }
                        break;
                    case "FB":
                        $brand = "Fred's Bread";
                        $b = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                    case "CHS":
                        $brand = 'Cape Herb and Spice';
                        $b = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                    case "CV":
                        $brand = 'Chatham Village';
                        $b = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                    case "LAT":
                        $brand = 'La Tourangelle';
                        $b = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                    case "LJ":
                        $brand = 'Late July';
                        $b = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                    case "LS":
                        $brand = 'Lesley Stowes';
                        $b = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                    case "NB":
                        $brand = 'Neal Brothers';
                        $b = array_shift($bBits);
                        $description = implode(' ',$bBits) . ' ' . $description;
                        break;
                }
            }
            January */

            /* Flags from description */
            if (stripos($description,'ORGANIC ') !== False) {
                $description = str_ireplace('ORGANIC ','',$description) . ' O';
                $organic = 1;
            }

/* '#j
$this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku | brand: $brand | 
                <br />rawDesc: $rawDesc | <br />desc: $description";
continue;
 */

            $case_size = ($CASE_SIZE !== false) ? $data[$CASE_SIZE] : '';
            $case_cost = ($CASE_COST !== false) ? $data[$CASE_COST] : '';
                $case_cost = str_replace('$',"",$case_cost);
                $case_cost = str_replace(',',"",$case_cost);
            $unit_cost = ($UNIT_COST !== false) ? $data[$UNIT_COST] : '';
                $unit_cost = str_replace('$',"",$unit_cost);
            $package = ($PACKAGE !== false) ? $data[$PACKAGE] : '';
            $hst = ($HST !== false) ? $data[$HST] : '';

            /* 
             * Neal:
             * Package
             * 35ml
             * - $size
             * - $unitofmeasure
             * $BATCHESU/testCase.php
             * Cases:
             * 80g
             * 12x30g
             * 6 units
             * 24bags
             */
             $size = 0;
             $unitofmeasure = '';
             if ($package == '') {
                 $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku | brand: $brand | desc: $description 
                     | package: EMPTY";
                 $noop = 1;
             } elseif (preg_match('/^(\d+) +([a-z]+)$/',$package,$matches)) {
                 // e.g. "6 units", "100 filters"
                 $size = $matches[1];
                 $unitofmeasure = 'ct';
             } elseif (preg_match('/^(\d+)([a-z]+)$/',$package,$matches)) {
                 $size = $matches[1];
                 $unitofmeasure = $matches[2];
                 switch ($unitofmeasure) {
                     case 'bags':
                         $unitofmeasure = 'ct';
                         break;
                 }
             }

            /* 
             * Case statement
             * E.g. 12, 6
             * - $case_size
             * $BATCHESU/testCase.php
             */
             $rawCase = '';
             $itemCostIsCase = False;
             if ($case_size == '') {
                 $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku | brand: $brand | desc: $description 
                     | case_size: EMPTY";
                 $noop = 1;
                 /* Good idea? Ever happen?
                  */
                 $case_size = 1;
             }
             if (!is_numeric($case_size)) {
                 $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku | brand: $brand | desc: $description 
                     | case_size: $case_size";
                 $noop = 1;
             }

             /* Unit cost
              * From case_cost and case_size
              */
             if ($unit_cost == '') {
                 $raw_unit_cost = ($case_cost / $case_size);
                 $unit_cost = round(($case_cost / $case_size),2,PHP_ROUND_HALF_UP);
             } else {
                 $raw_unit_cost = $unit_cost;
                 $unit_cost = round($unit_cost,2,PHP_ROUND_HALF_UP);
             }

            /* Can't process items w/o cost (usually promos/samples anyway)
             */
            $costTest = "{$unit_cost}{$case_cost}";
            if (empty($costTest)){
                $no_cost++;
                continue;
            }

             /* #'t Test:
              * Dump what you have: line# upc, sku, brand, description, case_size, size, unitofmeasure
              * to screen or to $problems
             */
             $this->problem_details .= "<br />rrC#: $rawRowCount | upc: $upc | sku: $sku | brand: $brand | desc: $description 
                | package: $package 
                | size: $size | uom: $unitofmeasure
                | units: $case_size
                | case_cost: $case_cost
                | unit cost: $unit_cost
                | raw unit cost: $raw_unit_cost
                ";

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
            // Neal v.1 has $srp but don't use it. v.2 doesn't have $srp.
            $srp = '0.00';
            $size .= $unitofmeasure;
            $qty = $case_size;
            $reg1 = sprintf("%.2f",$unit_cost);
            $regCase = sprintf("%.2f",$case_cost);
            $saleCase = '0.00';
            //$reg_unit = sprintf("%.2f",(!empty($reg1)) ? $reg1 : ($regCase / $qty));
            // need unit cost, not case cost
            $reg_unit = $reg1;
            $sale_unit = '0.00';

             /* #'O upc
              */

            /* Never for Neal
             */
            if (isset($SKU_TO_PLU_MAP[$sku])) {
                $upc = $SKU_TO_PLU_MAP[$sku];
                // '002' have price/lb in the upc.
                if (substr($size, -1) == '#' && substr($upc, 0, 3) == '002') {
                    $qty = trim($size, '# ');
                    $size = '#';
                } elseif (substr($size, -2) == 'LB' && substr($upc, 0, 3) == '002') {
                    $qty = trim($size, 'LB ');
                    $size = 'LB';
                }
            }

            /* syntax fixes.
             * trim $ off amounts as well as commas for the
             * occasional > $1,000 item
            $regCase = str_replace('$',"",$regCase);
            $regCase = str_replace(",","",$regCase);
            $reg1 = str_replace('$',"",$reg1);
            $reg1 = str_replace(",","",$reg1);
            $saleCase = str_replace('$',"",$saleCase);
            $saleCase = str_replace(",","",$saleCase);
             */
            $srp = str_replace('$',"",$srp);
            $srp = str_replace(",","",$srp);

            /* Neal Brothers doesn't have these, or maybe if 'ORGANIC' in description.
             * See the parsing of $data[$DESCRIPTION]
            $organic_flag = 0;
            // set gluten-free flag on g
            $gf_flag = 0;
             */
            $organic_flag = 0;
            $gf_flag = 0;

            /* Pass 1 false, Pass 2 true
                Production: if (true) {}
             */
            if ($this->update_products && !$this->dry_run) {
                // Update prodExtra and products
                $dbc->execute($extraP, array($reg_unit,$regCase, $qty, $upc));
                $dbc->execute($prodP, array($reg_unit,$organic_flag,$gf_flag,$upc,$VENDOR_ID));
                $updated_upcs[] = $upc;
            } else {
                //$this->results_details .= "<p>Skipped prodExtra and products updates.</p>";
                $noop = 1;
            }

            /* Have been moved up
            $findVendorItemQ = 'SELECT upc,sku,cost,vendorID FROM vendorItems ' .
                'WHERE upc =? AND vendorID =?';
            $findVendorItemP = $dbc->prepare($findVendorItemQ);
             */
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
                    $this->results_details .= "<br />SKU different: vendorItems: {$fir['sku']} Neal: $sku ";
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
                // Pass 1 false, pass 2 true
                if (true && !$this->dry_run) {
                    $ok = $dbc->execute($vendorItemPupdate,$argsUpdate);
                    if (!$ok) {
                        $this->results_details .= "<br />vI update failed";
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
                    $description,
                    $vendorDept == 0 ? null : $vendorDept,
                    $VENDOR_ID,
                    $sale_unit,
                    $srp,
                   date('Y-m-d H:i:s'),

                );
                // Pass 1 false, pass 2 true
                /* Defeat during Corwin development.
                 */
                if (true && !$this->dry_run) {
                    $ok = $dbc->execute($vendorItemPinsert,$argsInsert);
                    if (!$ok) {
                        $this->results_details .= "<br />vI insert failed";
                        break;
                    }
                }
            }

            /*
             * Defeat during development.
             * Neal Bros. data does have srp but
             *  prefer to run Recalculate SRPs after the load.
            if ($srpP && !$this->dry_run) {
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
        if (false && true && !$this->dry_run) {
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
            " higher: $new_cost_higher" .
            " ; lower: $new_cost_lower" .
            " ; same: $new_cost_same" .
            "<br />{$wba} to Vendor catalogue: $itemsNotFound" .
            "<br />Notices and issues:" .
            "<br />'no upc' (empty or 0) : $no_upc" .
            "<br />UPC ending in XXX : $xx_upc" .
            "<br />Non-item UPC : $non_item_upc" .
            "<br />Non-item SKU : $non_item_sku" .
            // "<br />'no upc' - implies Bulk: $no_upc" .
            // "<br />bad_upc_pattern: $bad_upc_pattern" .
            // "<br />could_not_fix_upc: $could_not_fix_upc" .
            "<br />no cost: $no_cost" .
            "<br />non_numeric_prices: $non_numeric_prices" .
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
                <br />Only changes cost, not description.
                </li>
                <li>Delete vendor items that are no longer in the catalogue.
                <br />Clears all the vendor items for this vendor and replaces
                them with what is in the upload.
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
                <li>Create Sale Batch
                    <br />Neal Brothers main data does not include sale prices.
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

    /*
     * Return the integer checkdigit for a UPC code.
     * Works for: UPC-A (10 or 11 +1), EAN13 (12+1) and
     *  a 13+1 format I don't know the name for.
     *  Works means: agrees with Neal Brothers checkdigits
     *   and a few other tests.
     * Based on: http://www.codediesel.com/php/generating-upc-check-digit/
     */
    function generate_upc_checkdigit($upc_code)
    {

        $odd_total  = 0;
        $even_total = 0;
        $check_digit = 0;
        if (strlen($upc_code) < 11) {
            $upc_code = str_pad($upc_code, 11, '0', STR_PAD_LEFT);
        }
        $upc_chars = str_split($upc_code);
        $chars_max = count($upc_chars);

        for($i=0; $i<$chars_max; $i++)
        {
            echo "\n$i. {$upc_chars[$i]}";
            if((($i+1)%2) == 0) {
                /* Sum even digits */
                $even_total += $upc_chars[$i];
                //$even_total += $upc_code[$i];
            } else {
                /* Sum odd digits */
                $odd_total += $upc_chars[$i];
                //$odd_total += $upc_code[$i];
            }
        }

        $sum = (3 * $odd_total) + $even_total;

        /* Get the remainder MOD 10*/
        $check_digit = $sum % 10;

        /* If the result is not zero, subtract the result from ten. */
        $check_digit = ($check_digit > 0) ? 10 - $check_digit : $check_digit;

        return $check_digit;
    }

}

FannieDispatch::conditionalExec(false);

