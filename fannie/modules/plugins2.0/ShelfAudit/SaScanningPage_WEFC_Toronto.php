<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Based on example code from Wedge Community Co-op

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

/* 28Mar2017 EL Changed to SaScanningPage_WEFC_Toronto.php
 * This, SaScanningPage.php was used in the 31Mar2016 Inventory Count
 * It has several fixes and enhancements from the original which is probably
 *  like ~_1.8
 * I think "old" is relative to SaHandheldPage.php which I think is for a phone
 *  with a linea scanner.
 * I should maybe call this SaScanningPage_WEFC_Toronto
*/
/* HELP HOWTO
 * The page consists of two forms:
 * 1. mForm Has three controls:
 *    1. minput - a UPC, usually entered with a hand-held scanner.
 *       Checks for length of field either 11 or 13
 *        following which the cursor moves to the 2nd input
 *    2. qinput - The quantity: count or weight of the item in minput
 *       Entry is terminated by 'z'
 *       Valid entries:
 *       - integer
 *       - decimal number, for weight
 *       - number'C'number, where the number after 'C' is a Section to change to.
 *         The current scan goes to the new Section.
 *       - number'S'number, where the number after 'S' is the Section to use
 *         instead of the current section.
 *         I.e. a one-off section override.
 *    3. A Submit labelled "enter"
 *       ?Uses the default quantity of 1.
 * 2. sForm Has one control:
 *    1. A Submit labelled "New Section"
 *       Increments the section counter, i.e. uses MAX(section) + 1.
 *       Does NOT simply go to the current section + 1.
 *       Changes current section to the new value.
*/

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

/**
  @class SaScanningPage_WEFC_Toronto
*/
class SaScanningPage_WEFC_Toronto extends FanniePage {

    protected $window_dressing = False;

    public $page_set = 'Plugin :: Shelf Audit';
    public $description = '[Alt. Scanning] is an older interface for entering quantities
    on hand';
    public $themed = true;
    protected $header = '';
    protected $title = 'ShelfAudit Old Scanning Page';

    private $status='';
    private $section=0;

    function preprocess(){
        global $FANNIE_PLUGIN_SETTINGS, $FANNIE_OP_DB;
        $this->status = 'waiting - no input';

        /**
          Store session in browser section.
        */
        if (ini_get('session.auto_start')==0 && !headers_sent() && php_sapi_name() != 'cli' && session_id() == '') {
            @session_start();
        }
        if (!isset($_SESSION['SaPluginSection']))
            $_SESSION['SaPluginSection'] = 0;
        $this->section = $_SESSION['SaPluginSection'];

        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['ShelfAuditDB']);
        if (!is_object($dbc) || $dbc->connections[$FANNIE_PLUGIN_SETTINGS['ShelfAuditDB']] === False){
            $this->status = 'bad - cannot connect';
            return True;
        }

        if (FormLib::get_form_value('sflag') == 1){
            $query = $dbc->prepare_statement('SELECT MAX(section) AS new_section FROM sa_inventory');
            $result = $dbc->exec_statement($query);
            $section = 0;
            if ($dbc->num_rows($result) > 0)
                $section = array_pop($dbc->fetch_row($result));

            $this->section = $section + 1;
            $_SESSION['SaPluginSection'] = $section + 1;
            $this->status = 'good - section changed';
        } 
        else if (FormLib::get_form_value('minput') !== ''){
            $upc = FormLib::get_form_value('minput');
            if (FormLib::get_form_value('isbnflag')=='1'){
                $upc=BarcodeLib::padUPC(substr($_GET['minput'],0,12));
            } else {
                $upc=BarcodeLib::padUPC(substr($_GET['minput'],0,11));
            }
                
            /* Short tag rules */
            if (false && strcmp('0000000',substr($upc,0,7))==0) {
                switch ($upc[12]) {
                case '0':
                    $upc='00'.substr($upc,6,3).'00000'.substr($upc,10,3);
                    break;
                case '1':
                    $upc='00'.substr($upc,6,3).'10000'.substr($upc,10,3);
                    break;
                case '2':
                    $upc='00'.substr($upc,6,3).'20000'.substr($upc,10,3);
                    break;
                case '3':
                    $upc='00'.substr($upc,6,4).'00000'.substr($upc,10,2);
                    break;
                case '4':
                    $upc='00'.substr($upc,6,5).'00000'.substr($upc,11,1);
                    break;
                default:
                    $upc='00'.substr($upc,6,6).'0000'.substr($upc,12,1);
                    break;
                }
            }

            /*
             * Strip the z from qinput. Quick hack version
             */
            $qty = FormLib::get_form_value('qinput');
            $qty = rtrim($qty,'z');
            $args = array($upc);
            $query = 'INSERT INTO sa_inventory 
                    (id,datetime,upc,clear,quantity,section)
                    VALUES (NULL,'.$dbc->now().',?,0,?,?)';
            $stmt = $dbc->prepare_statement($query);
            /*
            $stmt = $dbc->prepare_statement('INSERT INTO sa_inventory 
                    (id,datetime,upc,clear,quantity,section)
                    VALUES (NULL,'.$dbc->now().',?,0,?,?)');
             */
                    
            $sectionChange = '';
            //if ($qty != '' && ctype_digit($qty)){}
            $qty = strtoupper($qty);
            if ($qty != '' && is_numeric($qty)){
                $args[] = $qty;
                $quantity = $qty;
                $args[] = $this->section;
            } else if ($qty != '' && strpos($qty,'C') > 0) {
                $split=strpos($qty,'C');
                $quantity=substr($qty,0,$split);
                $section=substr($qty,$split+1);
                $args[] = $quantity;
                $args[] = $section;
                $_SESSION['SaPluginSection'] = $section;
                $this->section = $section;
                $sectionChange = "<br />to Section $section and Section changed to $section";
                //$sectionChange = " and section changed to $section";
            } else if ($qty != '') {
                $split=strpos($qty,'S');
                $quantity=substr($qty,0,$split);
                $section=substr($qty,$split+1);
                $args[] = $quantity;
                $args[] = $section;
                $sectionChange = "<br />and put in Section $section";
            } else {
                $quantity = 1;
                $args[] = $quantity;
                $args[] = $this->section;
            }
            $result = $dbc->exec_statement($stmt, $args);

            $qtyMsg = " x $quantity";

            $OP = $FANNIE_OP_DB;
            $prodQ = "SELECT upc, brand, description, size, unitofmeasure FROM {$OP}.products WHERE upc = ?";
            $prodP = $dbc->prepare($prodQ);
            $argsP = array($upc);
            $prodR = $dbc->execute($prodP,$argsP);
            $pMsg = '';
            $dMsg = '';
            if ($dbc->num_rows($prodR) == 0) {
                $pMsg = " NOT in POS!";
            } else {
                $row = $dbc->fetch_row($prodR);
                $dMsg = sprintf("<br />%s %s %s%s",
                    $row['brand'],
                    $row['description'],
                    $row['size'],
                    $row['unitofmeasure']
                );
            }

            
            if ($result) {
                $this->status = 'good - scan entered: '.$upc.''.
                    $qtyMsg .
                    $pMsg .
                    $dMsg .
                    $sectionChange;
            }   else {
                $argsMsg = print_r($args,True);
                $this->status = 'bad - strange scan:'.$query .
                    '<br />' . $argsMsg;
            }
        }

