<?php
/*
yongcheng.ch@gmail.com
*/

require_once (Mage::getBaseDir().DS."lib" .DS."yccminify". DS .'HTML.php');

class Ycc_Minify_Model_Observer
{
	const MINIFY_CONFIG = 'dev/minifyhtml/enabled';
        public function MinifyResponse($observer){
		if (Mage::app()->getStore()->isAdmin()){
			return;
		}

                if (Mage::app()->getRequest()->isXmlHttpRequest()) { // is Ajax request
                        return;
                }

		if ("1" === Mage::getStoreConfig(self::MINIFY_CONFIG, Mage::app()->getStore()->getStoreId())){
			$response = $observer->getResponse();
			$html     = $response->getBody();
			$min = new Minify_HTML($html, $options);
			$response->setBody( $min->process() );
		}
        }
}
?>

