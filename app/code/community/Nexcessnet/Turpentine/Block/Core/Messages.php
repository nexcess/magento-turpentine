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
    protected $_singleRenderType    = null;

    /**
     * Sentinel to check if the toHtml method was skipped (getGroupedHtml was
     * called directly)
     *
     * Better name for this var would be '_magentoDevsAreDumb'
     *
     * @var boolean
     */
    protected $_directCall          = false;

    /**
     * List of session types to load messages from for the "messages" block.
     * Must be listed here because there's no other way to get this info.
     * @var array
     */
    protected $_messageStorageTypes = array(
        'catalog',
        'checkout',
        'tag',
        'customer',
        'review',
        'wishlist',
        'core',
    );

    /**
     * Storage for used types of message storages
     *
     * Added for compatibility with Magento 1.5
     *
     * @var array
     */
    protected $_usedStorageTypes = array( 'core/session' );

    public function _prepareLayout() {
        if( $this->_fixMessages() ) {
            /* do nothing */
            return $this;
        } else {
            return parent::_prepareLayout();
        }
    }

    /**
     * Set messages collection
     *
     * @param   Mage_Core_Model_Message_Collection $messages
     * @return  Mage_Core_Block_Messages
     */
    public function setMessages( Mage_Core_Model_Message_Collection $messages ) {
        if( $this->_fixMessages() ) {
            $this->_saveMessages( $messages->getItems() );
        } else {
            parent::setMessages( $messages );
        }
        return $this;
    }

    /**
     * Add messages to display
     *
     * @param Mage_Core_Model_Message_Collection $messages
     * @return Mage_Core_Block_Messages
     */
    public function addMessages( Mage_Core_Model_Message_Collection $messages ) {
        if( $this->_fixMessages() ) {
            $this->_saveMessages( $messages->getItems() );
        } else {
            parent::addMessages( $messages );
        }
        return $this;
    }

    /**
     * Adding new message to message collection
     *
     * @param   Mage_Core_Model_Message_Abstract $message
     * @return  Mage_Core_Block_Messages
     */
    public function addMessage( Mage_Core_Model_Message_Abstract $message ) {
        if( $this->_fixMessages() ) {
            $this->_saveMessages( $message->getItems() );
        } else {
            parent::addMessage( $message );
        }
        return $this;
    }

    /**
     * Override this in case some dumb layout decides to use it instead of the
     * standard toHtml stuff
     *
     * @param  mixed $type=self::NO_SINGLE_RENDER_TYPE
     * @return string
     */
    public function getHtml( $type=self::NO_SINGLE_RENDER_TYPE ) {
        $this->_singleRenderType = $type;
        return $this->_handleDirectCall( 'getHtml' )->toHtml();
    }

    /**
     * Override this in case some dumb layout decides to use it directly instead
     * of the standard toHtml stuff (i.e. most of core magento)
     *
     * @return string
     */
    public function getGroupedHtml() {
        return $this->_handleDirectCall( 'getGroupedHtml' )->toHtml();
    }

    /**
     * Add used storage type
     *
     * Method added for compatibility with Magento 1.5
     *
     * @param string $type
     */
    public function addStorageType( $type ) {
        $this->_usedStorageTypes[] = $type;
    }

    /**
     * Load layout options
     *
     * @return null
     */
    protected function _handleDirectCall( $methodCalled ) {
        // this doesn't actually do anything because _real_toHtml() won't be
        // called in this request context (unless the flash message isn't
        // actually supposed to be ajax/esi'd in)
        $this->_directCall = $methodCalled;
        if( $this->_fixMessages() ) {
            $layout = $this->getLayout();
            $layoutUpdate = $layout->getUpdate()->load( 'default' );
            if( Mage::app()->useCache( 'layout' ) ) {
                // this is skipped in the layout update load() if the "layout"
                // cache is enabled, which seems to cause the esi layout stuff
                // to not load, so we manually do it here
                foreach( $layoutUpdate->getHandles() as $handle ) {
                    $layoutUpdate->merge( $handle );
                }
            }
            $layout->generateXml();
            $layoutShim = Mage::getSingleton( 'turpentine/shim_mage_core_layout' );
            foreach( $layout->getNode()->xpath(
                    sprintf( '//reference[@name=\'%s\']/action',
                        $this->getNameInLayout() ) ) as $node ) {
                $layoutShim->shim_generateAction( $node );
            }
        }
        return $this;
    }

    /**
     * Render the messages block
     *
     * @return string
     */
    protected function _toHtml() {
        if( $this->_fixMessages() ) {
            if( $this->_shouldUseInjection() ) {
                $html = $this->renderView();
            } else {
                $this->_loadMessages();
                $this->_loadSavedMessages();
                $html = $this->_real_toHtml();
            }
        } else {
            $html = $this->_real_toHtml();
        }
        $this->_directCall = false;
        return $html;
    }

    /**
     * Check if this block has either the ajax or esi options set
     *
     * @return boolean
     */
    protected function _hasInjectOptions() {
        return $this->getEsiOptions() &&
            Mage::helper( 'turpentine/esi' )->shouldResponseUseEsi();
    }

    /**
     * Check if the block has injection options, and if they should be used
     *
     * @return boolean
     */
    protected function _shouldUseInjection() {
        return $this->_hasTemplateSet() &&
            $this->_hasInjectOptions() &&
            Mage::app()->getStore()->getCode() !== 'admin';
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

    /**
     * Preserve messages for later display
     *
     * @return null
     */
    protected function _saveMessages( $messages ) {
        if( $this->_fixMessages() && !$this->_isEsiRequest() ) {
            Mage::getSingleton( 'turpentine/session' )
                ->saveMessages( $this->getNameInLayout(), $messages );
        }
    }

    /**
     * Add messages to display
     *
     * @return null
     */
    protected function _loadMessages() {
        if( $this->getNameInLayout() == 'messages' ) {
            foreach( $this->_messageStorageTypes as $type ) {
                $storage = sprintf( '%s/session', $type );
                $this->addStorageType( $storage );
                $this->_loadMessagesFromStorage( $storage );
            }
        } else {
            $this->_loadMessagesFromStorage( 'core/session' );
        }
    }

    /**
     * Load messages saved to turpentine/session
     *
     * @return null
     */
    protected function _loadSavedMessages() {
        $session = Mage::getSingleton( 'turpentine/session' );
        foreach( $session->loadMessages( $this->getNameInLayout() ) as $msg ) {
            parent::addMessage( $msg );
        }
        $this->_clearMessages();
    }

    /**
     * Load messages from the specified session storage
     *
     * @param  string $type
     * @return null
     */
    protected function _loadMessagesFromStorage( $type ) {
        foreach( Mage::getSingleton( $type )
                    ->getMessages( true )->getItems() as $msg ) {
            parent::addMessage( $msg );
        }
    }

    /**
     * Clear messages saved to turpentine/session
     *
     * @return null
     */
    protected function _clearMessages() {
        Mage::getSingleton( 'turpentine/session' )
            ->clearMessages( $this->getNameInLayout() );
    }

    /**
     * Render output using parent methods
     *
     * @return string
     */
    protected function _real_toHtml() {
        if( !$this->_directCall ) {
            switch( $this->getNameInLayout() ) {
                case 'global_messages':
                    $this->_directCall = 'getHtml';
                    break;
                case 'messages':
                default:
                    $this->_directCall = 'getGroupedHtml';
                    break;
            }
        }
        switch( $this->_directCall ) {
            case 'getHtml':
                $html = parent::getHtml( $this->_singleRenderType );
                $this->_singleRenderType = self::NO_SINGLE_RENDER_TYPE;
                break;
            case 'getGroupedHtml':
            default:
                $html = parent::getGroupedHtml();
                break;
        }
        return $html;
    }

    /**
     * Check if we should fix the messages behavior to work with turpentine,
     * disable-able for compatibility with other extensions
     *
     * @return bool
     */
    protected function _fixMessages() {
        return Mage::helper( 'turpentine/esi' )->shouldFixFlashMessages();
    }

    /**
     * Check if this is an AJAX request
     *
     * Not very accurate, currently just checks if the module/controller/action
     * looks like the dummy request that is set by the esi controller
     *
     * @return boolean
     */
    protected function _isEsiRequest() {
        return is_subclass_of( Mage::app()->getRequest(),
            'Nexcessnet_Turpentine_Model_Dummy_Request' );
    }
}
