<?php
/**
  Change dtrans.department for products.department where it has been
  changed in the recent re-organization indicated by .x = 1.

  Run from command line.

*/
include(dirname(__FILE__) . '/../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT . 'classlib2.0/FannieAPI.php');
}

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    ini_set('display_errors', 'on');

    $dbc = FannieDB::get("$FANNIE_ARCHIVE_DB");
    $OP = $FANNIE_OP_DB . $dbc->sep();
    $TRANS = $FANNIE_TRANS_DB . $dbc->sep();

    // The one that has the new .department values.
    $products_table = 'products_20160130';
    $prodQ = "SELECT upc, department
        FROM {$OP}{$products_table}
        WHERE price_rule_id =1
        ORDER BY upc";
    $prodS = $dbc->prepare($prodQ);
    $prodR = $dbc->execute($prodS,array());
    if ($prodR === false) {
        echo "Failed: $prodQ\n";
        exit;
    }
    $items = array();
    while($row = $dbc->fetch_array($prodR)) {
        // This is $args for the UPDATE
        $item = array($row['department'], $row['upc']);
        //$item = array(upc => $row['upc'], department => $row['department']);
        $items[] = $item;
    }
    $i = 0;
    foreach($items as $item) {
        $i++;
        echo $i . " upc: {$item[1]}  department: {$item[0]}\n";
        //echo $i . " upc: {$item['upc']}  department: {$item['department']}\n";
    }
    echo "There are $i items to change.\n";
    //exit;

    /**
      Find monthly tables
    */
    $tablesR = $dbc->query('SHOW TABLES');
    $monthly_tables = array();
    while ($w = $dbc->fetchRow($tablesR)) {
        if (preg_match('/transArchive20[0-2][0-9][0-1][0-9]/', $w[0])) {
            $monthly_tables[] = $w[0];
            //echo "$w[0]\n";
        }
    }
    if (count($monthly_tables) == 0) {
        echo "No monthly tables found!\n";
        exit;
    } else {
        sort($monthly_tables);
    }

    /**
      Change the departments of the selected upcs.
     */
    foreach ($monthly_tables as $table) {
        $valid = preg_match('/transArchive([0-9]{4})([0-9]{2})/', $table, $matches);
        if (!$valid) {
            echo "Cannot detect month and year for $table\n";
            echo "Nothing in that table has been changed.\n";
            continue;
        }
        if ($matches[1] == "2012") {
            echo "Skipping $table\n";
            continue;
        }
        if ($matches[1] == "2013" && $matches[2] < "04") {
            echo "Skipping $table\n";
            continue;
        }
        if ($matches[1] < "2016") {
            echo "Skipping $table\n";
            continue;
        }

        $is_core_trans = 1;
        if ($is_core_trans) {
            //$table = "{$TRANS}dlog_15";
            $table = "{$TRANS}transarchive";
            echo "Doing: $table\n";
        } else {
            echo "Doing: year {$matches[1]} month: {$matches[2]}\n";
        }
        $changeQ = "UPDATE $table SET department = ? WHERE upc = ?";
        $changeS = $dbc->prepare($changeQ);
        $i = 0;
        /*
         */
        foreach($items AS $item) {
            $changeR = $dbc->execute($changeS,$item);
            if ($changeR === false) {
                $params = print_r($item,true);
                echo "Failed: $changeQ\n$params\n";
                exit;
            }
            $i++;
            if (true && $i == 1) {
                $params = print_r($item,true);
                echo "Did: $changeQ\n$params\n";
            }
            if (true && $i == 2) {
                break;
            }
        }
        echo "\tDid $i items.\n";
        if ($is_core_trans) {
            echo "Bail for $table\n";
            break;
        }
    }

    echo "===============================================================\n";
    echo "DONE\n";
    echo "===============================================================\n";

}


