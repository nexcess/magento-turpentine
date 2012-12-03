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

/**
 * Slightly modified from:
 * @link http://magedev.com/2010/10/15/adding-event-observer-on-the-fly/
 */
class Nexcessnet_Turpentine_Model_Mage_Shim extends Mage_Core_Model_App {
    /**
     * Adds new observer for specified event
     *
     * @param string $area (global|admin...)
     * @param string $eventName name of the event to observe
     * @param string $obsName name of the observer (as specified in config.xml)
     * @param string $type (model|singleton)
     * @param string $class identifier of the observing model class
     * @param string $method name of the method to call
     * @return null
     */
    public function addEventObserver( $area, $eventName, $obsName, $type=null, $class=null, $method=null ) {
        $eventConfig = new Varien_Simplexml_Config();
        $eventConfig->loadDom( $this->_getConfigDom( $eventName, $obsName,
            $type, $class, $method ) );
        Mage::getConfig()->extend( $eventConfig, true );
        //This wouldn't work if PHP had a sane object model
        Mage::app()->_events[$area][$eventName] = null;
    }

    /**
     * Prepares event DOM node used for updating configuration
     *
     * @param string $eventName
     * @param string $obsName
     * @param string $type
     * @param string $class
     * @param string $method
     * @return DOMDocument
     */
    protected function _getConfigDom( $eventName, $obsName, $type=null, $class=null, $method=null ) {
        $dom = new DOMDocument("1.0");
        $config = $dom->createElement("config");
        $observers = $config->appendChild($dom->createElement('global'))
               ->appendChild($dom->createElement("events"))
               ->appendChild($dom->createElement($eventName))
               ->appendChild($dom->createElement("observers"));
        $observer = $dom->createElement($obsName);
        if ($class) {
            if ($method) {
                if ($type) {
                    $observer->appendChild($dom->createElement('type', $type));
                }
                $observer->appendChild($dom->createElement('class', $class));
                $observer->appendChild($dom->createElement('method', $method));
            }
        }
        $observers->appendChild($observer);
        $dom->appendChild($config);
        return $dom;
    }
}
