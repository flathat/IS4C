<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Copyright 2015 West End Food Co-op, Toronto, Canada

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

use COREPOS\pos\lib\Tenders\TenderModule;

class MarketBucksTender extends TenderModule 
{
    protected $amount_tendered;

    public function MarketBucksTender($code, $amt)
    {
        parent::__construct($code, $amt);
        $this->amount_tendered = $this->amount;
    }

    /**
      Allow the tender to be used without specifying an amount tendered
      @return boolean
    */
    public function allowDefault()
    {
        return false;
    }

    /**
      Error message shown if tender cannot be used without
      specifying an amount tendered.
      Changes from base:
      This doesn't change the behaviour of the base method,
       just makes the message clearer.
      @return html string
    */
    public function disabledPrompt()
    {
        /* Changes from base:
         * - Different message style.
         * - Standard clear button instead of 'OK'.
         */
        //$clearButton = array('OK [clear]' => 'parseWrapper(\'CL\');');
        return DisplayLib::boxMsg(
            _('Also enter the <i>amount</i> of ') . $this->name_string .
            ' ' . _('being tendered.'),
            '',
            false,
            DisplayLib::standardClearButton()
        );
    }

    /**
      Check for errors
      @return True or an error message string
    */
    public function errorCheck()
    {

    if (false) {
        return DisplayLib::xboxMsg(
            "MBT->errorCheck forced error. amt_tendered: " . $this->amount_tendered,
            DisplayLib::standardClearButton()
        );
    }
        // Highest Market Buck currency value.
        $largestBuck = 10.00;
        if (($this->amount_tendered > (CoreLocal::get("amtdue") + 0.005 + ($largestBuck - 0.01))) &&
            CoreLocal::get("amtdue") >= 0)
        { 
            return DisplayLib::boxMsg(
                _("The amount of ") . $this->name_string . 
                ' ' . _("tendered may not exceed the amount due") .
                ' ' . _("by $") . $largestBuck . ' ' . _("or more."),
                '',
                false,
                DisplayLib::standardClearButton()
            );
        }

        // Amount tendered must be an even-dollar amount.
        $intAmount = (int)($this->amount_tendered * 100);
        if (($intAmount % 100) != 0) { 
            return DisplayLib::boxMsg(
                _("The amount of ") . $this->name_string . 
                ' ' . _("tendered must be an even-dollar amount."),
                '',
                false,
                DisplayLib::standardClearButton()
            );
        }

        return true;
    }
    

}

?>
