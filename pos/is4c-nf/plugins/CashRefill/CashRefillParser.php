<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of IT CORE.

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

class CashRefillParser extends Parser {

    function check($str){
        if (substr($str,0,12) == 'REFILLREFILL'){
            return True;
        }
        else if (substr($str,0,6) == 'REFILL' && is_numeric(substr($str,6))){
            return True;
        }
        else if (substr($str,-6) == 'REFILL' && is_numeric(substr($str,0,strlen($str)-6))){
            return True;
        }
        else{
            return False;
        }
    }

    function parse($str)
    {
        $ret = $this->default_json();
        if (substr($str,0,12) == 'REFILLREFILL'){
            // repeat cashier's input, if any
            if (strlen($str) > 12) {
                $ret['retry'] = substr($str, 12);
            }
            // redraw right side of the screen
            $ret['scale'] = true; 

            return $ret;
        }
        else {
            // add drop record to transaction
            $amt = 0;
            if (substr($str,0,6) == 'REFILL')
                $amt = substr($str,6);
            else
                $amt = substr($str,0,strlen($str)-6);
            TransRecord::addRecord(array(
                'upc' =>'CASHREFILL', 
                'description' => 'CASHREFILL', 
                'trans_type' => "L", 
                'trans_subtype' => 'CA',
                'total' => ($amt/100.00),
            ));
            $ret['main_frame'] = MiscLib::base_url()."gui-modules/pos2.php";
            return $ret;
        }
    }
}
