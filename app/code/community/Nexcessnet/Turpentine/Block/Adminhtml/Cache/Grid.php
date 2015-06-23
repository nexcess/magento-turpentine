<?php

/**
 * User: damian.pastorini@usestrategery.com
 * Date: 08/01/14
 */

class Nexcessnet_Turpentine_Block_Adminhtml_Cache_Grid extends Mage_Adminhtml_Block_Cache_Grid
{

    /**
     * Prepare grid collection
     */
    protected function _prepareCollection()
    {
        parent::_prepareCollection();
        $collection = $this->getCollection();
        $turpentineEnabled = false;
        $fullPageEnabled = false;
        foreach ($collection as $key=>$item)
        {
            if($item->getStatus()==1 && ($item->getId()=='turpentine_pages' || $item->getId()=='turpentine_esi_blocks'))
            {
                $turpentineEnabled = true;
            }
            if($item->getStatus()==1 && $item->getId()=='full_page')
            {
                $fullPageEnabled = true;
            }
        }
        if($turpentineEnabled)
        {
            $collection->removeItemByKey('full_page');
        }
        if($fullPageEnabled)
        {
            $collection->removeItemByKey('turpentine_pages');
            $collection->removeItemByKey('turpentine_esi_blocks');
        }
        $this->setCollection($collection);
        return $this;
    }

}
