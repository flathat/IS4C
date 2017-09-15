<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op, Duluth, MN

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

class ContactPref_WEFC extends \COREPOS\Fannie\API\member\MemberModule {

    public function width()
    {
        return parent::META_WIDTH_HALF;
    }

    // Return a form segment to display or edit the Contact Preference.
    function showEditForm($memNum, $country="US"){

        global $FANNIE_URL;

        $dbc = $this->db();

        // Select the preference for this member and all of the options.
        $infoQ = $dbc->prepare("SELECT n.pref, p.pref_id, p.pref_description
                FROM memContact AS n,
                memContactPrefs AS p
                WHERE n.card_no=?
                ORDER BY p.pref_id");
        $infoR = $dbc->execute($infoQ,array($memNum));

        // If no preference exists get the options and force a default in pref.
        if ( $dbc->num_rows($infoR) == 0 ) {
            $infoQ = $dbc->prepare("SELECT IF(pref_id=2,2,-1) pref, pref_id, pref_description
                    FROM memContactPrefs
                    ORDER BY pref_id");
            $infoR = $dbc->execute($infoQ);
        }

        // Compose the display/edit block.
        $ret = "<div class=\"panel panel-default\">
            <div class=\"panel-heading\">Member Contact Preference and Email Opt-In";
        $ret .= " <a onclick=\"$('#_fhtt17102007').toggle();return false;\" href=''>" .
            "<img title=\"Opt-In is for managing the member's consent to receive commercial
                email from WEFC. " .
            "(Click for more)\" src='{$FANNIE_URL}src/img/buttons/help16.png'>" .
            "</a>";
        $ret .= "</div><!-- /.panel-heading -->";

        $ret .= "<div class=\"panel-body\">";

        $ret .= '<div class="form-group form-inline">
            <span class="label primaryBackground">Preference</span>';
        $ret .= '&nbsp;<select name="MemContactPref_WEFC" class="form-control">';
        while ($infoW = $dbc->fetchRow($infoR)) {
            $ret .= sprintf("<option value=%d %s>%s</option>",
                $infoW['pref_id'],
                (($infoW['pref']==$infoW['pref_id'])?'selected':''),
                $infoW['pref_description']);
        }
        $ret .= "</select></div>";

        // Email Opt-In
        // Select the Opt-In choice for this member.
        $query = "SELECT COALESCE(opt_in,-2) as opt_in FROM memEmailOption WHERE card_no = ?";
        $optQ = $dbc->prepare($query);
        $optR = $dbc->execute($optQ,array($memNum));
        $email_option = -2;
        //$dbc->logger("1 e_o: $email_option");
        if ($dbc->numRows($optR) > 0) {
            $infoOpt = $dbc->fetchRow($optR);
            $email_option = $infoOpt['opt_in'];
            //$dbc->logger("2 e_o: $email_option");
        }
        $ret .= '<div class="form-group form-inline">
            <span class="label primaryBackground">Opt-In</span>';
        $ret .= '&nbsp;<select name="EmailOptInChoice" class="form-control">';
            $ret .= sprintf("<option value='%s'%s>%s</option>",
                '',
                '',
                'Choose option');
        $emailOpts = array(
            'Opted In' => 1,
            'Opted Out' => 0,
            'Pending - see "?" Help' => 2,
            'Unknown' => -2
        );
        foreach ($emailOpts as $key => $value) {
            // Why does 0 == NULL but 0 !== NULL?
            if (is_numeric($value)) {
            $ret .= sprintf("<option value='%s'%s>%s</option>",
                $value,
                ($value == $email_option) ? ' selected' : '',
                $key);
            } else {
            $ret .= sprintf("<option value='%s'%s>%s</option>",
                $value,
                ($value === $email_option) ? ' selected' : '',
                $key);
            }
        }
        $ret .= "</select></div>";

        $ret .= "</div>";

        $ret .= '<fieldset id="_fhtt17102007" style="display:none; width:440px;">' .
            "<p style=\"margin-left: 1em;\">" .
            "Opt-In is for managing the member's consent to receive commercial
            email from WEFC.
            " .
            "The official record of the consent is in CiviCRM.
            " .
            "<ul>" .
            "<li>\"Opted In\" means the member has given consent.
            </li>
            " .
            "<li>\"Opted Out\" means the member does not consent
            or has withdrawn consent.
            </li>
            " .
            "<li>\"Unknown\" means we don't know the member's choice.
            It is the default initial status but you can change to
            \"Unknown\" if, for example, the the initial status of
            \"Opted In\" turns out to be incorrect and you don't know
            the member's actual choice.
            </li>
            " .
            "<li>The status \"Pending\" means the member completed only the first
            of two steps in giving consent. The second step requires an email
            from the member from the address to which the request for consent
            was sent. 
            For that reason it cannot be changed here.
            " .
            "<br />An initial status of \"Pending\" may not be entered here.
            </li>
            " .
            "</ul>" .
            "</p>" .
            "" .
        "</fieldset>";

        $ret .= "</div>";

        return $ret;

    // showEditForm
    }

    /* Update or insert the Contact Preference and Email Opt-In Choice.
     * Return "" on success or an error message.
     * 2.7? wants $json, maybe also prefer public as in the parent.
     * was: function saveFormData($memNum){ \\}
     */
    function saveFormData($memNum, $json=array())
    {
        $dbc = $this->db();

        $formPref = FormLib::get_form_value('MemContactPref_WEFC',-1);

        // Does a preference for this member exist?
        $infoQ = $dbc->prepare("SELECT pref
                FROM memContact
                WHERE card_no=?");
        $infoR = $dbc->execute($infoQ,array($memNum));

        // If no preference exists, add one if one was chosen.
        if ( $dbc->numRows($infoR) == 0 ) {
            if ( $formPref > -1 ) {
                $upQ = $dbc->prepare("INSERT INTO memContact (card_no, pref)
                    VALUES (?, ?)");
                $upR = $dbc->execute($upQ,array($memNum, $formPref));
                if ( $upR === False )
                    return "Error: problem adding Contact Preference.";
                else
                    return "";
            }
        }
        // If one exists, update it unless there was no change.
        else {
            $row = $dbc->fetchRow($infoR);
            $dbPref = $row['pref'];
            if ( $formPref != $dbPref ) {
                $upQ = $dbc->prepare("UPDATE memContact SET pref = ?
                    WHERE card_no = ?");
                $upR = $dbc->execute($upQ,array($formPref, $memNum));
                if ( $upR === False )
                    return "Error: problem updating Contact Preference.";
                else
                    return "";
            }
        }

        // Email Opt-In
        $formEmailOption = FormLib::get_form_value('EmailOptInChoice',-1);

        // Does a choice for this member exist?

        // Select the Opt-In choice for this member.
        $query = "SELECT COALESCE(opt_in,-2) as opt_in FROM memEmailOption WHERE card_no = ?";
        $optQ = $dbc->prepare($query);
        $optR = $dbc->execute($optQ,array($memNum));

        /* If no choice exists, add one if one was chosen.
         * May not add a Pending.
         */
        if ( $dbc->numRows($optR) == 0 ) {
            if (
                $formEmailOption != -1 &&
                $formEmailOption != -2 &&
                $formEmailOption != 2
            ) {
                $upQ = $dbc->prepare("INSERT INTO memEmailOption (card_no, opt_in)
                    VALUES (?, ?)");
                $upR = $dbc->execute($upQ,array($memNum, $formEmailOption));
                if ( $upR === False ) {
                    return "Error: problem adding Email Opt-In Choice.";
                } else {
                    return "";
                }
            }
        }
        /* If one exists, update it unless there was no change.
         * May not change existing Pending here, or change to Pending.
         */
        else {
            $row = $dbc->fetchRow($optR);
            $dbEmailOption = $row['opt_in'];
            if (
                $formEmailOption != -1 &&
                $formEmailOption != $dbEmailOption &&
                $formEmailOption != 2 &&
                $dbEmailOption != 2
            ) {
                // Also .modified and .modified_by
                if ($formEmailOption == -2) {
                    $query = "UPDATE memEmailOption SET opt_in = NULL
                        WHERE card_no = ?";
                    $upQ = $dbc->prepare($query);
                    $upR = $dbc->execute($upQ,array($memNum));
                } else {
                    $query = "UPDATE memEmailOption SET opt_in = ?
                        WHERE card_no = ?";
                    $upQ = $dbc->prepare($query);
                    $upR = $dbc->execute($upQ,array($formEmailOption,$memNum));
                }
                if ( $upR === False ) {
                    return "Error: problem updating Email Opt-In Choice.";
                } else {
                    return "";
                }
            }
        }

        return "";

    // saveFormData
    }

// ContactPref_WEFC
}

?>
