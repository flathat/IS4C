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

class WefcCreditCardTender extends Plugin {

    public $plugin_settings = array(
    );

    public $plugin_description = 'Inherits the standard Credit Card tender
        and extends it to prevent payment of Store Charge by credit card.';
}

