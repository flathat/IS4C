<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op
    Copyright 2017 West End Food Co-op, Toronto

    This file is part of Fannie.

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
  @class MemEmailOptioModel
*/
class MemEmailOptionModel extends BasicModel
{

    protected $name = "memEmailOption";

    protected $columns = array(
        // FK to custdata.CardNo
        'card_no' => array('type'=>'INT', 'not_null'=>True, 'default'=>0, 'index'=>True),
        'opt_in' => array('type'=>'INT', 'not_null'=>False, 'default'=>null)
    );

    public function name()
    {
        return $this->name;
    }
}

