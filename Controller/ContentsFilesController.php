<?php
App::uses('Security', 'Utility');

class ContentsFilesController extends ContentsFileAppController {

    var $name = 'ContentsFiles';
    var $uses = array('ContentsFile.ContentsFileResize');
    var $components = array('Session');
    var $noUpdateHash = true;

    /**
     * loader
     * file loader
     *
     * @param string $model
     * @param string $model_id
     * @param string $fieldName
     * @param string $hash
     * @return
     */
    function loader($isDownload = true, $model = null, $model_id = null, $fieldName = null, $hash = null, $size = null, $fileName = null) {
        $this->layout = false;
        $this->autoRender = false;
        Configure::write('debug', 0);

        if (!$model || $model_id == null || !$fieldName) {
            throw new NotFoundException(__('Invalid access'));
            return;
        }
        
        if (Security::hash($model . $model_id . $fieldName . $this->Session->read('Filebinder.hash')) !== $hash) {
            throw new NotFoundException(__('Invalid access'));
            return;
        }

        //アクセス制限(セッションキーがあるかどうか)
        $session_info = Configure::read('contents_file_access_limit.' . $model);
            if (!empty($session_info)) {
            $deny = true;
            foreach ($session_info as $session_key) {
                if ($this->Session->check($session_key)) {
                    $deny = false;
                }
            }
            if ($deny === true) {
                throw new NotFoundException(__('Invalid access'));
            }
        }
        //!アクセス制限

        if ($size === null) {
            $size = 'default';
        }

        $this->loadModel($model);

        //model_id=0は一時ファイル表示用
        if ($model_id == 0) {
            if (!empty($this->{$model}->contentsFileField)) {
                if (array_key_exists('cacheTempDir', $this->{$model}->contentsFileField)) {
                    $tmp_file_path = $this->{$model}->contentsFileField['cacheTempDir'] . $fileName;
                } else {
                    foreach ($this->{$model}->contentsFileField as $contentsFileField) {
                        if ($contentsFileField['column'] == $fieldName) {
                            $tmp_file_path = $contentsFileField['cacheTempDir'] . $fileName;
                            break;
                        }
                    }
                }
            }
            $filePath = $this->_filePathSet($tmp_file_path, $size);
            $file_ext = null;
            if (preg_match('/\.([^\.]*)$/', $fileName, $ext)){
                if ($ext[1]){
                    $file_ext = strtolower($ext[1]);
                }
            }
        } else {
            $query = array();
            $query['recursive'] = -1;
            $query['fields'] = array('id');
            $query['conditions'] = array('id' => $model_id);
            $file = $this->{$model}->find('first', $query);
            if (empty($fileName)) {
                $fileName = $file[$model][$fieldName]['file_name'];
            }
            $fileContentType = $file[$model][$fieldName]['file_content_type'];
            $filePath = $this->_filePathSet($file[$model][$fieldName]['file_path'], $size);
            $file_ext = null;
            if (preg_match('/\.([^\.]*)$/', $file[$model][$fieldName]['file_name'], $ext)){
                if ($ext[1]){
                    $file_ext = strtolower($ext[1]);
                }
            }
        }

        if (!file_exists($filePath)) {
            throw new NotFoundException(__('Invalid access'));
            return;
        }

        // modified by s.sugimoto 2011.09.14
        if ($isDownload) {
            if (strstr(env('HTTP_USER_AGENT'), 'MSIE') || strstr(env('HTTP_USER_AGENT'), 'Trident') || strstr(env('HTTP_USER_AGENT'), 'Edge')) {
                $fileName = mb_convert_encoding($fileName, "SJIS", "UTF-8");
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
            }
        }

        header('Content-Length: ' . filesize($filePath));
        if(!empty($file_ext)){
            $fileContentType = $this->getFileType($file_ext);
            header('Content-Type: ' . $fileContentType);
        } else if (!empty($fileContentType)) {
            header('Content-Type: ' . $fileContentType);
        } else if (class_exists('FInfo')) {
            $info = new FInfo(FILEINFO_MIME_TYPE);
            $fileContentType = $info->file($filePath);
            header('Content-Type: ' . $fileContentType);
        } else if (function_exists('mime_content_type')) {
            $fileContentType = mime_content_type($filePath);
            header('Content-Type: ' . $fileContentType);
        }else{
            $fileContentType = $this->getMimeType($filePath);
            header('Content-Type: ' . $fileContentType);
        }
        @ob_end_clean(); // clean
        readfile($filePath);
    }
    
    function getFileType($ext){
        $aContentTypes = array(
        'txt'=>'text/plain',
        'htm'=>'text/html',
        'html'=>'text/html',
        'jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg',
        'gif'=>'image/gif',
        'png'=>'image/png',
        'bmp'=>'image/x-bmp',
        'ai'=>'application/postscript',
        'psd'=>'image/x-photoshop',
        'eps'=>'application/postscript',
        'pdf'=>'application/pdf',
        'swf'=>'application/x-shockwave-flash',
        'lzh'=>'application/x-lha-compressed',
        'zip'=>'application/x-zip-compressed',
        'sit'=>'application/x-stuffit'
    ); 
        $sContentType = 'application/octet-stream';
        
        if (!empty($aContentTypes[$ext])){
            $sContentType = $aContentTypes[$ext];
        }
        return $sContentType;
    }
    	
    
    function getMimeType($filename) {
        $aContentTypes = array(
        'txt'=>'text/plain',
        'htm'=>'text/html',
        'html'=>'text/html',
        'jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg',
        'gif'=>'image/gif',
        'png'=>'image/png',
        'bmp'=>'image/x-bmp',
        'ai'=>'application/postscript',
        'psd'=>'image/x-photoshop',
        'eps'=>'application/postscript',
        'pdf'=>'application/pdf',
        'swf'=>'application/x-shockwave-flash',
        'lzh'=>'application/x-lha-compressed',
        'zip'=>'application/x-zip-compressed',
        'sit'=>'application/x-stuffit'
    ); 
        $sContentType = 'application/octet-stream';
        
        if (($pos = strrpos($filename, ".")) !== false) {
            // 拡張子がある場合
            $ext = strtolower(substr($filename, $pos+1));
            if (strlen($ext)) {
                return $aContentTypes[$ext]?$aContentTypes[$ext]:$sContentType;
            }
        }
        return $sContentType;
    } 

    /*
     * _filePathSet
     * 
     * 画像パスの修正
     */
    private function _filePathSet($file_path, $file_size) {
        if ($file_size == 'default') {
            return $file_path;
        }
        $separate_file_path = explode('/', $file_path);
        $separate_file_path[count($separate_file_path) - 1] = $file_size . '_' . $separate_file_path[count($separate_file_path) - 1];
        $renew_path = '';
        foreach ($separate_file_path as $v) {
            if (!empty($v)) {
                $renew_path .= '/';
                $renew_path .= $v;
            }
        }
        //元画像があって、リサイズ画像がない場合、リサイズをする
        if (!file_exists($renew_path) && file_exists($file_path)){
            $size_setting = explode('_', $file_size);
            if (count($size_setting) >= 2){
                $this->ContentsFileResize->resizeImg($file_path,array('width' => $size_setting[0],'height' => $size_setting[1],'type' => $size_setting[2]));
            }
        }
        return $renew_path;
    }

}
