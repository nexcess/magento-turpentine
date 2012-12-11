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

class Nexcessnet_Turpentine_Helper_Ajax extends Mage_Core_Helper_Abstract {
    const AJAX_DATA_PARAM           = 'ajaxData';

    /**
     * Get whether AJAX includes are enabled or not
     *
     * @return bool
     */
    public function getAjaxEnabled() {
        return true;
    }

    /**
     * Get if AJAX should be used for this request
     *
     * @return bool
     */
    public function shouldResponseUseAjax() {
        return $this->getAjaxEnabled() &&
            Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi();
    }

    /**
     * Get the name of the URL param that holds the ESI block hash
     *
     * @return string
     */
    public function getAjaxDataParam() {
        return self::AJAX_DATA_PARAM;
    }

    /**
     * Get whether ESI debugging is enabled or not
     *
     * @return bool
     */
    public function getAjaxDebugEnabled() {
        return (bool)Mage::getStoreConfig(
            'turpentine_varnish/general/ajax_debug' );
    }

    /**
     * Get the CORS origin field from the unsecure base URL
     *
     * @return string
     */
    public function getCorsOrigin() {
        $baseUrl = Mage::getBaseUrl();
        $path = parse_url( $baseUrl, PHP_URL_PATH );
        $domain = parse_url( $baseUrl, PHP_URL_HOST );
        // there has to be a better way to just strip the path off
        return substr( $baseUrl, 0,
            strpos( $baseUrl, $path,
                strpos( $baseUrl, $domain ) ) );
    }
}
