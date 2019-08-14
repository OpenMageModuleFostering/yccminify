<?php

class Ycc_Minify_Model_System_Config_Csscompressor
{
    public function toOptionArray()
    {
        return array(
            array('value' => 0, 'label'=>Mage::helper('adminhtml')->__('Don\'t compress')),
            array('value' => 1, 'label'=>Mage::helper('adminhtml')->__('YUI Compressor')),
        );
    }
}
?>
