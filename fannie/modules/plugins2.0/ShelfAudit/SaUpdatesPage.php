<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Copyright 2017 West End Food Co-op, Toronto

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
  @class SaUpdatesPage
*/
class SaUpdatesPage extends FanniePage
{

    public $page_set = 'Plugin :: Shelf Audit';
    public $description = '[Inventory] Update the data for the Final Report
    from post-Inventory Count day scans and product changes.';
    public $themed = true;
    protected $title = 'Inventory Final Report Data Updates';
    protected $header = 'Inventory Final Report Data Updates';
    protected $messages = array();
    protected $fannie_name = '';


    public function preprocess()
    {

        $dbc = $this->connection;
        $OP = $this->config->get('OP_DB') . $dbc->sep();
        $PS = $this->config->get('PLUGIN_SETTINGS');
        $SADB = $PS['ShelfAuditDB'];
        $dbc->selectDB($SADB);
        $time_now = date('Y-m-d H:i:s');
        $item_maint_URL = $this->config->get('URL') .
            'item/ItemEditorPage.php?searchupc=';
        $imTag = '<a href="' . $item_maint_URL;
        $item_count = 0;
        $item_limit = 99999;
        $update_limit = 99999;
        $update_count = 0;
        $insert_limit = 99999;
        $insert_count = 0;
        // Needs to be "this year" unless overridden from the form.
        $year_now = date('Y');
        $count_date = "{$year_now}-03-31 23:59:59";
        $this->fannie_name = _('Fannie');
        $dry_run = (FormLib::get('dry_run', 1) == 1) ? True : False;
        $change_msg = '';

        /* M1. Zero Cost */
        if (FormLib::get('zero_cost','') != '') {
            $this->messages[] = '<p style="font-weight:bold;">Report from Zero Cost</p>';
            /* Get the sa_inventory_products items that have zero cost
             * and the current cost in Fannie.
             */
            $query = "SELECT i.upc, i.cost AS icost, p.cost AS pcost, p.description
                FROM sa_inventory_products AS i
                LEFT JOIN {$OP}products AS p ON p.upc = i.upc
                WHERE i.cost = 0";
            $statement = $dbc->prepare($query);
            $result = $dbc->execute($statement,array());
            $queryU = "UPDATE sa_inventory_products SET cost = ? WHERE upc = ?";
            $statementU = $dbc->prepare($queryU);

            $item_count = 0;
            if ($dbc->numRows($result) > 0) {
                $this->messages[] = "<ol>";
            } else {
                $this->messages[] = "<p>No zero cost items remain.</p>";
            }
            if ($dry_run) {
                $change_msg = "Would change";
            } else {
                $change_msg = "Changed";
            }
            while ($row = $dbc->fetch_array($result)) {
                $item_count++;
                /* If the cost in Fannie is now not zero
                 *  update the sa_inventory_products item.
                 */
                if ($row['pcost'] > 0) {
                    $args = array($row['pcost'],$row['upc']);
                    $ok = True;
                    if (!$dry_run) {
                        $ok = $dbc->execute($statementU,$args);
                    }
                    if ($ok === False) {
                        $this->messages[] = sprintf('<li>**ERROR Failed to Change cost of
                            %s%s">%s</a> %s to %0.2f</li>',
                            $imTag, $row['upc'], $row['upc'],
                            $row['description'], $row['pcost']);
                    } else {
                        $this->messages[] = sprintf('<li>%s cost of %s%s">%s</a> %s to %0.2f</li>',
                            $change_msg,
                            $imTag, $row['upc'], $row['upc'],
                            $row['description'], $row['pcost']);
                        $update_count++;
                    }
                } elseif ($row['pcost'] == 0) {
                    $this->messages[] = sprintf('<li>The cost of %s%s">%s</a> %s is still zero.</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $row['description']);
                } elseif ($row['description'] == 'NULL') {
                    $this->messages[] = sprintf('<li>Item %s is not in %s .</li>',
                        $row['upc'],
                        $this->fannie_name
                    );
                }
                if ($item_count > $item_limit) {
                    break;
                }
                if ($update_count >= $update_limit) {
                    break;
                }
            }
            /*
            if ($item_count > 0) {
                $this->messages[] = "</ol>";
            }
            if ($item_count > $item_limit) {
                $this->messages[] = sprintf('<p class="big_message bold_message">
                    TERMINATED run after %s items.</p>',
                    $item_count);
            }
            if ($update_count >= $update_limit) {
                $this->messages[] = sprintf('<p class="big_message bold_message">
                    TERMINATED run after %s updates.</p>',
                    $update_count);
            }
             */

        // zero_cost
        }

        /* M2. Update Cost */
        if (FormLib::get('update_cost','') != '') {
            $this->messages[] = '<p style="font-weight:bold;">Report from Update Cost</p>';
            /* Get the sa_inventory_products items that have been update in Fannie
             * since Count day and have p.cost different from i.cost.
             */
            //$count_date = "2017-03-31 23:59:59";
            $ignore_departments = "";
            if (FormLib::get('ignore_departments','') != '') {
                $ignore_departments = " AND p.department NOT IN (" .
                    FormLib::get('ignore_departments') . ")";
            }
            // LEFT or INNER?
            $query = "SELECT i.upc, i.cost AS icost, p.cost AS pcost, p.description
                FROM sa_inventory_products AS i
                LEFT JOIN {$OP}products AS p ON p.upc = i.upc
                WHERE p.modified > '{$count_date}' AND i.cost != p.cost{$ignore_departments}";
            $statement = $dbc->prepare($query);
            $result = $dbc->execute($statement,array());
            $queryU = "UPDATE sa_inventory_products SET cost = ? WHERE upc = ?";
            $statementU = $dbc->prepare($queryU);

            $item_count = 0;
            if ($dbc->numRows($result) > 0) {
                $this->messages[] = "<ol>";
            } else {
                $this->messages[] = "<p>No zero cost items remain.</p>";
            }
            if ($dry_run) {
                $change_msg = "Would Change";
            } else {
                $change_msg = "Changed";
            }
            while ($row = $dbc->fetch_array($result)) {
                $item_count++;
                /* If the cost in Fannie is now not zero
                 *  update the sa_inventory_products item.
                 */
                if ($row['pcost'] > 0) {
                    $args = array($row['pcost'],$row['upc']);
                    $ok = True;
                    if (!$dry_run) {
                        $ok = $dbc->execute($statementU,$args);
                    }
                    if ($ok === False) {
                        $this->messages[] = sprintf('<li>**ERROR Failed to Change 
                            cost of %s%s">%s</a> %s from %0.2f to %0.2f</li>',
                            $imTag, $row['upc'], $row['upc'],
                            $row['description'], $row['icost'], $row['pcost']);
                    } else {
                        $this->messages[] = sprintf('<li>%s cost of %s%s">%s</a> %s from %0.2f to %0.2f</li>',
                            $change_msg,
                            $imTag, $row['upc'], $row['upc'],
                            $row['description'], $row['icost'], $row['pcost']);
                        $update_count++;
                    }
                } elseif ($row['pcost'] == 0) {
                    $this->messages[] = sprintf('<li>The cost of %s%s">%s</a> %s is still zero.</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $row['description']);
                } elseif ($row['description'] == 'NULL') {
                    $this->messages[] = sprintf('<li>Item %s is not in %s .</li>',
                        $row['upc'],
                        $this->fannie_name
                    );
                }
                if ($item_count > $item_limit) {
                    break;
                }
                if ($update_count >= $update_limit) {
                    break;
                }
            }
            /*
            if ($item_count > 0) {
                $this->messages[] = "</ol>";
            }
            if ($item_count > $item_limit) {
                $this->messages[] = sprintf('<p class="big_message bold_message">
                    TERMINATED run after %s items.</p>',
                    $item_count);
            }
             */

            // update_cost
        }


        /* M3. Update Department */
        if (FormLib::get('update_department','') != '') {
            $this->messages[] = '<p style="font-weight:bold;">Report from Update Department</p>';
            /* Get the sa_inventory_products items that have been updated in Fannie
             * since Count day and have p.department different from i.cost.
             */
            //$count_date = "2017-03-31 23:59:59";
            // LEFT or INNER?
            $query = "SELECT i.upc, i.p_department AS idepartment, p.department AS pdepartment,
                p.description
                FROM sa_inventory_products AS i
                LEFT JOIN {$OP}products AS p ON p.upc = i.upc
                WHERE p.modified > '{$count_date}' AND i.p_department != p.department";
            $statement = $dbc->prepare($query);
            $result = $dbc->execute($statement,array());
            $queryU = "UPDATE sa_inventory_products SET p_department = ? WHERE upc = ?";
            $statementU = $dbc->prepare($queryU);

            $item_count = 0;
            if ($dbc->numRows($result) > 0) {
                $this->messages[] = "<ol>";
            } else {
                $this->messages[] = "<p>No department changes to make.</p>";
            }
            if ($dry_run) {
                $change_msg = "Would Change";
            } else {
                $change_msg = "Changed";
            }
            while ($row = $dbc->fetch_array($result)) {
                $item_count++;
                /* If the cost in Fannie is now not zero
                 *  update the sa_inventory_products item.
                 */
                if (true) {
                    $args = array($row['pdepartment'],$row['upc']);
                    $ok = True;
                    if (!$dry_run) {
                        $ok = $dbc->execute($statementU,$args);
                    }
                    if ($ok === False) {
                        $this->messages[] = sprintf('<li>**ERROR Failed to Change
                            department of %s%s">%s</a> %s from %s to %s</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $row['description'], $row['idepartment'], $row['pdepartment']);
                    } else {
                        $this->messages[] = sprintf('<li>%s department of %s%s">%s</a>
                            %s from %s to %s</li>',
                        $change_msg,
                        $imTag, $row['upc'], $row['upc'],
                        $row['description'], $row['idepartment'], $row['pdepartment']);
                        $update_count++;
                    }
                } elseif ($row['pcost'] == 0) {
                    $this->messages[] = sprintf('<li>The cost of %s%s">%s</a> %s is still zero.</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $row['description']);
                } elseif ($row['description'] == 'NULL') {
                    $this->messages[] = sprintf('<li>Item %s is not in %s .</li>',
                        $row['upc'],
                        $this->fannie_name
                    );
                }
                if ($item_count > $item_limit) {
                    break;
                }
                if ($update_count >= $update_limit) {
                    break;
                }
            }
            /*
            if ($item_count > 0) {
                $this->messages[] = "</ol>";
            }
            if ($item_count > $item_limit) {
                $this->messages[] = sprintf('<p class="big_message bold_message">
                    TERMINATED run after %s items.</p>',
                    $item_count);
            }
             */

        // update_department
        }

        /* M4. Not in POS */
        if (FormLib::get('not_in_POS','') != '') {
            $this->messages[] = '<p style="font-weight:bold;">Report from: Complete Not in POS</p>';
            /* Get the sa_inventory_products items that were not in Fannie on Count Day
             * and the values from Fannie now.
             */
            $query = "SELECT i.upc AS upc,
                COALESCE(p.upc,'') AS p_upc,
                COALESCE(p.cost, 0.00) AS cost,
                COALESCE(p.department,0) AS department,
                COALESCE(p.brand,'') AS brand,
                COALESCE(p.description,'') AS description,
                COALESCE(p.size,'') AS size,
                COALESCE(p.unitofmeasure,'') AS unitofmeasure,
                COALESCE(p.scale,9) AS scale
                FROM sa_inventory_products AS i
                LEFT JOIN {$OP}products AS p ON p.upc = i.upc
                WHERE i.inProducts = 0";
            $statement = $dbc->prepare($query);
            $result = $dbc->execute($statement,array());
            $queryU = "UPDATE sa_inventory_products SET
                cost = ?,
                p_department = ?,
                p_brand = ?,
                p_description = ?,
                p_size = ?,
                p_unitofmeasure = ?,
                p_scale = ?,
                modified = ?,
                inProducts = ?,
                package = ?
                WHERE upc = ?";
            $statementU = $dbc->prepare($queryU);

            $item_count = 0;
            if ($dbc->numRows($result) > 0) {
                $this->messages[] = "<ol>";
            } else {
                $this->messages[] = "<p>No not-in-POS items to update.</p>";
            }
            if ($dry_run) {
                $change_msg = "Would update";
            } else {
                $change_msg = "Updated";
            }
            while ($row = $dbc->fetch_array($result)) {
                $item_count++;
                if ($item_count > $item_limit) {
                    break;
                }
                /* If the cost in Fannie (products.cost) is now not zero and is not null
                 *  AND products.department is not null
                 *  AND products.description is not null
                 *  update the sa_inventory_products item.
                 *   - inProducts = 1
                 *   - modified = $time_now;
                 */
                if ($row['p_upc'] == '') {
                    $sourceMessage = "";
                    $vendorName = $this->getVendorName($row['upc'],$dbc);
                    if ($vendorName != "") {
                        $sourceMessage = " but is in the $vendorName catalogue.";
                    } else {
                        $brandName = $this->getBrandName($row['upc'],$dbc);
                        if ($brandName != "") {
                            $sourceMessage = ". It is not in a Vendor Catalogue " .
                                "but Brand may be: {$brandName}.";
                        } else {
                            $sourceMessage = ". It is not in a Vendor Catalogue " .
                                "and the Brand Prefix of the UPC is not known.";
                        }
                    }
                    $this->messages[] = sprintf('<li>Item %s%s">%s</a> is not in 
                        %s%s</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $this->fannie_name,
                        $sourceMessage);
                    continue;
                } 
                $ef = array();
                $essential_fields = '';
                if ($row['cost'] == 0) { $ef[] = 'cost'; }
                if ($row['department'] == 0) { $ef[] = 'department'; }
                if ($row['description'] == '') { $ef[] = 'description'; }
                if (!empty($ef)) {
                    $essential_fields = implode(', ',$ef);
                    $this->messages[] = sprintf('<li>Item %s%s">%s</a> is in %s
                        but lacks: %s</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $this->fannie_name,
                        $essential_fields
                    );
                } elseif (true) {
                    $scale = ($row['scale'] == 9) ? 'null' : $row['scale'];
                    $package = $row['size'];
                    $package .=  ($row['unitofmeasure'] != '') ? ' ' .
                        $row['unitofmeasure'] : '';
                    $args = array(
                        $row['cost'],
                        $row['department'],
                        $row['brand'],
                        $row['description'],
                        $row['size'],
                        $row['unitofmeasure'],
                        $scale,
                        $time_now,
                        1,
                        $package,
                        $row['upc']);
                    $ok = True;
                    if (!$dry_run) {
                        $ok = $dbc->execute($statementU,$args);
                    }
                    if ($ok === False) {
                        $this->messages[] = sprintf('<li>**ERROR Failed to Update Item %s%s">%s</a> %s %s .</li>',
                            $imTag, $row['upc'], $row['upc'],
                            $row['brand'],
                            $row['description']
                        );
                    } else {
                        $this->messages[] = sprintf('<li>%s Item %s%s">%s</a> %s %s .</li>',
                            $change_msg,
                            $imTag, $row['upc'], $row['upc'],
                            $row['brand'],
                            $row['description']
                        );
                        $update_count++;
                        if ($update_count >= $update_limit) {
                            break;
                        }
                    }
                }
            }
            /*
            if ($item_count > 0) {
                $this->messages[] = "</ol>";
            }
            if ($item_count > $item_limit) {
                $this->messages[] = sprintf('<p class="big_message bold_message">
                    TERMINATED run after %s items.</p>',
                    $item_count);
            }
            if ($update_count >= $update_limit) {
                $this->messages[] = sprintf('<p class="big_message bold_message">
                    TERMINATED run after %s updates.</p>',
                    $update_count);
            }
             */

        // not_in_POS
        }

        /* M5. New to Inventory */
        if (FormLib::get('new_to_Inventory','') != '') {
            $this->messages[] = '<p style="font-weight:bold;">Report from: Add New to Inventory</p>';
            /* Get the sa_inventory items that are not in sa_inventory_products
             * and add them to sa_inventory_products if they have the essential fields in Fannie.
             */
            $query = "SELECT i.upc AS upc,
                COALESCE(p.upc,'') AS p_upc,
                COALESCE(p.cost, 0.00) AS cost,
                COALESCE(p.department,0) AS department,
                COALESCE(p.brand,'') AS brand,
                COALESCE(p.description,'') AS description,
                COALESCE(p.size,'') AS size,
                COALESCE(p.unitofmeasure,'') AS unitofmeasure,
                COALESCE(p.scale,9) AS scale,
                p.modified
                FROM sa_inventory AS i
                LEFT JOIN sa_inventory_products AS ip ON ip.upc = i.upc
                LEFT JOIN {$OP}products AS p ON p.upc = i.upc
                WHERE ip.upc IS NULL";
            $statement = $dbc->prepare($query);
            $result = $dbc->execute($statement,array());
            $queryI = "INSERT IGNORE INTO sa_inventory_products
                (upc, inProducts, cost, modified,
                p_department, p_brand, p_description, p_size, p_unitofmeasure,
                p_scale, package)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)";
            $statementI = $dbc->prepare($queryI);

            $item_count = 0;
            if ($dbc->numRows($result) > 0) {
                $this->messages[] = "<ol>";
            } else {
                $this->messages[] = "<p>No New-to-Inventory items to Add.</p>";
            }
            if ($dry_run) {
                $change_msg = "Would Add";
            } else {
                $change_msg = "Added";
            }
            while ($row = $dbc->fetch_array($result)) {
                $item_count++;
                if ($item_count > $item_limit) {
                    break;
                }
                /* If the cost in Fannie (products.cost) is now not zero and is not null
                 *  AND products.department is not null
                 *  AND products.description is not null
                 *  insert the sa_inventory_products item.
                 *   - inProducts = 1
                 *   - modified = products.modified
                 */
                if ($row['p_upc'] == '') {
                    $this->messages[] = sprintf('<li>Item %s%s">%s</a> is not in %s
                        but may be in a Vendor Catalog.</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $this->fannie_name);
                    continue;
                } 
                $ef = array();
                $essential_fields = '';
                if ($row['cost'] == 0) { $ef[] = 'cost'; }
                if ($row['department'] == 0) { $ef[] = 'department'; }
                if ($row['description'] == '') { $ef[] = 'description'; }
                if (!empty($ef)) {
                    $essential_fields = implode(', ',$ef);
                    $this->messages[] = sprintf('<li>Item %s%s">%s</a> is in %s but lacks: %s</li>',
                        $imTag, $row['upc'], $row['upc'],
                        $this->fannie_name,
                        $essential_fields
                    );
                } elseif (true) {
                    $scale = ($row['scale'] == 9) ? 'null' : $row['scale'];
                    $package = $row['size'];
                    $package .=  ($row['unitofmeasure'] != '') ? ' ' . $row['unitofmeasure'] : '';
                    $args = array(
                        $row['upc'],
                        1,
                        $row['cost'],
                        $row['modified'],
                        $row['department'],
                        $row['brand'],
                        $row['description'],
                        $row['size'],
                        $row['unitofmeasure'],
                        $scale,
                        $package
                    );
                    $ok = True;
                    if (!$dry_run) {
                        $ok = $dbc->execute($statementI,$args);
                    }
                    if ($ok === False) {
                        $this->messages[] = sprintf('<li>**ERROR Failed to Add Item %s%s">%s</a> %s %s .</li>',
                            $imTag, $row['upc'], $row['upc'],
                            $row['brand'],
                            $row['description']
                        );
                    } else {
                        $this->messages[] = sprintf("<li>%s Item %s%s\">%s</a> %s %s .</li>",
                            $change_msg,
                            $imTag, $row['upc'], $row['upc'],
                            $row['brand'],
                            $row['description']
                        );
                        $insert_count++;
                        if ($insert_count >= $insert_limit) {
                            break;
                        }
                    }
                }
            }

        // new_to_Inventory
        }

        if ($item_count > 0) {
            $this->messages[] = "</ol>";
        }
        if ($item_count > $item_limit) {
            $this->messages[] = sprintf('<p class="big_message bold_message">
                TERMINATED run after %s items.</p>',
                $item_count);
        }
        if ($update_count >= $update_limit) {
            $this->messages[] = sprintf('<p class="big_message bold_message">
                TERMINATED run after %s updates.</p>',
                $update_count);
        }
        if ($insert_count >= $insert_limit) {
            $this->messages[] = sprintf('<p class="big_message bold_message">
                TERMINATED run after %s adds.</p>',
                $insert_count);
        }
        if ($dry_run && ($insert_count > 0 || $update_count > 0)) {
            $this->messages[] = '<p class="big_message bold_message">
                Information only: NO UPDATES OR ADDITIONS were made.</p>';
        }

        // Return true so body_content() will be called.
        return true;
    }


    function css_content(){
        $ret = '';
        $ret .= "
    .big_message {
        font-size:1.5em;
    }
    .bold_message {
        font-weight:bold;
    }
    ";
        return $ret;
    }


    function body_content(){
        ob_start();
        if (!empty($this->messages)) {
            echo "<h4>Report from the previous run</h4>";
            foreach ($this->messages as $message) {
                if (substr($message,0,1) != '<') {
                    echo '<br />';
                }
                echo $message;
            }
        }
?>
        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" id="UpdateForm" >
        <h3>Menu</h3>
            <ul>
                <li> <input type="radio" name="dry_run" value="0" checked /> Make changes
                 &nbsp;
                     <input type="radio" name="dry_run" value="1" /> Information only
                </li>
                <li><input type="submit" name="zero_cost" value="Zero Cost" />
                Get non-zero costs that are now available in
                <?php echo $this->fannie_name; ?> for 
                Final Reprort items
                that have cost of zero.
                </li>
                <li><input type="submit" name="update_cost" value="Update Cost" />
                Update cost of Final Reprort items from costs in
                <?php echo $this->fannie_name; ?> that were
                changed since Inventory Count Day.
                <br />Exclude changes in these departments: <input name="ignore_departments"
                 type="text" size=20 />
                 &nbsp; <span style="font-size:0.9em;">(Comma-separated list of department numbers.)</span>
                </li>
                <li><input type="submit" name="update_department" value="Update Department" />
                Change the department for Final Reprort items
                where it is now different in <?php echo $this->fannie_name; ?>
                from what it was on Inventory Day.
                </li>
                <li><input type="submit" name="not_in_POS" value="Complete Not in POS" />
                Complete, for the Final Report, data about items that were entered (scanned)
                during the Inventory Count
                but were not in <?php echo $this->fannie_name; ?> at that time
                and are in <?php echo $this->fannie_name; ?> now.
                <br />These are the "Not in Inventory Products" items on the current Final Report.
                </li>
                <li><input type="submit" name="new_to_Inventory" value="Add New to Inventory" />
                Add, for the Final Report, data about items that were entered (scanned)
                to the Inventory Count data after Count Day
                and are in <?php echo $this->fannie_name; ?> now.
                <br />These are the "New to Inventory" items on the current Final Report.
                </li>
            </ul>
        </form>
<?php
        return ob_get_clean();
    }

    /* @return the name of the Vendor if the upc is known in vendorItems
     * or empty string;
     */
    function getVendorName($upc, $dbc)
    {
        $ret = "";
        $OP = $this->config->get('OP_DB') . $dbc->sep();
        $q = "SELECT DISTINCT vendorName
            FROM {$OP}vendorItems i
            INNER JOIN {$OP}vendors v ON v.vendorID = i.vendorID
            WHERE i.upc = ?";
        $s = $dbc->prepare($q);
        $r = $dbc->execute($s,array($upc));
        while ($row = $dbc->fetch_array($r)) {
            $ret .= sprintf("%s%s",
                ($ret == '') ? '' : ', ',
                $row['vendorName']);
        }
        return $ret;
    }

    /* @return the brand name if there is the brand prefix
     * is known in vendorItems
     * or empty string;
     */
    function getBrandName($upc, $dbc)
    {
        $ret = "";
        $OP = $this->config->get('OP_DB') . $dbc->sep();
        $brandPrefix = substr($upc,0,8);
        if ($brandPrefix != '00000000') {
            $brandPrefix = '^' . $brandPrefix;
            /* Base the check in products. Doesn't make much difference.
            $q = "SELECT DISTINCT CONCAT(vendorName,' : ',brand) AS vendor_brand
                FROM {$OP}products p
                INNER JOIN {$OP}vendors v ON v.vendorID = p.default_vendor_id
                WHERE p.upc REGEXP ?";
             */
            $q = "SELECT DISTINCT CONCAT(vendorName,' : ',brand) AS vendor_brand
                FROM {$OP}vendorItems i
                INNER JOIN {$OP}vendors v ON v.vendorID = i.vendorID
                WHERE i.upc REGEXP ?";
            $s = $dbc->prepare($q);
            $r = $dbc->execute($s,array($brandPrefix));
            while ($row = $dbc->fetch_array($r)) {
                $ret .= sprintf("%s%s",
                    ($ret == '') ? '' : '<br />or ',
                    $row['vendor_brand']);
            }
            if (strpos($ret,'<br') > 1) {
                $ret = '<br />' . $ret;
            }
        }
        return $ret;
        }

    public function helpContent()
    {
        $ret = '';
        $ret .= '<p>Menu of utilities to update the inventory count data that is used
            to prepare the Final Report.
            </p>';
        $ret .= '<p>This data is initially a snapshot of the data captured during the
            physical inventory count, but includes a snapshot of product data
            including cost and department which the count-day data does not.
            </p>';
        $ret .= '<p>For the "Complete Not in POS" report the Brand check
            for items not in the Products List
            is against the Vendor Catalogues, not the Products List'; 
        return $ret;
    }

// class
}

FannieDispatch::conditionalExec();

?>
