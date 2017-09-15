<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
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
/* This only checks the current item for exceeding Balance.
 * TotalActions/ArOverpayAction.php checks the total of payments in the
 *  transaction against Balance.
 */

use COREPOS\pos\lib\Scanning\SpecialDept;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\MemberLib;

class WefcStoreChargeDepartmentHandler extends SpecialDept 
{

    public $help_summary = 'Require cashier confirmation on Store Charge (A/R) Payment.
        Check that the member has a Store Charge Account
        and that the Payment doesn\'t exceed the Balance.';

    public function handle($deptID,$amount,$json)
    {
    
        if (CoreLocal::get('msgrepeat') == 0) {
            $charge_ok = MemberLib::chargeOk();
            if ($charge_ok == 0) {
                /* I'd prefer xboxMsg for errors. How to do that? */
                CoreLocal::set("boxMsg",
                    _("Member #") . ' ' . CoreLocal::get("memberID") . '<br />' .
                    _("does not have a Store Charge account") .
                    '<br />' . _("to pay off") . '.'
                );
                CoreLocal::set('boxMsgButtons', array(
                    'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
                ));
                $json['main_frame'] = MiscLib::base_url().'gui-modules/boxMsg2.php?quiet=1';
                return $json;
            }
            if ($amount > CoreLocal::get("balance")) {
                CoreLocal::set("boxMsg",
                    sprintf('The Payment of $%.2f<br />is more than the Balance of $%.2f',
                    $amount, CoreLocal::get("balance"))
                );
                CoreLocal::set('boxMsgButtons', array(
                    'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
                ));
                $json['main_frame'] = MiscLib::base_url().'gui-modules/boxMsg2.php?quiet=1';
                return $json;
            }

        /* Confirmation and receipt reminder.
         * The payment doesn't seem to actually happen if this is omitted.
         * */
            CoreLocal::set("boxMsg","<b>Store Charge Payment</b>" .
                "<br />Remember to keep your receipt."
            );
            CoreLocal::set('boxMsgButtons', array(
                'Confirm [enter]' => '$(\'#reginput\').val(\'\');submitWrapper();',
                'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
            ));
            $json['main_frame'] = MiscLib::base_url().'gui-modules/boxMsg2.php?quiet=1';
        }
        return $json;
    }

}

