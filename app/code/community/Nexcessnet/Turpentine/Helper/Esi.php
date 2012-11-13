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

class Nexcessnet_Turpentine_Helper_Esi extends Mage_Core_Helper_Abstract {
    const ESI_DATA_ID_PARAM         = 'esiId';

    public function getEsiEnabled() {
        return Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() &&
            Mage::getStoreConfig( 'turpentine_varnish/general/enable_esi' );
    }

    public function ensureEsiEnabled() {
        if( !$this->getEsiEnabled() ) {
            Mage::throwException( 'ESI includes are not enabled' );
        }
    }

    public function getEsiDataIdParam() {
        return self::ESI_DATA_ID_PARAM;
    }

    public function getEsiDebugEnabled() {
        return (bool)Mage::getStoreConfig(
            'turpentine_varnish/general/esi_debug' );
    }
}
