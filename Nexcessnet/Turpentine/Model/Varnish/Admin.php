<?php

class Nexcessnet_Turpentine_Model_Varnish_Admin {

    /**
     * Flush all Magento URLs in Varnish cache
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @return bool
     */
    public function flushAll() {
        return $this->flushUrl( '.*' );
    }

    /**
     * Flush all Magento URLs matching the given (relative) regex
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @param  string $pattern regex to match against URLs
     * @return bool
     */
    public function flushUrl( $subPattern ) {
        $cfgr = $this->getConfigurator();
        $pattern = $cfgr->getBaseUrlPathRegex() . $subPattern;
        $success = true;
        foreach( $cfgr->getSockets() as $socket ) {
            try {
                $socket->ban_url( $pattern );
            } catch( Mage_Core_Exception $e ) {
                $success = $success && false;
                continue;
            }
            $success = $success && true;
        }
        return $success;
    }

    public function flushContentType( $contentType ) {
        $success = true;
        foreach( $this->getConfigurator()->getSockets() as $socket ) {
            try {
                $socket->ban( 'obj.http.Content-type', '~', $contentType );
            } catch( Mage_Core_Exception $e ) {
                $success = $success && false;
                continue;
            }
            $success = $success && true;
        }
        return $success;
    }

    /**
     * Generate and apply the config to the Varnish instances
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @return bool
     */
    public function applyConfig() {
        $result = array();
        $cfgr = $this->getConfigurator();
        $vcl = $cfgr->generate();
        $vclname = hash( 'sha256', microtime() );
        foreach( $cfgr->getSockets() as $socket ) {
            $socketName = sprintf( '%s:%d', $socket->getHost(), $socket->getPort() );
            try {
                $socket->vcl_inline( $vclname, $vcl );
                $socket->vcl_use( $vclname );
            } catch( Mage_Core_Exception $e ) {
                $result[$socketName] = $e->getMessage();
                continue;
            }
            $result[$socketName] = true;
        }
        return $result;
    }

    /**
     * Get the appropriate configurator based on the specified Varnish version
     * in the Magento config
     *
     * @param  string $version=null provide version string instead of pulling
     *                              from config
     * @return Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract
     */
    public function getConfigurator( $version=null ) {
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
                return $this->getConfigurator( $socket->getVersion() );
                break;
        }
        return $cfgr;
    }
}
