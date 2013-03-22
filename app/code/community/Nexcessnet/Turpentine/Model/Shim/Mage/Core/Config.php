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

class Nexcessnet_Turpentine_Model_Shim_Mage_Core_Config extends Mage_Core_Model_Config {

    /**
     * Apply a block/helper/model rewrite to the config's rewrite cache. Returns
     * the previous value from the cache
     *
     * @param  string $groupType    rewrite type (helper|model|block)
     * @param  string $group        module part of class spec, "example" in "example/model"
     * @param  string $class        classname part of class spec, "model" in "example/model"
     * @param  string $className    full class name to rewrite to
     * @return string
     */
    public function shim_setClassNameCache( $groupType, $group, $class, $className ) {
        $config = Mage::getConfig();
        $prevValue = @$config->_classNameCache[$groupType][$group][$class];
        $config->_classNameCache[$groupType][$group][$class] = $className;
        return $prevValue;
    }
}