        return True;
    }


    function body_content(){
        ob_start();
        ?>
<html>
    <body onload="readinput();">
        <center>
        <form name="mForm" id="mid" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
                <table border=0>
                  <tr style="vertical-align:top">
                    <td>
                <input name="minput" id ="minput" type="text" value=""/>
            <div>scan or type upc</div>
                    </td>
                    <td>
                <input name="qinput" type="text" value="1"/>
                <input type="submit" value="enter"/>
                <br />Count or weight. Terminate with 'z'.
                <br />-<i>number</i> to reduce.
                <br />qty<b>C</b><i>number</i> - Change to Section <i>number</i>
                <br />qty<b>S</b><i>number</i> - This item to Section <i>number</i>
                    </td>
                  </tr>
                </table>
                <input name="isbnflag" type="hidden" value=""/>
            </form>
            <form name="sForm" id="mid" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
                <input name="sflag" type="hidden" value="1"/>
                <i>on Section (<?php echo $this->section; ?>)</i>
                <br />
                <input type="submit" value="new section"/>
            </form>
            <!-- div>scan or type upc</div -->
            <div>status: <?php echo($this->status); ?></div>
            <!-- other group not in example. ignoring for now. (Andy 29Mar2013)
            <div><strong>Using Group 1</strong></div>
            <div style="font-size: x-small; padding-top: .5em;"><a href="../hbc2">Switch to Group 2</a></div>
            -->
        </center>
        <script type="text/javascript"
        src="/IS4C/fannie/src/javascript/jquery.js">
        </script>
        <script type="text/javascript">
        $(document).ready(function() {
              $("#minput").keydown(function(event){
                      if(event.keyCode == 13) {
                                event.preventDefault();
                                      return false;
                                    }
                });
    });
        </script>
    </body>
</html>
        <?php
        return ob_get_clean();
    }

    function javascript_content(){
        ob_start();
        ?>
function waitforz() {
    if (document.forms[0]) {
        var qinputvalue = document.forms[0].qinput.value;

        if (qinputvalue.charAt(qinputvalue.length - 1) == 'z') {
            document.forms[0].submit();
        }   else {
            t=setTimeout("waitforz()",1000);
        }
    }
}
        
function readinput() {
    if (document.forms[0]) {
        var inputvalue = document.forms[0].minput.value;
                
        if (inputvalue.length == 11) {
            document.forms[0].qinput.value="";
            document.forms[0].qinput.focus();
            waitforz();
        } else if (inputvalue.length == 13) {
            document.forms[0].isbnflag.value="1";
            document.forms[0].qinputk.value="";
            document.forms[0].qinput.focus();
            waitforz();
        } else {
            document.forms[0].minput.focus();
            t=setTimeout("readinput()",1000);
        }
    } else {
    }
}
        <?php
        return ob_get_clean();
    }
}

FannieDispatch::conditionalExec(false);

