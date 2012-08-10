<?php
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
