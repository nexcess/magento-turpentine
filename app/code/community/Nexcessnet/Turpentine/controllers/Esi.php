<?php

class Nexcessnet_Turpentine_EsiController extends Mage_Core_Controller_Front_Action {
    public function indexAction() {
        return $this->getBlockAction();
    }

    public function getBlockAction() {
        $blockDataId = $this->getRequest()->getParam( 'blockDataId' );
        $cache = Mage::app()->getCache();
        if( $blockData = unserialize( $cache->load( $blockDataId ) ) ) {
            if( $registryData = $blockData->getRegistry() ) {
                foreach( $registryData as $registryItem ) {
                    if( $registryItem['key'] ) {
                        Mage::register( $registryItem['key'], $registryItem['content'] );
                    }
                }
            }
        } else {
            //block data not in the cache, figure out how to regenerate and cache it
        }
        $layout = Mage::getSingleton( 'core/layout' );
        $design = Mage::getSingleton( 'core/design_package' )
            ->setPackageName( $blockData->getDesignPackage() )
            ->setTheme( $blockData->getDesignTheme() );
        $layoutXml = $layout->getUpdate()->getFileLayoutUpdatesXml(
            $design->getArea(),
            $design->getPackageName(),
            $design->getTheme( 'layout' ),
            $blockData->getStoreId() );

        $handleNames = $layoutXml->xpath( sprintf(
            '//block[@name=\'%s\']/ancestor::node()[last()-2]',
            $blockData->getNameInLayout() ) );
        foreach( $handles as $handle ) {
            $handleName = $handle->getName();
            $layout->getUpdate()->addHandle( $handleName );
            $layout->getUpdate()->load();
            $layout->generateXml();
            $layout->generateBlocks();

            if( $block = $layout->getBlock( $blockData->getNameInLayout() ) ) {
                $block->setEsi( false );
                $this->getResponse()->setBody( $block->toHtml() );
                break;
            }
            //is this line really needed?
            Mage::app()->removeCache( $layout->getUpdate()->getCacheId() );
            $layout->getUpdate()->removeHandle( $handleName );
            $layout->getUpdate()->resetUpdates();
        }
    }

    public function getMessagesAction() {
        $responseHtml = '';
        foreach( array( 'catalog/session', 'checkout/session' ) as $className ) {
            if( $session = Mage::getSingleton( $className ) ) {
                $this->loadLayout();
                $messageBlock = $this->getLayout()->getMessagesBlock();
                $messageBlock->addMessages( $session->getMessages( true ) );

                $messageBlock->setEsi( false );
                if( $messageHtml = $messageBlock->toHtml() ) {
                    //set no cache flag
                    $responseHtml .= $messageHtml;
                }
            }
        }
        $this->getResponse()->setBody( $responseHtml );
    }
}
