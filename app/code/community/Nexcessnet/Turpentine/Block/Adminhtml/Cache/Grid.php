<?php

/**
 * User: damian.pastorini@usestrategery.com
 * Date: 08/01/14
 */

class Nexcessnet_Turpentine_Block_Adminhtml_Cache_Grid extends Mage_Adminhtml_Block_Cache_Grid
{

    /**
     * Decorate status column values
     *
     * @return string
     */
    public function decorateStatus($value, $row, $column, $isExport)
    {
        $class = '';
        if (isset($this->_invalidatedTypes[$row->getId()])) {
            $cell = '<span class="grid-severity-minor"><span id="cache_type_'.$row->getId().'">'.$this->__('Invalidated').'</span></span>';
        } else {
            if ($row->getStatus()) {
                $cell = '<span class="grid-severity-notice"><span id="cache_type_'.$row->getId().'">'.$value.'</span></span>';
            } else {
                $cell = '<span class="grid-severity-critical"><span id="cache_type_'.$row->getId().'">'.$value.'</span></span>';
            }
        }
        return $cell;
    }

}
