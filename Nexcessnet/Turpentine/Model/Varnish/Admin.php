<?php

class Nexcessnet_Turpentine_Model_Varnish_Admin {

    /**
     * Flush all Magento URLs in Varnish cache
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @return bool
     */
    public function flushAll( $cfgr ) {
        $success = true;
        $pattern = $cfgr->getBaseUrlPathRegex() . '.*';
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

    /**
     * Flush all Magento URLs matching the given (relative) regex
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @param  string $pattern regex to match against URLs
     * @return bool
     */
    public function flushUrl( $cfgr, $subPattern ) {
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

    /**
     * Generate and apply the config to the Varnish instances
     *
     * @param  Nexcessnet_Turpentine_Model_Varnish_Configurator_Abstract $cfgr
     * @return bool
     */
    public function applyConfig( $cfgr ) {
        $success = true;
        $vcl = $cfgr->generate();
        $vclname = hash( 'sha256', microtime() );
        foreach( $cfgr->getSockets() as $socket ) {
            try {
                $socket->vcl_inline( $vclname, $vcl );
                $socket->vcl_use( $vclname );
            } catch( Mage_Core_Exception $e ) {
                $success = $success && false;
                continue;
            }
            $success = $success && true;
        }
        return $success;
    }
}
