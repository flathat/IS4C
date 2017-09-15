<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op
    Copyright 2016 West End Food Co-op, Toronto, Canada

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

use COREPOS\pos\lib\Tenders\CreditCardTender;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\DisplayLib;

/**
  @class CreditCardTenderWEFC_Toronto
  Tender module for credit cards
  19Aug2016 Extends standard credit card handling to:
  - Prevent credit card being used to pay store charge accounts.
*/
class CreditCardTenderWEFC_Toronto extends CreditCardTender 
{

    /**
      Check for errors
      @return True or an error message string
    */
    public function errorCheck()
    {
        $eC = parent::errorCheck();
        if ($eC !== True) {
            return $eC;
        }
        /* I like this wording better, but use parent for inheritance.
        if (($this->amount > (CoreLocal::get("amtdue") + 0.005)) && CoreLocal::get("amtdue") >= 0) { 
            return DisplayLib::xboxMsg(
                _("The tender may not exceed<br />the Amount Due."),
                DisplayLib::standardClearButton()
            );
        }
         */

        if ($this->tender_code == 'CC') {
            $args = CoreLocal::get("ArDepartments");
            if (count($args) > 0) {
                $plcs = array();
                foreach ($args as $dept) {
                    $plcs[] = '?';
                }
                $placeholders = implode(',',$plcs);
                $db = Database::pDataConnect();
                $TRANS = 'translog';
                $query = "SELECT t.department " .
                    "FROM {$TRANS}.localtemptrans t " .
                    "WHERE t.department IN ({$placeholders}) " .
                    "AND t.trans_status NOT IN ('X','V','R') " .
                    "";
                $statement = $db->prepare($query);
                $results = $db->execute($statement, $args);
                if ($db->numRows($results) > 0 ) {
                    $clearButton = array('[clear]' => 'parseWrapper(\'CL\');');
                    $msg = _('Store Charge accounts may not be paid by Credit Card.');
                    return DisplayLib::xboxMsg(
                    $msg,
                    $clearButton);
                }
            }
        }

        return true;
    }
    
    /**
      Set up state and redirect if needed
      @return True or a URL to redirect
    public function preReqCheck()
    {
        if ($this->tender_code == 'CC' && CoreLocal::get('store') == 'wfc')
            CoreLocal::set('kickOverride',true);

        return true;
    }
    */

    /*
    public function allowDefault()
    {
        if ($this->tender_code == 'CC' && CoreLocal::get('store') == 'wfc') {
            return True;
        } else {
            return False;
        }
    }
     */
}

