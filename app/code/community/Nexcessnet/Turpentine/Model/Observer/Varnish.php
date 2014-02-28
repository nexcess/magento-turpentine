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

class Nexcessnet_Turpentine_Model_Observer_Varnish extends Varien_Event_Observer {
    /**
     * Check sentinel flags and set headers/cookies as needed
     *
     * Events: http_response_send_before
     *
     * @param  mixed $eventObject
     * @return null
     */
    public function setCacheFlagHeader( $eventObject ) {
        $response = $eventObject->getResponse();
        if( Mage::helper( 'turpentine/varnish' )->shouldResponseUseVarnish() ) {
            $response->setHeader( 'X-Turpentine-Cache',
                Mage::registry( 'turpentine_nocache_flag' ) ? '0' : '1' );
            if( Mage::helper( 'turpentine/varnish' )->getVarnishDebugEnabled() ) {
                Mage::helper( 'turpentine/debug' )->logDebug(
                    'Set Varnish cache flag header to: ' .
                    ( Mage::registry( 'turpentine_nocache_flag' ) ? '0' : '1' ) );
            }
        }
    }

    /**
     * Add a rewrite for catalog/product_list_toolbar if config option enabled
     *
     * @param Varien_Object $eventObject
     * @return null
     */
    public function addProductListToolbarRewrite( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->shouldFixProductListToolbar() ) {
            Mage::getSingleton( 'turpentine/shim_mage_core_app' )
                ->shim_addClassRewrite( 'block', 'catalog', 'product_list_toolbar',
                    'Nexcessnet_Turpentine_Block_Catalog_Product_List_Toolbar' );
        }
    }

    /**
     * Re-apply and save Varnish configuration on config change
     *
     * @param  mixed $eventObject
     * @return null
     */
    public function adminSystemConfigChangedSection( $eventObject ) {
        if( Mage::helper( 'turpentine/varnish' )->getVarnishEnabled() &&
                Mage::helper( 'turpentine/data' )->getAutoApplyOnSave() ) {
            $result = Mage::getModel( 'turpentine/varnish_admin' )->applyConfig();
            $session = Mage::getSingleton( 'core/session' );
            foreach( $result as $name => $value ) {
                if( $value === true ) {
                    $session->addSuccess( Mage::helper( 'turpentine/data' )
                        ->__( 'VCL successfully applied to: ' . $name ) );
                } else {
                    $session->addError( Mage::helper( 'turpentine/data' )
                        ->__( sprintf( 'Failed to apply the VCL to %s: %s',
                            $name, $value ) ) );
                }
            }
            $cfgr = Mage::getModel( 'turpentine/varnish_admin' )->getConfigurator();
            if( is_null( $cfgr ) ) {
                $session->addError( Mage::helper( 'turpentine/data' )
                    ->__( 'Failed to load configurator' ) );
            } else {
                $result = $cfgr->save( $cfgr->generate() );
                if( $result[0] ) {
                    $session->addSuccess( Mage::helper('turpentine/data' )
                        ->__( 'The VCL file has been saved.' ) );
                } else {
                    $session->addError( Mage::helper('turpentine/data' )
                        ->__( 'Failed to save the VCL file: ' . $result[1]['message'] ) );
                }
            }
        }
    }
}
