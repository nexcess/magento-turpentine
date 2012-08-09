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
     * Load the current VCL in varnish and activate it
     *
     * @return null
     */
    public function applyConfigAction() {
        Mage::dispatchEvent('turpentine_varnish_apply_config');
        $varnishctl = Mage::getModel( 'turpentine/varnish_admin' );
        if( $varnishctl->applyConfig( $this->_getConfigurator() ) ) {
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
        $cfgr = $this->_getConfigurator();
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
     * @param  string $version=null provide version string instead of pulling
     *                              from config
     * @return Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract
     */
    protected function _getConfigurator( $version=null ) {
        if( is_null( $version ) ) {
            $version = Mage::getStoreConfig(
                'turpentine_servers/servers/version' );
        }
        switch( $version ) {
            case '2.1':
                $cfgr = Mage::getModel( 'turpentine/varnish_configurator_version2' );
                break;
            case '3.0':
                $cfgr = Mage::getModel( 'turpentine/varnish_configurator_version3' );
                break;
            case 'auto':
            default:
                $hosts = explode( PHP_EOL,
                    Mage::getStoreConfig( 'turpentine_servers/servers/server_list' ) );
                list( $host, $port ) = explode( ':', $hosts[0] );
                $socket = Mage::getModel( 'turpentine/varnish_admin_socket',
                    array( 'host' => $host, 'port' => $port ) );
                return $this->_getConfigurator( $socket->getVersion() );
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
