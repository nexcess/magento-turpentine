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
    const ESI_DATA_PARAM            = 'data';
    const ESI_TTL_PARAM             = 'ttl';
    const ESI_CACHE_TYPE_PARAM      = 'access';
    const ESI_SCOPE_PARAM           = 'scope';
    const ESI_METHOD_PARAM          = 'method';
    const ESI_HMAC_PARAM            = 'hmac';
    const MAGE_CACHE_NAME           = 'turpentine_esi_blocks';

    /**
     * Cache for layout XML
     *
     * @var Mage_Core_Model_Layout_Element|SimpleXMLElement
     */
    protected $_layoutXml = null;

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
     * Get the URL param name for the ESI block scope
     *
     * @return string
     */
    public function getEsiScopeParam() {
        return self::ESI_SCOPE_PARAM;
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
     * Get the URL param name for the ESI inclusion method
     *
     * @return string
     */
    public function getEsiMethodParam() {
        return self::ESI_METHOD_PARAM;
    }

    /**
     * Get the URL param name for the ESI HMAC
     *
     * @return string
     */
    public function getEsiHmacParam() {
        return self::ESI_HMAC_PARAM;
    }

	/**
	 * Get referrer param
	 *
	 * @return string
	 */
	public function getEsiReferrerParam() {
		return Mage_Core_Controller_Varien_Action::PARAM_NAME_BASE64_URL;
	}

    /**
     * Get whether ESI debugging is enabled or not
     *
     * @return bool
     */
    public function getEsiDebugEnabled() {
        return Mage::helper( 'turpentine/varnish' )
            ->getVarnishDebugEnabled();
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
     * Check if the flash messages are enabled and we're not in the admin section
     *
     * @return bool
     */
    public function shouldFixFlashMessages() {
        return Mage::helper( 'turpentine/data' )->useFlashMessagesFix() &&
            Mage::app()->getStore()->getCode() !== 'admin';
    }

    /**
     * Get URL for redirects and dummy requests
     *
     * @return string
     */
    public function getDummyUrl() {
        return Mage::getUrl( 'checkout/cart' );
    }

    /**
     * Get mock request
     *
     * Used to pretend that the request was for the base URL instead of
     * turpentine/esi/getBlock while rendering ESI blocks. Not perfect, but may
     * be good enough
     *
     * @param  string $url=null
     * @return Mage_Core_Controller_Request_Http
     */
    public function getDummyRequest( $url=null ) {
        if( $url === null ) {
            $url = $this->getDummyUrl();
        }
        $request = new Nexcessnet_Turpentine_Model_Dummy_Request( $url );
        $request->fakeRouterDispatch();
        return $request;
    }

    /**
     * Get the cache type Magento uses
     *
     * @return string
     */
    public function getMageCacheName() {
        return self::MAGE_CACHE_NAME;
    }

    /**
     * Get the list of cache clear events to include with every ESI block
     *
     * @return array
     */
    public function getDefaultCacheClearEvents() {
        $events = array(
            'customer_login',
            'customer_logout',
        );
        return $events;
    }

    /**
     * Get the list of events that should cause the ESI cache to be cleared
     *
     * @return array
     */
    public function getCacheClearEvents() {
        Varien_Profiler::start( 'turpentine::helper::esi::getCacheClearEvents' );
        $cacheKey = $this->getCacheClearEventsCacheKey();
        $events = @unserialize( Mage::app()->loadCache( $cacheKey ) );
        if( is_null( $events ) || $events === false ) {
            $events = $this->_loadEsiCacheClearEvents();
            Mage::app()->saveCache( serialize( $events ), $cacheKey,
                array( 'LAYOUT_GENERAL_CACHE_TAG' ) );
        }
        Varien_Profiler::stop( 'turpentine::helper::esi::getCacheClearEvents' );
        return array_merge( $this->getDefaultCacheClearEvents(), $events );
    }

    /**
     * Get the default private ESI block TTL
     *
     * @return string
     */
    public function getDefaultEsiTtl() {
        return trim( Mage::getStoreConfig( 'web/cookie/cookie_lifetime' ) );
    }

    /**
     * Get the CORS origin field from the unsecure base URL
     *
     * If this isn't added to AJAX responses they won't load properly
     *
     * @return string
     */
    public function getCorsOrigin( $url=null ) {
        if( is_null( $url ) ) {
            $baseUrl = Mage::getBaseUrl();
        } else {
            $baseUrl = $url;
        }
        $path = parse_url( $baseUrl, PHP_URL_PATH );
        $domain = parse_url( $baseUrl, PHP_URL_HOST );
        // there has to be a better way to just strip the path off
        return substr( $baseUrl, 0,
            strpos( $baseUrl, $path,
                strpos( $baseUrl, $domain ) ) );
    }

    /**
     * Get the layout's XML structure
     *
     * This is cached because it's expensive to load for each ESI'd block
     *
     * @return Mage_Core_Model_Layout_Element|SimpleXMLElement
     */
    public function getLayoutXml() {
        Varien_Profiler::start( 'turpentine::helper::esi::getLayoutXml' );
        if( is_null( $this->_layoutXml ) ) {
            if( $useCache = Mage::app()->useCache( 'layout' ) ) {
                $cacheKey = $this->getFileLayoutUpdatesXmlCacheKey();
                $this->_layoutXml = simplexml_load_string(
                    Mage::app()->loadCache( $cacheKey ) );
            }
            // this check is redundant if the layout cache is disabled
            if( !$this->_layoutXml ) {
                $this->_layoutXml = $this->_loadLayoutXml();
                if( $useCache ) {
                    Mage::app()->saveCache( $this->_layoutXml->asXML(),
                        $cacheKey, array( 'LAYOUT_GENERAL_CACHE_TAG' ) );
                }
            }
        }
        Varien_Profiler::stop( 'turpentine::helper::esi::getLayoutXml' );
        return $this->_layoutXml;
    }

    /**
     * Get the cache key for the cache clear events
     *
     * @return string
     */
    public function getCacheClearEventsCacheKey() {
        $design = Mage::getDesign();
        return Mage::helper( 'turpentine/data' )
            ->getCacheKeyHash( array(
                'FILE_LAYOUT_ESI_CACHE_EVENTS',
                $design->getArea(),
                $design->getPackageName(),
                $design->getTheme( 'layout' ),
                Mage::app()->getStore()->getId(),
            ) );
    }

    /**
     * Get the cache key for the file layouts xml
     *
     * @return string
     */
    public function getFileLayoutUpdatesXmlCacheKey() {
        $design = Mage::getDesign();
        return Mage::helper( 'turpentine/data' )
            ->getCacheKeyHash( array(
                'FILE_LAYOUT_UPDATES_XML',
                $design->getArea(),
                $design->getPackageName(),
                $design->getTheme( 'layout' ),
                Mage::app()->getStore()->getId(),
            ) );
    }

    /**
     * Generate an ESI tag to be replaced by the content from the given URL
     *
     * Generated tag looks like:
     *     <esi:include src="$url" />
     *
     * @param  string $url url to pull content from
     * @return string
     */
    public function buildEsiIncludeFragment( $url ) {
        return sprintf( '<esi:include src="%s" />', $url );
    }

    /**
     * Generate an ESI tag with content that is removed when ESI processed, and
     * visible when not
     *
     * Generated tag looks like:
     *     <esi:remove>$content</esi>
     *
     * @param  string $content content to be removed
     * @return string
     */
    public function buildEsiRemoveFragment( $content ) {
        return sprintf( '<esi:remove>%s</esi>', $content );
    }

    /**
     * Get URL for grabbing form key via ESI
     *
     * @return string
     */
    public function getFormKeyEsiUrl() {
        $urlOptions = array(
            $this->getEsiTtlParam()         => $this->getDefaultEsiTtl(),
            $this->getEsiMethodParam()      => 'esi',
            $this->getEsiScopeParam()       => 'global',
            $this->getEsiCacheTypeParam()   => 'private',
        );
        return Mage::getUrl( 'turpentine/esi/getFormKey', $urlOptions );
    }

    /**
     * Load the ESI cache clear events from the layout
     *
     * @return array
     */
    protected function _loadEsiCacheClearEvents() {
        Varien_Profiler::start( 'turpentine::helper::esi::_loadEsiCacheClearEvents' );
        $layoutXml = $this->getLayoutXml();
        $events = $layoutXml->xpath(
            '//action[@method=\'setEsiOptions\']/params/flush_events/*' );
        if( $events ) {
            $events = array_unique( array_map(
                create_function( '$e',
                    'return (string)$e->getName();' ),
                $events ) );
        } else {
            $events = array();
        }
        Varien_Profiler::stop( 'turpentine::helper::esi::_loadEsiCacheClearEvents' );
        return $events;
    }

    /**
     * Load the layout's XML structure, bypassing any caching
     *
     * @return Mage_Core_Model_Layout_Element
     */
    protected function _loadLayoutXml() {
        Varien_Profiler::start( 'turpentine::helper::esi::_loadLayoutXml' );
        $design = Mage::getDesign();
        $layoutXml = Mage::getSingleton( 'core/layout' )
            ->getUpdate()
            ->getFileLayoutUpdatesXml(
                $design->getArea(),
                $design->getPackageName(),
                $design->getTheme( 'layout' ),
                Mage::app()->getStore()->getId() );
        Varien_Profiler::stop( 'turpentine::helper::esi::_loadLayoutXml' );
        return $layoutXml;
    }
}
