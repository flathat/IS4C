<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op
    Copyright 2015 River Valley Market, Northampton, MA

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

/**
  @class TempLastChangeModel
*/
class TempLastChangeModel extends BasicModel
{

    protected $name = "TempLastChange";

    protected $columns = array(
        // FK to core_op.custdata
        'CardNo' => array('type'=>'INT', 'not_null'=>True, 'default'=>0, 'index'=>True),
        //
        'blueLine' => array('type'=>'VARCHAR(50)', 'default'=>"''", 'not_null'=>True),
        // Place to store custdata.LastChange while blueLine is being assigned.
        'LastChange' => array('type'=>'DATETIME', 'not_null'=>True)
	);

    public function name()
    {
        return $this->name;
    }

    /* START ACCESSOR FUNCTIONS */

    /* END ACCESSOR FUNCTIONS */

}

