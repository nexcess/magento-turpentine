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

class Nexcessnet_Turpentine_Block_Core_Messages extends Mage_Core_Block_Messages {
    /**
     * Sentinel value to see if getHtml was passed a specific message type,
     * can't use null because that is the default so no way to tell between
     * the default and  not actually calling it
     */
    const NO_SINGLE_RENDER_TYPE     = -1;

    /**
     * Hold the message type to call getHtml with
     * @var mixed
     */
    protected $_singleRenderType = self::NO_SINGLE_RENDER_TYPE;

    /**
     * Override this in case some dumb layout decides to use it instead of the
     * standard toHtml stuff
     *
     * @param  mixed $type=self::NO_SINGLE_RENDER_TYPE
     * @return string
     */
    public function getHtml( $type=self::NO_SINGLE_RENDER_TYPE ) {
        if( $type !== self::NO_SINGLE_RENDER_TYPE ) {
            $this->_singleRenderType = $type;
        }
        return $this->toHtml();
    }

    /**
     * Override this in case some dumb layout decides to use it directly instead
     * of the standard toHtml stuff (i.e. most of core magento)
     *
     * @return string
     */
    public function getGroupedHtml() {
        return $this->toHtml();
    }

    /**
     * Render the messages block
     *
     * @return string
     */
    protected function _toHtml() {
        if( $this->_hasTemplateSet() && $this->_hasInjectOptions() &&
            ( $this->getAjaxOptions() &&
                Mage::helper( 'turpentine/ajax' )->shouldResponseUseAjax() ) ||
            ( $this->getEsiOptions() &&
                Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi() ) ) {
            return $this->renderView();
        } else {
            if( Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi() ||
                    Mage::helper( 'turpentine/ajax' )->shouldResponseUseAjax() ) {
                foreach( array( 'catalog', 'checkout', 'customer' )
                        as $storagePrefix ) {
                    $storageType = sprintf( '%s/session', $storagePrefix );
                    $storage = Mage::getSingleton( $storageType );
                    if( $storage ) {
                        $this->addStorageType( $storageType );
                        $this->addMessages( $storage->getMessages( true, true ) );
                    }
                }
            }
            if( $this->_singleRenderType !== self::NO_SINGLE_RENDER_TYPE ) {
                $html = parent::getHtml( $this->_singleRenderType );
                $this->_singleRenderType = self::NO_SINGLE_RENDER_TYPE;
            } else {
                $html = parent::getGroupedHtml();
            }
            return $html;
        }
    }

    /**
     * Check if this block has either the ajax or esi options set
     *
     * @return boolean
     */
    protected function _hasInjectOptions() {
        return $this->getEsiOptions() || $this->getAjaxOptions();
    }

    /**
     * Check if this block has a template set
     *
     * This should be false unless we're rendering with the ajax/esi option stuff
     *
     * @return boolean
     */
    protected function _hasTemplateSet() {
        return (bool)$this->getTemplate();
    }
}
