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

class Nexcess_Turpentine_Model_Sentinel extends Mage_Core_Model_Abstract {
    /**
     * Use cache flag
     *
     * @var boolean
     */
    protected $_cacheFlag       = true;

    /**
     * Use ESI flag
     *
     * @var boolean
     */
    protected $_esiFlag         = false;

    /**
     * Set the flag to cache or not, flag defaults to on
     *
     * @param bool $value=null turn flag on or off, null flips value
     */
    public function setCacheFlag( $value=null ) {
        return $this->_cacheFlag = (
            is_null( $value ) ? !$this->_cacheFlag : (bool)$value );
    }

    /**
     * Get the current value of the cache flag
     *
     * @return bool
     */
    public function getCacheFlag() {
        return $this->_cacheFlag;
    }

    /**
     * Set the flag to use ESI or not, flag defaults to off
     *
     * @param bool $value=null turn flag on or off, null flips value
     */
    public function setEsiFlag( $value=null ) {
        return $this->_esiFlag = (
            is_null( $value ) ? !$this->_esiFlag : (bool)$value );
    }

    /**
     * Get the current value of the esi flag
     *
     * @return bool
     */
    public function getEsiFlag() {
        return $this->_esiFlag;
    }
}
