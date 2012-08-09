<?php

class Nexcessnet_Turpentine_Helper_Data extends Mage_Core_Helper_Abstract {
    const NO_CACHE_COOKIE   = 'varnish_nocache';
    const ADMIN_COOKIE      = 'adminhtml';

    /**
     * Get the name of the varnish no cache cookie
     *
     * @return string
     */
    public function getNoCacheCookieName() {
        return self::NO_CACHE_COOKIE;
    }

    /**
     * Get the name of the admin cookie
     *
     * @return string
     */
    public function getAdminCookieName() {
        return self::ADMIN_COOKIE;
    }

    /**
     * Get the actual Varnish no cache cookie object
     *
     * @return Mage_Core_Model_Cookie
     */
    public function getNoCacheCookie() {
        return Mage::getModel( 'core/cookie' )->get(
            $this->getNoCacheCookieName() );
    }
}
