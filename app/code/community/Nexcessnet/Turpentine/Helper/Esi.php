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
    const ESI_DATA_PARAM            = 'esiData';
    const ESI_TTL_PARAM             = 'ttl';
    const ESI_CACHE_TYPE_PARAM      = 'cacheType';
    const MAGE_CACHE_NAME           = 'turpentine_esi_blocks';

    /**
     * Get whether ESI includes are enabled or not
     *
     * @return bool
     */
    public function getEsiEnabled() {
        return Mage::app()->useCache( $this->getMageCacheName() );
    }

    /**
     * Get if ESI should be used for this request
     *
     * @return bool
     */
    public function shouldResponseUseEsi() {
        return Mage::helper( 'turpentine/varnish' )->shouldResponseUseVarnish() &&
            $this->getEsiEnabled();
    }

    /**
     * Check if ESI includes are enabled and throw an exception if not
     *
     * @return null
     */
    public function ensureEsiEnabled() {
        if( !$this->shouldResponseUseEsi() ) {
            Mage::throwException( 'ESI includes are not enabled' );
        }
    }

    /**
     * Get the name of the URL param that holds the ESI block hash
     *
     * @return string
     */
    public function getEsiDataParam() {
        return self::ESI_DATA_PARAM;
    }

    /**
     * Get the URL param name for the ESI block cache type
     *
     * @return string
     */
    public function getEsiCacheTypeParam() {
        return self::ESI_CACHE_TYPE_PARAM;
    }

    /**
     * Get the URL param name for the ESI block TTL
     *
     * @return string
     */
    public function getEsiTtlParam() {
        return self::ESI_TTL_PARAM;
    }

    /**
     * Get whether ESI debugging is enabled or not
     *
     * @return bool
     */
    public function getEsiDebugEnabled() {
        return (bool)Mage::getStoreConfig(
            'turpentine_varnish/general/esi_debug' );
    }

    /**
     * Get whether block name logging is enabled or not
     *
     * @return bool
     */
    public function getEsiBlockLogEnabled() {
        return (bool)Mage::getStoreConfig(
            'turpentine_varnish/general/block_debug' );
    }

    /**
     * Get the cache type Magento uses
     *
     * @return string
     */
    public function getMageCacheName() {
        return self::MAGE_CACHE_NAME;
    }
}
