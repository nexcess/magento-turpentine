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

class Nexcessnet_Turpentine_Model_Observer_Ban extends Varien_Event_Observer {

    protected $_varnishAdmin    = null;
    protected $_esiClearFlag    = false;

    public function banClientEsiCache( $eventObject ) {
        if( Mage::helper( 'turpentine/esi' )->getEsiEnabled() &&
                !$this->_esiClearFlag ) {
            $sessionId = Mage::app()->getRequest()->getCookie( 'frontend' );
            $result = $this->_getVarnishAdmin()->flushExpression(
                'obj.http.X-Varnish-Session', '~', $sessionId );
            Mage::app()->cleanCache(
                array( 'TURPENTINE_ESI_CLIENTID_' . $sessionId ) );
            Mage::dispatchEvent( 'turpentine_ban_client_esi_cache', $result );
            if( !$result ) {
                $this->_logWarn(
                    sprintf( 'Error flushing Varnish ESI cache for client (%s): %s',
                        $sessionId ),
                    $result );
            } elseif( Mage::helper( 'turpentine/esi' )->getEsiDebugEnabled() ) {
                Mage::log( 'Cleared Varnish ESI cache for client: ' . $sessionId );
            }
            $this->_esiClearFlag = true;
        }
    }

    public function banProductPageCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $productUrl = $eventObject->getProduct()->getUrlKey();
            $result = $this->_getVarnishAdmin()->flushUrl( $productUrl );
            Mage::dispatchEvent( 'turpentine_ban_product_cache', $result );
            if( !$result ) {
                $this->_logWarn(
                    sprintf( 'Error flushing Varnish cache for product (%s): %s',
                        $productUrl ),
                    $result );
            }
        }
    }

    public function banProductPageCacheCheckStock( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $item = $eventObject->getItem();
            if( $item->getStockStatusChangedAuto() ||
                    ( $item->getOriginalInventoryQty() <= 0 &&
                        $item->getQty() > 0 &&
                        $item->getQtyCorrection() > 0 ) ) {
                $parentIds = array_merge(
                    Mage::getModel( 'catalog/product_type_configurable' )
                        ->getParentIdsByChild( $item->getProductId() ),
                    Mage::getModel( 'catalog/product_type_grouped' )
                        ->getParentIdsByChild( $item->getProductId() ) );
                $urlPatterns = array();
                foreach( $parentIds as $parentId ) {
                    $parentProduct = Mage::getModel( 'catalog/product' )
                        ->load( $parentId );
                    $urlPatterns[] = $parentProduct->getUrlKey();
                }
                $product = Mage::getModel( 'catalog/product' )
                    ->load( $item->getProductId() );
                $urlPatterns[] = $product->getUrlKey();
                $pattern = sprintf( '(?:%s)', implode( '|', $urlPatterns ) );
                $result = $this->_getVarnishAdmin()->flushUrl( $pattern );
                Mage::dispatchEvent( 'turpentine_ban_product_cache_check_stock',
                    $result );
                if( !$result ) {
                    $this->_logWarn(
                        sprintf( 'Error flushing Varnish cache for product (%s): %s',
                            $product->getUrlKey() ),
                        $result );
                }
            }
        }
    }

    public function banMediaCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushUrl( 'media/(?:js|css)/' );
            Mage::dispatchEvent( 'turpentine_ban_media_cache', $result );
            if( !$result ) {
                $this->_logWarn( 'Error flushing Varnish media cache: %s',
                    $result );
            }
        }
    }

    public function banCatalogImagesCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushUrl(
                'media/catalog/product/cache/' );
            Mage::dispatchEvent( 'turpentine_ban_catalog_images_cache', $result );
            if( !$result ) {
                $this->_logWarn( 'Error flushing catalog images cache: %s',
                    $result );
            }
        }
    }

    public function banCmsPageCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $pageId = $eventObject->getDataObject()->getIdentifier();
            $result = $this->_getVarnishAdmin()->flushUrl( $pageId . '\.html$' );
            Mage::dispatchEvent( 'turpentine_ban_cms_page_cache', $result );
            if( !$result ) {
                $this->_logWarn(
                    sprintf( 'Error flushing Varnish CMS page cache (%s): %s',
                        $pageId ),
                    $result );
            }
        }
    }

    public function banAllCache( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() ) {
            $result = $this->_getVarnishAdmin()->flushAll();
            Mage::dispatchEvent( 'turpentine_ban_all_cache', $result );
            if( !$result ) {
                $this->_logWarn( 'Error flushing full Varnish cache: %s',
                    $result );
            }
        }
    }

    protected function _getVarnishAdmin() {
        if( is_null( $this->_varnishAdmin ) ) {
            $this->_varnishAdmin = Mage::getModel( 'turpentine/varnish_admin' );
        }
        return $this->_varnishAdmin;
    }

    protected function _logWarn( $message, $result ) {
        Mage::log( sprintf( $message, print_r( $result, true ) ), Zend_Log::WARN );
    }
}
