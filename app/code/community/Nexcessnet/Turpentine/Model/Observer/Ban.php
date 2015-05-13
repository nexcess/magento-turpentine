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

/**
 * Most of this is taken from @link https://github.com/huguesalary/Magento-Varnish
 */

class Nexcessnet_Turpentine_Model_Observer_Ban extends Varien_Event_Observer {

    /**
     * Cache the varnish admin object
     * @var Nexcessnet_Turpentine_Model_Varnish_Admin
     */
    protected $_varnishAdmin    = null;
    /**
     * Flag to prevent doing the ESI cache clear more than once per request
     * @var boolean
     */
    protected $_esiClearFlag    = array();

    /**
     * Clear the ESI block cache for a specific client
     *
     * Events:
     *     the events are applied dynamically according to what events are set
     *     for the various blocks' esi policies
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banClientEsiCache( $eventObject ) {
        $eventName = $eventObject->getEvent()->getName();
        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() &&
                !in_array( $eventName, $this->_esiClearFlag ) ) {
            $sessionId = Mage::app()->getRequest()->getCookie( 'frontend' );
            if( $sessionId ) {
                $result = $this->_getVarnishAdmin()->flushExpression(
                    'obj.http.X-Varnish-Session', '==', $sessionId,
                    '&&', 'obj.http.X-Turpentine-Flush-Events', '~',
                    $eventName );
                Mage::dispatchEvent( 'turpentine_ban_client_esi_cache', $result );
                if( $this->_checkResult( $result ) ) {
                    Mage::helper( 'turpentine/debug' )
                        ->logDebug( 'Cleared ESI cache for client (%s) on event: %s',
                            $sessionId, $eventName );
                } else {
                    Mage::helper( 'turpentine/debug' )
                        ->logWarn(
                            'Failed to clear Varnish ESI cache for client: %s',
                            $sessionId );
                }
            }
            $this->_esiClearFlag[] = $eventName;
        }
    }

    /**
     * Ban a specific product page from the cache
     *
     * Events:
     *     catalog_product_save_commit_after
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banProductPageCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $banHelper = Mage::helper( 'turpentine/ban' );
            $product = $eventObject->getProduct();
            $urlPattern = $banHelper->getProductBanRegex( $product );
            $result = $this->_getVarnishAdmin()->flushUrl( $urlPattern );
            Mage::dispatchEvent( 'turpentine_ban_product_cache', $result );
            $cronHelper = Mage::helper( 'turpentine/cron' );
            if( $this->_checkResult( $result ) &&
                    $cronHelper->getCrawlerEnabled() ) {
                $cronHelper->addProductToCrawlerQueue( $product );
                foreach( $banHelper->getParentProducts( $product )
                        as $parentProduct ) {
                    $cronHelper->addProductToCrawlerQueue( $parentProduct );
                }
            }
        }
    }

    /**
     * Ban a product page from the cache if it's stock status changed
     *
     * Events:
     *     cataloginventory_stock_item_save_after
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banProductPageCacheCheckStock( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $item = $eventObject->getItem();
            if( $item->getStockStatusChangedAutomatically() ||
                    ( $item->getOriginalInventoryQty() <= 0 &&
                        $item->getQty() > 0 &&
                        $item->getQtyCorrection() > 0 ) ) {
                $banHelper = Mage::helper( 'turpentine/ban' );
                $cronHelper = Mage::helper( 'turpentine/cron' );
                $product = Mage::getModel( 'catalog/product' )
                    ->load( $item->getProductId() );
                $urlPattern = $banHelper->getProductBanRegex( $product );
                $result = $this->_getVarnishAdmin()->flushUrl( $urlPattern );
                Mage::dispatchEvent( 'turpentine_ban_product_cache_check_stock',
                    $result );
                if( $this->_checkResult( $result ) &&
                        $cronHelper->getCrawlerEnabled() ) {
                    $cronHelper->addProductToCrawlerQueue( $product );
                    foreach( $banHelper->getParentProducts( $product )
                            as $parentProduct ) {
                        $cronHelper->addProductToCrawlerQueue( $parentProduct );
                    }
                }
            }
        }
    }

    /**
     * Ban a category page, and any subpages on save
     *
     * Events:
     *     catalog_category_save_commit_after
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banCategoryCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $category = $eventObject->getCategory();
            $result = $this->_getVarnishAdmin()->flushUrl( $category->getUrlKey() );
            Mage::dispatchEvent( 'turpentine_ban_category_cache', $result );
            $cronHelper = Mage::helper( 'turpentine/cron' );
            if( $this->_checkResult( $result ) &&
                    $cronHelper->getCrawlerEnabled() ) {
                $cronHelper->addCategoryToCrawlerQueue( $category );
            }
        }
    }

    /**
     * Clear the media (CSS/JS) cache, corresponds to the buttons on the cache
     * page in admin
     *
     * Events:
     *     clean_media_cache_after
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banMediaCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushUrl( 'media/(?:js|css)/' );
            Mage::dispatchEvent( 'turpentine_ban_media_cache', $result );
            $this->_checkResult( $result );
        }
    }

    /**
     * Flush catalog images cache, corresponds to same button in admin cache
     * management page
     *
     * Events:
     *     clean_catalog_images_cache_after
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banCatalogImagesCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushUrl(
                'media/catalog/product/cache/' );
            Mage::dispatchEvent( 'turpentine_ban_catalog_images_cache', $result );
            $this->_checkResult( $result );
        }
    }

    /**
     * Ban a specific CMS page from cache after edit
     *
     * Events:
     *     cms_page_save_commit_after
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banCmsPageCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $pageId = $eventObject->getDataObject()->getIdentifier();
            $result = $this->_getVarnishAdmin()->flushUrl( $pageId . '(?:\.html?)?$' );
            Mage::dispatchEvent( 'turpentine_ban_cms_page_cache', $result );
            $cronHelper = Mage::helper( 'turpentine/cron' );
            if( $this->_checkResult( $result ) &&
                    $cronHelper->getCrawlerEnabled() ) {
                $cronHelper->addCmsPageToCrawlerQueue( $pageId );
            }
        }
    }

    /**
     * Ban a specific CMS page revision from cache after edit (enterprise edition only)
     * Events:
     *     enterprise_cms_revision_save_commit_after
     *
     * @param Varien_Object $eventObject
     * @return null
     */
    public function banCmsPageRevisionCache($eventObject) {
        if ( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $pageId = $eventObject->getDataObject()->getPageId();
            $page = Mage::getModel( 'cms/page' )->load( $pageId );

            // Don't do anything if the page isn't found.
            if( !$page ) {
                return;
            }
            $pageIdentifier = $page->getIdentifier();
            $result = $this->_getVarnishAdmin()->flushUrl( $pageIdentifier . '(?:\.html?)?$' );
            Mage::dispatchEvent( 'turpentine_ban_cms_page_cache', $result );
            $cronHelper = Mage::helper( 'turpentine/cron' );
            if( $this->_checkResult( $result ) &&
                $cronHelper->getCrawlerEnabled() ) {
                $cronHelper->addCmsPageToCrawlerQueue( $pageIdentifier );
            }
        }
    }

