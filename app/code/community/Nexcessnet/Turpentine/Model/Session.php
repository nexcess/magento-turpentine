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

class Nexcessnet_Turpentine_Model_Session extends Mage_Core_Model_Session_Abstract {
    protected $_namespace = 'turpentine';

    public function __construct( $data=array() ) {
        $sessionName = isset( $data['name'] ) ? $data['name'] : null;
        $this->init( $this->_namespace, $sessionName );
        Mage::dispatchEvent(
            sprintf( '%s_session_init', $this->_namespace ),
            array( sprintf( '%s_session', $this->_namespace ) => $this ) );
    }

    /**
     * Save the messages for a given block to the session
     *
     * @param  string $blockName
     * @param  array $messages
     * @return null
     */
    public function saveMessages( $blockName, $messages ) {
        $allMessages = $this->getMessages();
        $allMessages[$blockName] = array_merge(
            $this->loadMessages( $blockName ), $messages );
        $this->setMessages( $allMessages );
    }

    /**
     * Retrieve the messages for a given messages block
     *
     * @param  string $blockName
     * @return array
     */
    public function loadMessages( $blockName ) {
        $messages = $this->getMessages();
        if( is_array( @$messages[$blockName] ) ) {
            return $messages[$blockName];
        } else {
            return array();
        }
    }

    /**
     * Clear the messages stored for a block
     *
     * @param  string $blockName
     * @return null
     */
    public function clearMessages( $blockName ) {
        $messages = $this->getMessages();
        unset( $messages[$blockName] );
        $this->setMessages( $messages );
    }

    /**
     * Retrieve the stored messages
     *
     * @param  boolean $clear=false
     * @return array
     */
    public function getMessages( $clear=false ) {
        $messages = $this->getData( 'messages' );
        if( !is_array( $messages ) ) {
            $messages = array();
        }
        return $messages;
    }

    /**
     * Store messages
     *
     * @param array $messages
     */
    public function setMessages( $messages ) {
        $this->setData( 'messages', $messages );
    }
}
