<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op
    Copyright 2015 West End Food Co-op, Toronto, Canada.

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

/**
  @class ProductsWEFCTorontoModel
*/
class ProductsWEFCTorontoModel extends BasicModel 
{

    protected $name = "products_WEFC_Toronto";
    protected $preferred_db = 'op';

    protected $columns = array(
    'upc' => array('type'=>'VARCHAR(13)', 'primary_key'=>True),
    'order_code' => array('type'=>'INT'),
    'description' => array('type'=>'VARCHAR(255)'),
    'search_description' => array('type'=>'VARCHAR(255)'),
    );

    public function doc()
    {
        return '
Table: products_WEFC_Toronto

Columns:
    upc varchar
    order_code int
    description varchar
    search_description varchar

Depends on:
    products (table)

Use:
Various uses specific to store WEFC_Toronto.
        ';
    }

    /* START ACCESSOR FUNCTIONS */

    public function upc()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["upc"])) {
                return $this->instance["upc"];
            } else if (isset($this->columns["upc"]["default"])) {
                return $this->columns["upc"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'upc',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["upc"]) || $this->instance["upc"] != func_get_args(0)) {
                if (!isset($this->columns["upc"]["ignore_updates"]) || $this->columns["upc"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["upc"] = func_get_arg(0);
        }
        return $this;
    }

    public function order_code()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["order_code"])) {
                return $this->instance["order_code"];
            } else if (isset($this->columns["order_code"]["default"])) {
                return $this->columns["order_code"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'order_code',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["order_code"]) || $this->instance["order_code"] != func_get_args(0)) {
                if (!isset($this->columns["order_code"]["ignore_updates"]) || $this->columns["order_code"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["order_code"] = func_get_arg(0);
        }
        return $this;
    }

    public function description()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["description"])) {
                return $this->instance["description"];
            } else if (isset($this->columns["description"]["default"])) {
                return $this->columns["description"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'description',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["description"]) || $this->instance["description"] != func_get_args(0)) {
                if (!isset($this->columns["description"]["ignore_updates"]) || $this->columns["description"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["description"] = func_get_arg(0);
        }
        return $this;
    }

    public function search_description()
    {
        if(func_num_args() == 0) {
            if(isset($this->instance["search_description"])) {
                return $this->instance["search_description"];
            } else if (isset($this->columns["search_description"]["default"])) {
                return $this->columns["search_description"]["default"];
            } else {
                return null;
            }
        } else if (func_num_args() > 1) {
            $value = func_get_arg(0);
            $op = $this->validateOp(func_get_arg(1));
            if ($op === false) {
                throw new Exception('Invalid operator: ' . func_get_arg(1));
            }
            $filter = array(
                'left' => 'search_description',
                'right' => $value,
                'op' => $op,
                'rightIsLiteral' => false,
            );
            if (func_num_args() > 2 && func_get_arg(2) === true) {
                $filter['rightIsLiteral'] = true;
            }
            $this->filters[] = $filter;
        } else {
            if (!isset($this->instance["search_description"]) || $this->instance["search_description"] != func_get_args(0)) {
                if (!isset($this->columns["search_description"]["ignore_updates"]) || $this->columns["search_description"]["ignore_updates"] == false) {
                    $this->record_changed = true;
                }
            }
            $this->instance["search_description"] = func_get_arg(0);
        }
        return $this;
    }
    /* END ACCESSOR FUNCTIONS */
}

