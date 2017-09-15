<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op.

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

use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\Search\Products\ProductSearch;

/**
  @class ItemSearchAll_WEFC_Toronto
  Use the productUser table to supplement searches.
  Does not filter out normal UPC's,
  i.e. those that start with a manufacturer part.
  Return description formatted: BRAND | item | sizeUnitOfMeasure
*/
class ItemSearchAll_WEFC_Toronto extends ProductSearch {

    /* True if this should be the only search module used. */
    public $this_mod_only = 0;

    public function search($str)
    {
        global $CORE_LOCAL;
        $ret = array();
        $sql = Database::pDataConnect();
        if (!$sql->table_exists('productUser')) {
            return $ret;
        }
        $noBrandDepts = "-1";
        //if (CoreLocal::get('store') == 'WEFC_Toronto') {}
        if ($CORE_LOCAL->get('store') == 'WEFC_Toronto') {
            $noBrandDepts = "3000,4900";
        }
        /*
         * description formatted: BRAND | item | sizeUnitOfMeasure
         * - prefer productUser.description for item
         * - ?prefer productUser.brand for brand
         * - ?prefer productUser.sizing, without space separator,
         *     for sizeUnitOfMeasure
        */
        $query = "SELECT p.upc,
            CONCAT(
            CASE WHEN (p.department in ({$noBrandDepts}) AND SUBSTR(p.upc,1,5) = '00000')
                THEN ''
            WHEN COALESCE(u.brand, '') != ''
                THEN CONCAT(UPPER(SUBSTR(u.brand,1,14)), ' | ')
            WHEN COALESCE(p.brand, '') != ''
                THEN CONCAT(UPPER(SUBSTR(p.brand,1,14)), ' | ')
            ELSE '' END,
            CASE WHEN COALESCE(u.description, '') != ''
                THEN SUBSTR(u.description, 1, 40)
                ELSE p.description END,
            CASE WHEN COALESCE(p.size,p.unitofmeasure) != ''
            THEN CONCAT(' | ',p.size,p.unitofmeasure) ELSE '' END
            )
                AS description,
                p.normal_price, p.special_price, p.scale,
            CASE WHEN p.department in ({$noBrandDepts})
                THEN 'A ' ELSE 'B ' END
                AS PTP
               FROM products AS p
                LEFT JOIN productUser AS u ON p.upc=u.upc
                WHERE (
                    u.description LIKE ? OR
                    p.description LIKE ? OR
                    p.formatted_name LIKE ? OR
                    p.brand LIKE ? OR
                    u.brand LIKE ?
                 )
            AND p.inUse='1'
            ORDER BY PTP, description";
        $args = array(
            '%' . $str . '%',
            '%' . $str . '%',
            '%' . $str . '%',
            '%' . $str . '%',
            '%' . $str . '%',
        );
        $prep = $sql->prepare($query);
        $result = $sql->execute($prep, $args);
        while($row = $sql->fetch_row($result)){
            $ret[$row['upc']] = $row;
        }
        $this->this_mod_only = (!empty($ret) ? 1 : 0);
        return $ret;
    }
}