    /**
     * Do a full cache flush, corresponds to "Flush Magento Cache" and
     * "Flush Cache Storage" buttons in admin > cache management
     *
     * Events:
     *     adminhtml_cache_flush_system
     *     adminhtml_cache_flush_all
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banAllCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushAll();
            Mage::dispatchEvent( 'turpentine_ban_all_cache', $result );
            $this->_checkResult( $result );
        }
    }

    /**
     * Do a flush on the ESI blocks
     *
     * Events:
     *     adminhtml_cache_refresh_type
     *
     * @param  Varien_Object $eventObject
     * @return null
     */
    public function banCacheType( $eventObject ) {
        switch( $eventObject->getType() ) {
            //note this is the name of the container xml tag in config.xml,
            // **NOT** the cache tag name
            case Mage::helper( 'turpentine/esi' )->getMageCacheName():
                if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() ) {
                    $result = $this->_getVarnishAdmin()->flushUrl(
                        '/turpentine/esi/getBlock/' );
                    Mage::dispatchEvent( 'turpentine_ban_esi_cache', $result );
                    $this->_checkResult( $result );
                }
                break;
            case Mage::helper( 'turpentine/varnish' )->getMageCacheName():
                $this->banAllCache( $eventObject );
                break;
        }
    }

    /**
     * Ban a product's reviews page
     *
     * @param  Varien_Object $eventObject
     * @return bool
     */
    public function banProductReview( $eventObject ) {
        $patterns = array();
        $review = $eventObject->getObject();
        $products = $review->getProductCollection()->getItems();
        $productIds = array_unique( array_map(
            create_function( '$p', 'return $p->getEntityId();' ),
            $products ) );
        $patterns[] = sprintf( '/review/product/list/id/(?:%s)/category/',
            implode( '|', array_unique( $productIds ) ) );
        $patterns[] = sprintf( '/review/product/view/id/%d/',
            $review->getEntityId() );
        $productPatterns = array();
        foreach ( $products as $p ) {
            $urlKey = $p->getUrlModel()->formatUrlKey( $p->getName() );
            if ( $urlKey ) {
                $productPatterns[] = $urlKey;
            }
        }
        if ( !empty($productPatterns) ) {
            $productPatterns = array_unique( $productPatterns );
            $patterns[] = sprintf( '(?:%s)', implode( '|', $productPatterns ) );
        }
        $urlPattern = implode( '|', $patterns );

        $result = $this->_getVarnishAdmin()->flushUrl( $urlPattern );
        return $this->_checkResult( $result );
    }

    /**
     * Check a result from varnish admin action, log if result has errors
     *
     * @param  array $result stored as $socketName => $result
     * @return bool
     */
    protected function _checkResult( $result ) {
        $rvalue = true;
        foreach( $result as $socketName => $value ) {
            if( $value !== true ) {
                Mage::helper( 'turpentine/debug' )->logWarn(
                    'Error in Varnish action result for server [%s]: %s',
                    $socketName, $value );
                $rvalue = false;
            }
        }
        return $rvalue;
    }

    /**
     * Get the varnish admin socket
     *
     * @return Nexcessnet_Turpentine_Model_Varnish_Admin
     */
    protected function _getVarnishAdmin() {
        if( is_null( $this->_varnishAdmin ) ) {
            $this->_varnishAdmin = Mage::getModel( 'turpentine/varnish_admin' );
        }
        return $this->_varnishAdmin;
    }
}
