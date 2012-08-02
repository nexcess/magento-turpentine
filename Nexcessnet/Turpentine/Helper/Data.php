<?php

class Nexcessnet_Turpentine_Helper_Data extends Mage_Core_Helper_Abstract {
    const NO_CACHE_COOKIE = 'varnish_nocache';

    public function getNoCacheCookieName() {
        return self::NO_CACHE_COOKIE;
    }

    public function getNoCacheCookie() {
        return Mage::getModel( 'core/cookie' )->get(
            $this->getNoCacheCookieName() );
    }
}
