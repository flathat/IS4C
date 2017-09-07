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

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once(dirname(__FILE__) . '/../../../classlib2.0/FannieAPI.php');
}

/**
  @class SaMenuPage_WEFC_Toronto
*/
/**
 * Handheld-friendly means they work well in a small format.
 * This ~_WEFC_Toronto variant is for the utilities used in the 31Mar2017
 * physical inventory.
 */
class SaMenuPage_WEFC_Toronto extends FannieRESTfulPage {

    public $page_set = 'Plugin :: Shelf Audit';
    public $description = '[Menu] lists utilities used by WEFC Toronto in 2017.';
    public $themed = true;
    protected $title = 'Physical Inventory (ShelfAudit) Menu WEFC Toronto';
    protected $header = '';

    function css_content(){
        ob_start();
        ?>
input[type="submit"] {
    width:85%;
    font-size: 2em;
}
        <?php
        return ob_get_clean();
    }

    function get_view(){
        $dbc = $this->connection;
        $PS = $this->config->get('PLUGIN_SETTINGS');
        $SADB = $PS['ShelfAuditDB'];
        $dbc->selectDB($SADB);
        $inventory_year = '';
        $query = "SELECT datetime FROM sa_inventory LIMIT 1";
        $statement = $dbc->prepare($query);
        $result = $dbc->execute($query,array());
        while ($row = $dbc->fetch_array($result)) {
            $inventory_year = substr($row['datetime'],0,4);
            break;
        }
        if ($inventory_year == '') {
            $inventory_year = date('Y');
        }
        ob_start();
        ?>
<!doctype html>
<html>
<head>
    <title>Handheld Menu</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<p>
<a class="btn btn-default btn-lg"
    href="SaScanningPage_WEFC_Toronto.php" target="_Scan" />Inventory Scan</a>
<hr />
<a class="btn btn-default btn-lg"
    href="SaReportPage_WEFC_Toronto.php" target="_List" />Inventory Item List</a>
<hr />
<a class="btn btn-default btn-lg"
    href="SaUpdatesPage.php" target="_Update" />Updates to Final Report Items</a>
<hr />
<a class="btn btn-default btn-lg"
href="SaFinalReportPage.php?level=summary&date1=<?php echo $inventory_year; ?>-03-31&date2=''"
 target="_Summary"/>Final Inventory Report, Summary</a>
<hr />
<a class="btn btn-default btn-lg"
href="SaFinalReportPage.php?level=detail&date1=<?php echo $inventory_year; ?>-03-31&date2=''"
 target="_Detail"/>Final Inventory Report, Detail</a>
<hr />
</p>
</body>
</html>
        <?php
        return ob_get_clean();

    // get_view
    }

    public function helpContent()
    {
        $ret = '';
        $ret .= '<p>Menu of Physical Inventory Count utilties.
            </p>';
        $ret .= '<p>For a new Inventory:
            <ul>
                <li>Set aside the table <tt>sa_inventory</tt> if it
                contains items from a previous inventory
                and then make an empty copy of it.
                </li>
                <li>Do the same for the table <tt>sa_inventory_products</tt>.
                </li>
            </ul>
            </p>';
        return $ret;
    }

// class
}

FannieDispatch::conditionalExec();

?>
