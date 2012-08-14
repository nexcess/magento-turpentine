<?php

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

    public function testAction() {
        try {
            $socket = Mage::getModel( 'turpentine/varnish_admin_socket' );
            $socket->setHost( '127.0.0.1' );
            $socket->setPort( 6082 );
            var_dump( $socket->vcl_list() );
        } catch( Exception $e ) {
            var_dump( $e );
        }
        exit();
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
        if( Mage::getModel( 'turpentine/varnish_admin' )->applyConfig() ) {
            $this->_getSession()
                ->addSuccess( Mage::helper( 'turpentine' )
                    ->__( 'VCL successfully applied' ) );
        } else {
            $this->_getSession()
                ->addError( Mage::helper( 'turpentine' )
                    ->__( 'Failed to apply the VCL' ) );
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
