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

    /**
     * Full flush action, flushes all Magento URLs in Varnish cache
     *
     * @return null
     */
    public function flushAllAction() {
        Mage::dispatchEvent('turpentine_varnish_flush_all');
        $varnishctl = Mage::getModel( 'turpentine/varnish_admin' );
        if( $varnishctl->flushAll( $this->_getConfigurator() ) ) {
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
        $varnishctl = Mage::getModel( 'turpentine/varnish_admin' );
        if( $varnishctl->flushUrl( $this->_getConfigurator(), $pattern ) ) {
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
     * Save the config to the configured file action
     *
     * @return null
     */
    public function saveConfigAction() {
        Mage::dispatchEvent('turpentine_varnish_save_config');
        $cfgr = $this->_getConfigurator();
        $vcl = $cfgr->generate();
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
        $vcl = $this->_getConfigurator()->generate();
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
     * Get the appropriate configurator based on the specified Varnish version
     * in the Magento config
     *
     * @return Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract
     */
    protected function _getConfigurator() {
        switch( Mage::getStoreConfig( 'turpentine_servers/servers/version' ) ) {
            case '2':
                $cfgr = Mage::getModel( 'turpentine/varnish_configurator_version2' );
                break;
            case '3':
            default:
                $cfgr = Mage::getModel( 'turpentine/varnish_configurator_version3' );
                break;
        }
        return $cfgr;
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
