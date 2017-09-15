<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

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

namespace COREPOS\pos\lib\Tenders;
use \CoreLocal;

/**
  @class GiftCertificateTender
  Tender module for gift certificates
*/
class GiftCertificateTender extends TenderModule 
{
    /* Do these after lanes are upgraded to 1.8
    protected $amount_tendered;

    public function MarketBucksTender($code, $amt)
    {
        parent::__construct($code, $amt);
        $this->amount_tendered = $this->amount;
    }
     */

    /**
      Allow the tender to be used without specifying an amount tendered
      @return boolean
    */
    public function allowDefault()
    {
        return false;
    }

    /** Maybe use disabledPrompt() from Market Bucks when lanes upgraded.
     */

    /**
      Check for errors
      @return True or an error message string
    */
    public function errorCheck(){
    if (false) {
        return DisplayLib::xboxMsg(
            "GiftCert->errorCheck forced error. amt_tendered: " . $this->amount .
            " against amount due of: " . CoreLocal::get("amtdue"),
            DisplayLib::standardClearButton()
        );
    }

        if (CoreLocal::get("store") == 'WEFC_Toronto') {
            // Amount tendered must be a multiple of $5.
            $intAmount = (int)($this->amount * 100);
            if (($intAmount % 500) != 0) { 
                return DisplayLib::boxMsg(
                    _("The value of the ") . $this->name_string . 
                    ' ' . _("tendered must be a multiple of \$5."),
                    '',
                    false,
                    DisplayLib::standardClearButton()
                );
            }
        }

        return true;
    }
    
    /**
      Set up state and redirect if needed
      @return True or a URL to redirect
    */
    public function preReqCheck()
    {
        if (CoreLocal::get("enableFranking") != 1) {
            return true;
        }

        if (CoreLocal::get("msgrepeat") == 0) {
            return $this->defaultPrompt();
        }

        return true;
    }

    public function defaultPrompt()
    {
        return parent::frankingPrompt();
    }

    /* 12Dec2015 Is this used for Gift Certificates?
     * Fixed a bug in it.
    */
    public function add()
    {
        // rewrite WIC as checks
        if (CoreLocal::get("store")=="wfc" && $this->tender_code=='WT'){
            $this->tender_code = "CK";
        }
        parent::add();
    }
}

