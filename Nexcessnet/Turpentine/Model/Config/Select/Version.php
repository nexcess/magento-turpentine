<?php

/** 
 * Nexcess.net Turpentine Extension for Magento
 * Copyright (C) 2012  Nexcess.net L.L.C.
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */ 

class Nexcessnet_Turpentine_Model_Config_Select_Version {
    public function toOptionArray() {
        $helper = Mage::helper('turpentine');
        return array(
            array( 'value' => '2.1', 'label' => $helper->__( '2.1.x' ) ),
            array( 'value' => '3.0', 'label' => $helper->__( '3.0.x' ) ),
            array( 'value' => 'auto', 'label' => $helper->__( 'Auto' ) ),
        );
    }
}