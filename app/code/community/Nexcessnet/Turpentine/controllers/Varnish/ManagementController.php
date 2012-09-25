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

class Nexcessnet_Turpentine_Varnish_ManagementController
    extends Mage_Adminhtml_Controller_Action {

    /**
     * Management index action, displays buttons/forms for config and cache
     * management
     *
     * @return null
     */
    public function indexAction() {
        $this->_title($this->__('System'))
            ->_title(Mage::helper('turpentine')->__('Varnish Management'));
        $this->loadLayout()
            ->_setActiveMenu('system/turpentine')
            ->_addContent($this->getLayout()
                ->createBlock('turpentine/management'))
            ->renderLayout();
    }

    /**
     * Full flush action, flushes all Magento URLs in Varnish cache
     *
     * @return null
     */
    public function flushAllAction() {
        Mage::dispatchEvent('turpentine_varnish_flush_all');
        if( Mage::getModel( 'turpentine/varnish_admin' )->flushAll() ) {
            $this->_getSession()
                ->addSuccess(Mage::helper('turpentine')
                    ->__('The Varnish cache has been flushed.'));
        } else {
            $this->_getSession()
                ->addError(Mage::helper('turpentine')
                    ->__('Error flushing the Varnish cache.'));
        }
        $this->_redirect('*/*');
    }

    /**
     * Partial flush action, flushes Magento URLs matching "pattern" in POST
     * data
     *
     * @return null
     */
    public function flushPartialAction() {
        $postData = $this->getRequest()->getPost();
        if( !isset( $postData['pattern'] ) ) {
            Mage::throwException( $this->__( 'Missing URL post data' ) );
        }
        $pattern = $postData['pattern'];
        Mage::dispatchEvent('turpentine_varnish_flush_partial');
        if( Mage::getModel( 'turpentine/varnish_admin' )
                ->flushUrl( $pattern ) ) {
            $this->_getSession()
                ->addSuccess(Mage::helper('turpentine')
                    ->__('The Varnish cache has been flushed for URLs matching: ' . $pattern ) );
        } else {
            $this->_getSession()
                ->addError(Mage::helper('turpentine')
                    ->__('Error flushing the Varnish cache.'));
        }
        $this->_redirect('*/*');
    }

    /**
     * Flush objects by content type (ctype in POST)
     *
     * @return null
     */
    public function flushContentTypeAction() {
        $postData = $this->getRequest()->getPost();
        if( !isset( $postData['ctype'] ) ) {
            Mage::throwException( $this->__( 'Missing URL post data' ) );
        }
        $ctype = $postData['ctype'];
        Mage::dispatchEvent('turpentine_varnish_flush_content_type');
        if( Mage::getModel( 'turpentine/varnish_admin' )
                ->flushContentType( $ctype ) ) {
            $this->_getSession()
                ->addSuccess(Mage::helper('turpentine')
                    ->__('The Varnish cache has been flushed for objects with content type: ' . $ctype ) );
        } else {
            $this->_getSession()
                ->addError(Mage::helper('turpentine')
                    ->__('Error flushing the Varnish cache.'));
        }
        $this->_redirect('*/*');
    }

    /**
     * Load the current VCL in varnish and activate it
     *
     * @return null
     */
    public function applyConfigAction() {
        Mage::dispatchEvent('turpentine_varnish_apply_config');
        $result = Mage::getModel( 'turpentine/varnish_admin' )->applyConfig();
        foreach( $result as $name => $value ) {
            if( $value === true ) {
                $this->_getSession()
                    ->addSuccess( Mage::helper( 'turpentine' )
                        ->__( 'VCL successfully applied to ' . $name ) );
            } else {
                $this->_getSession()
                    ->addError( Mage::helper( 'turpentine' )
                        ->__( sprintf( 'Failed to apply the VCL to %s: %s',
                            $name, $value ) ) );
            }
        }
        $this->_redirect('*/*');
    }

    /**
     * Save the config to the configured file action
     *
     * @return null
     */
    public function saveConfigAction() {
        Mage::dispatchEvent('turpentine_varnish_save_config');
        $cfgr = Mage::getModel( 'turpentine/varnish_admin' )->getConfigurator();
        $result = $cfgr->save( $cfgr->generate() );
        if( $result[0] ) {
            $this->_getSession()
                ->addSuccess( Mage::helper('turpentine')
                    ->__('The VCL file has been saved.' ) );
        } else {
            $this->_getSession()
                ->addError( Mage::helper('turpentine')
                    ->__('Failed to save the VCL file: ' . $result[1]['message'] ) );
        }
        $this->_redirect('*/*');
    }

    /**
     * Present the generated config for download
     *
     * @return $this
     */
    public function getConfigAction() {
        $vcl = Mage::getModel( 'turpentine/varnish_admin' )
            ->getConfigurator()
            ->generate();
        $this->getResponse()
            ->setHttpResponseCode( 200 )
            ->setHeader( 'Content-Type', 'text/plain', true )
            ->setHeader( 'Content-Length', strlen( $vcl ) )
            ->setHeader( 'Content-Disposition',
                'attachment; filename=default.vcl' )
            ->setBody( $vcl );
        return $this;
    }

    /**
     * Check if a visitor is allowed access to this controller/action(?)
     *
     * @return boolean
     */
    protected function _isAllowed() {
        return Mage::getSingleton('admin/session')
            ->isAllowed('system/turpentine');
    }
}