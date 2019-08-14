<?php

class Ycc_Minify_Helper_Data extends Mage_Core_Helper_Data
{
    public function mergeFiles(array $srcFiles, $targetFile = false, $mustMerge = false, $beforeMergeCallback = null, $extensionsFilter = array())
    {
        try {
            // check whether merger is required
            $shouldMerge = $mustMerge || !$targetFile;
            if (!$shouldMerge) {
                if (!file_exists($targetFile)) {
                    $shouldMerge = true;
                } else {
                    $targetMtime = filemtime($targetFile);
                    foreach ($srcFiles as $file) {
                        if (filemtime($file) > $targetMtime) {
                            $shouldMerge = true;
                            break;
                        }
                    }
                }
            }

            // merge contents into the file
            if ($shouldMerge) {
                if ($targetFile && !is_writeable(dirname($targetFile))) {
                    // no translation intentionally
                    throw new Exception(sprintf('Path %s is not writeable.', dirname($targetFile)));
                }

                // filter by extensions
                if ($extensionsFilter) {
                    if (!is_array($extensionsFilter)) {
                        $extensionsFilter = array($extensionsFilter);
                    }
                    if (!empty($srcFiles)){
                        foreach ($srcFiles as $key => $file) {
                            $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, $extensionsFilter)) {
                                unset($srcFiles[$key]);
                            }
                        }
                    }
                }
                if (empty($srcFiles)) {
                    // no translation intentionally
                    throw new Exception('No files to compile.');
                }

                $data = '';
                foreach ($srcFiles as $file) {
                    if (!file_exists($file)) {
                        continue;
                    }
                    $contents = file_get_contents($file) . "\n";
                    if ($beforeMergeCallback && is_callable($beforeMergeCallback)) {
                        $contents = call_user_func($beforeMergeCallback, $file, $contents);
                    }
                    $data .= $contents;
                }
                if (!$data) {
                    // no translation intentionally
                    throw new Exception(sprintf("No content found in files:\n%s", implode("\n", $srcFiles)));
                }
                if ($targetFile) {
			$this->compile($targetFile,$data);
                        /*$extend = explode("." , $targetFile);
                        $tmpfile = "/tmp/".md5($targetFile). ".". $extend[count($extend)-1];
                        file_put_contents($tmpfile, $data, LOCK_EX);
                        exec("java -jar " . Mage::getBaseDir(). DS. "lib" .DS."yccminify". DS .  "yuicompressor.jar $tmpfile > $targetFile");
			*/
                        #exec("rm -rf $tmpfile");
			    #file_put_contents($targetFile, $data, LOCK_EX);
                } else {
                    return $data; // no need to write to file, just return data
                }
            }
            return true; // no need in merger or merged into file successfully
        } catch (Exception $e) {
	      Mage::logException($e);
        }
        return false;
    }

    const CSS_COMPILER_CONFIG = 'dev/css/minify_css_files';
    const JS_COMPILER_CONFIG = 'dev/js/minify_js_files';
    private function compile($targetFile, $data){
	$extend = explode("." , $targetFile);
	$ext = $extend[count($extend)-1];
	$tmpfile = "/tmp/".md5($targetFile). ".". $ext;
	if ($ext == "css"){
		$mode = Mage::getStoreConfig(self::CSS_COMPILER_CONFIG, Mage::app()->getStore()->getStoreId());
	} else {
		$mode = Mage::getStoreConfig(self::JS_COMPILER_CONFIG, Mage::app()->getStore()->getStoreId());
	}

	if (empty($mode)){
		file_put_contents($targetFile, $data, LOCK_EX);
		return;
	}

	file_put_contents($tmpfile, $data, LOCK_EX);
	if  ($mode == "1"){
		exec($this->getYUICmd(array(), $ext, $tmpfile, $targetFile));
	} elseif ($mode == "2"){
		exec($this->getClosuerCmd(array(), $ext, $tmpfile, $targetFile));
	}
	exec("rm -rf $tmpfile");
    }

    private function getYUICmd($userOptions, $type, $tmpfile, $targetFile)
    {
        $o = array_merge(
            array(
                'charset' => ''
                ,'line-break' => 5000
                ,'type' => $type
                ,'nomunge' => false
                ,'preserve-semi' => false
                ,'disable-optimizations' => false
            )
            ,$userOptions
        );

        $cmd = 'java -Xmx512m -jar ' . Mage::getBaseDir(). DS. "lib" .DS."yccminify". DS . "yuicompressor.jar"
             . " --type {$type}"
             . (preg_match('/^[a-zA-Z\\-]+$/', $o['charset'])
                ? " --charset {$o['charset']}"
                : '')
             . (is_numeric($o['line-break']) && $o['line-break'] >= 0
                ? ' --line-break ' . (int)$o['line-break']
                : '');
        if ($type === 'js') {
            foreach (array('nomunge', 'preserve-semi', 'disable-optimizations') as $opt) {
                $cmd .= $o[$opt]
                    ? " --{$opt}"
                    : '';
            }
        }

        return "$cmd $tmpfile > $targetFile";
    }

    private function getClosuerCmd($userOptions, $type, $tmpfile, $targetFile)
    {
        $o = array_merge(
            array(
                'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
                'summary_detail_level' => 0,
            )
            ,$userOptions
        );
        $cmd = 'java -Xmx512m -jar '. Mage::getBaseDir(). DS. "lib" .DS."yccminify". DS . "closurecompiler.jar";
        if ($type === 'js') {
            foreach (array('compilation_level', 'preserve-semi', 'disable-optimizations', 'warning_level', 'summary_detail_level') as $opt) {
                if (array_key_exists($opt, $o)) {
                    $cmd .= ' --' . $opt . ' ' . $o[$opt];
                }
            }
        }
        return $cmd . ' --third_party --js=' . $tmpfile . ' --js_output_file=' . $targetFile;
    }
}
?>
