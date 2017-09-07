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
/* 28Mar2017 EL WEFC_Toronto version
 * No changes yet.
 * I'm not sure what this is, whether it is unfinished or obsolete.
 * - The SaList table doesn't exist, isn't created by the plugin
 *   and nothing in the plugin populates it.
 *   It is almost identical to sa_inventory.
 * I might try changing this to use sa_inventory and see what it does.
 * Report fields are:
 * - UPC, SKU, Vendor, Brand, Description, Size, Qty
 * SaReport.php fields are:
 * - Date+Time, UPC, Description, Qty, Unit Cost, Total Cost,
 *   Normal Retail, Current Retail, Sale, Total Retail
 */

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class SaItemList_WEFC_Toronto extends SaHandheldPage
{
    public $page_set = 'Plugin :: Shelf Audit';
    public $description = '[Build List] is an interface for scanning and entering quantities on
    hand using a handheld device.';
    protected $enable_linea = true;

    public function preprocess()
    {
        $dbc = $this->connection;
        $settings = $this->config->get('PLUGIN_SETTINGS');

        if (FormLib::get('action') === 'save') {
            $upc = FormLib::get('upc');
            $qty = FormLib::get('qty');
            $dbc->selectDB($settings['ShelfAuditDB']);
            $model = new SaListModel($dbc);
            $model->upc(BarcodeLib::padUPC($upc));
            $model->clear(0);
            $entries = $model->find('date', true);
            if (count($entries) > 0) {
                $entries[0]->tdate(date('Y-m-d H:i:s'));
                $entries[0]->quantity($qty);
                $entries[0]->save();
            } else {
                $model->tdate(date('Y-m-d H:i:s'));
                $model->quantity($qty);
                $model->save();
            }

            echo $qty;
            echo 'quantity updated';
            return false;
        }

        if (FormLib::get('clear') === '1') {
            $table = $settings['ShelfAuditDB'] . $dbc->sep() . 'SaList';
            $res = $dbc->query('
                UPDATE ' . $table . '
                SET clear=1
            ');
            return true;
        }

        $upc = FormLib::get_form_value('upc_in','');
        if ($upc !== '') {
            $upc = BarcodeLib::padUPC($upc);
            $this->current_item_data['upc'] = $upc;
            $prep = $dbc->prepare('
                SELECT p.description,
                    p.brand,
                    p.size,
                    COALESCE(s.quantity, 0) AS qty
                FROM products AS p
                    LEFT JOIN ' . $settings['ShelfAuditDB'] . $dbc->sep() . 'SaList AS s ON p.upc=s.upc AND s.clear=0
                WHERE p.upc=?
            ');
            $row = $dbc->getRow($prep, array($upc));
            if ($row) {
                $this->current_item_data['desc'] = $row['brand'] . ' ' . $row['description'] . ' ' . $row['size'];
                $this->current_item_data['qty'] = $row['qty'];
            }
        }
        return true;
    } 

    public function body_content()
    {
        $elem = '#upc_in';
        if (isset($this->current_item_data['upc']) && isset($this->current_item_data['desc'])) $elem = '#cur_qty';
        $this->addOnloadCommand('$(\'' . $elem . '\').focus();');
        $this->addOnloadCommand("enableLinea('#upc_in');\n");
        ob_start();
        $this->upcForm($elem);
        $this->addOnloadCommand("\$('form:first div.form-inline').append('<a class=\"btn btn-default\" href=\"?list=1\">View List</a>');");
        if (isset($this->current_item_data['upc']) && !isset($this->current_item_data['desc'])) {
            echo '<div class="alert alert-danger">Item not found (' 
                . $this->current_item_data['upc'] . ')</div>'; 
        } elseif (isset($this->current_item_data['upc'])) {
            $this->qtyForm($elem);
            // prevent additive behavior. new qty here overwrites previous
            $this->addOnloadCommand("\$('#old-qty').html(0);\n");
            echo '</div>';
        } elseif (FormLib::get('list') !== '') {
            echo $this->getList();
        }

        return ob_get_clean();
    }

    private function getList()
    {
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $prep = $this->connection->prepare('
            SELECT s.upc,
                p.brand,
                p.description,
                p.size,
                s.quantity as qty,
                v.sku,
                n.vendorName
            FROM ' . $settings['ShelfAuditDB'] . $this->connection->sep() . 'SaList AS s
                ' . DTrans::joinProducts('s') . '
                LEFT JOIN vendorItems AS v ON p.upc=v.upc AND p.default_vendor_id=v.vendorID
                LEFT JOIN vendors AS n ON p.default_vendor_id=n.vendorID
            WHERE s.clear=0
                AND s.quantity <> 0
            ORDER BY s.tdate DESC
        ');
        $res = $this->connection->execute($prep);
        $ret = '
            <div class="table-responsive">
            <table class="table table-bordered table-striped small">
            <tr>
                <th>UPC</th>
                <th>SKU</th>
                <th>Vendor</th>
                <th>Brand</th>
                <th>Description</th>
                <th>Size</th>
                <th>Qty</th>
            </tr>';
        while ($row = $this->connection->fetchRow($res)) {
            $ret .= sprintf('<tr>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%d</td>
                </tr>',
                $row['upc'],
                $row['sku'],
                $row['vendorName'],
                $row['brand'],
                $row['description'],
                $row['size'],
                $row['qty']
            ); 
        }
        $ret .= '</table></div>';
        $ret .= '<p>
            <a href="?clear=1" class="btn btn-default btn-danger"
                onclick="return window.confirm(\'Clear list?\');">
                Clear List
            </a>
            </p>';

        return $ret;
    }
}

FannieDispatch::conditionalExec();

