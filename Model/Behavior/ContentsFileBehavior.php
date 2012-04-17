<?php

App::uses('ContentsFileAttachment', 'ContentsFile.Model');

class ContentsFileBehavior extends ModelBehavior {

    var $settings = array();
    var $runtime = array();

    /**
     * setup
     *
     * @param &$model
     * @param $settings
     */
    function setup(&$model, $settings = array()) {
        $defaults = array(
            'cache_temp_dir' => TMP . 'cache/files/'
        );


        // Default settings
        $this->settings[$model->alias] = Set::merge($defaults, $settings);

        $this->ContentsFileAttachment = new ContentsFileAttachment();
    }

    /*
     * afterSave
     */

    function afterSave(&$model, $created) {
        $modelName = $model->alias;
        if (!empty($model->contentsFileField)) {
            //一つか、配列で複数持っているかで分岐
            if (array_key_exists('column', $model->contentsFileField)) {
                $this->_fileSave($model, $model->contentsFileField);
            } else {
                foreach ($model->contentsFileField as $field) {
                    $this->_fileSave($model, $field);
                }
            }
        }
    }

    /*
     * afterFind
     */

    function afterFind(&$model, $result) {
        if (empty($result)) {
            return $result;
        }
        if (array_key_exists($model->alias, $result)) {
            $result = $this->_dataset(&$model, $data);
        } else {
            foreach ($result as $k => $v) {
                if (array_key_exists($model->alias, $v)) {
                    $result[$k] = $this->_dataset(&$model, $v);
                }
            }
        }
        return $result;
    }

    /*
     * _dataset
     */

    private function _dataset(&$model, $data) {
        if (!empty($model->contentsFileField)) {
            if (array_key_exists('column', $model->contentsFileField)) {
                $data[$model->alias][$model->contentsFileField['column']] = $this->_fileDataSet($model, $model->contentsFileField, $data);
            } else {
                foreach ($model->contentsFileField as $field) {
                    $data[$model->alias][$field['column']] = $this->_fileDataSet($model, $field, $data);
                }
            }
        }
        return $data;
    }

    /*
     * _fileDataSet
     */

    private function _fileDataSet(&$model, $field, $data) {
        $query = array();
        //必要ファイル情報を取得
        $query['conditions'] = array(
            'ContentsFileAttachment.model_id' => $data[$model->alias]['id'],
            'ContentsFileAttachment.model' => $model->alias,
            'ContentsFileAttachment.field_name' => $field['column']
        );
        $query['fields'] = array(
            'ContentsFileAttachment.model',
            'ContentsFileAttachment.model_id',
            'ContentsFileAttachment.field_name',
            'ContentsFileAttachment.file_name',
            'ContentsFileAttachment.file_size',
            'ContentsFileAttachment.file_content_type',
        );
        $file_data = $this->ContentsFileAttachment->find('first', $query);
        if (empty($file_data)) {
            return array();
        }
        $file_data['ContentsFileAttachment']['file_path'] = $this->_filePathSet($file_data['ContentsFileAttachment'], $field['filePath']);
        return $file_data['ContentsFileAttachment'];
    }

    /*
     * _filePathSet
     */

    private function _filePathSet($file_data, $file_path) {
        $path = $file_path . $file_data['model'] . '/' . $file_data['model_id'] . '/' . $file_data['field_name'] . '/' . $file_data['file_name'];
        return $path;
    }

    /*
     * _fileSave
     */

    private function _fileSave(&$model, $field) {
        $model_name = $model->alias;
        $field_name = $field['column'];
        if (!isset($model->data[$model->alias][$field_name])){
            return;
        }
        if (array_key_exists('delete_' . $field_name , $model->data[$model->alias]) && $model->data[$model->alias]['delete_' . $field_name] == true){
            //ファイル削除のほうへ
            if (!empty($model->data[$model->alias][$field_name]['cache_tmp_name'])){
                @unlink($field['cacheTempDir'] . $model->data[$model_name][$field_name]['cache_tmp_name']);
            }
            $this->_fileDelete($model, $field);
            return;
        }
        if (!empty($model->data[$model->alias][$field['column']]['file_path'])) {
            return;
        }

        if (empty($model->data[$model->alias]['id'])){
            $model_id = $model->getLastInsertId();
        }else{
            $model_id = $model->data[$model->alias]['id'];
            $this->_fileDelete($model,$field);
        }
        
        if (empty($model->data[$model_name][$field_name])) {
            return;
        }
        $file_name = $model->data[$model_name][$field_name]['name'];
        $file_content_type = $model->data[$model_name][$field_name]['type'];
        $file_size = $model->data[$model_name][$field_name]['size'];
        //DBデータの保存
        $db_save = array(
            'model' => $model_name,
            'model_id' => $model_id,
            'field_name' => $field_name,
            'file_name' => $file_name,
            'file_content_type' => $file_content_type,
            'file_size' => $file_size
        );
        $this->ContentsFileAttachment->create();
        if (!$this->ContentsFileAttachment->save($db_save)) {
            return false;
        }
        //!DBデータの保存
        //ファイルの保存
        $saveDir = $field['filePath'] . $model_name . '/' . $model_id . '/' . $field_name . '/';
        if (!file_exists($saveDir)) {
            mkdir($saveDir, 0755, true);
        }
        if (!@rename($field['cacheTempDir'] . $model->data[$model_name][$field_name]['cache_tmp_name'], $saveDir . $model->data[$model_name][$field_name]['name'])) {
            return false;
        }
        //!ファイルの保存
        //画像のリサイズ
        if (!empty($field['resize'])) {
            if (array_key_exists('width', $field['resize']) || array_key_exists('height', $field['resize'])) {
                $this->_uploadImageResize($model, $field_name, $field['resize']);
            } else {
                foreach ($field['resize'] as $resize) {
                    $this->_uploadImageResize($model, $field_name, $resize);
                }
            }
        }
        //!画像のリサイズ
    }
    
    /*
     * _fileDelete
     * 
     * ファイル削除
     */
    function _fileDelete($model, $field){
        $model_name = $model->alias;
        if (!empty($model->data[$model_name]['id'])){
            $model_id = $model->data[$model_name]['id'];
            //edit時
            $this->ContentsFileAttachment->deleteAll(array('model' => $model_name,'model_id' => $model_id,'field_name' => $field['column']));
            $fileDir = $field['filePath'] . $model_name . '/' . $model_id . '/' . $field['column'] . '/';
            if (file_exists($fileDir)){
                $this->_recursiveRemoveDir($fileDir);
            }
        }
    }

    
    /**
     * recursiveRemoveDir
     * recursively remove directory
     *
     * @param $dir
     * @return
     * @access protected
     */
    private function _recursiveRemoveDir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir")
                        $this->_recursiveRemoveDir($dir . "/" . $object); else
                        @unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            return rmdir($dir);
        }
        return false;
    }

    /**
     * アップロードした画像のリサイズ
     * _uploadImageResize
     *
     * @access private
     */
    private function _uploadImageResize($model, $field, $resize_list) {
        $data = $model->data;
        $Model = $model->alias;
        if (!empty($data[$Model]['id'])){
            $model_id = $data[$Model]['id'];
        }else{
            $model_id = $model->getLastInsertId();
        }
        //画像データがないときは特に何もしない。
        if (empty($data[$Model][$field])) {
            return true;
        }
        // 画像リサイズ
        $query = array();
        $query['conditions'] = array(
            $Model . '.id' => $model_id
        );
        $query['contain'] = false;
        $data = $model->find('first', $query);
        //画像がない場合はスルー
        if (empty($data[$Model][$field])) {
            return true;
        }
        if (!$this->resizeImg($model,$data[$Model][$field]['file_path'], $resize_list)) {
            // リサイズに失敗したらログに記録
            return false;
        }
        return true;
    }

    /*
     * resizeimg
     */

    public function resizeImg(&$model, $imagePath, $resizeSet) {
        $resize_flag = true;
        if (!empty($resizeSet)) {
            //widthやheightのキーがあるかどうかで1層かそれ以上か判定
            if (array_key_exists('width', $resizeSet) || array_key_exists('height', $resizeSet)) {
                if (!$this->_imageResize($imagePath, $resizeSet)) {
                    $resize_flag = false;
                }
            } else {
                foreach ($resizeSet as $size) {
                    if (!$this->_imageResize($imagePath, $size)) {
                        $resize_flag = false;
                    }
                }
            }
        }
        return $resize_flag;
    }

    /*
     * _imageResize
     */

    function _imageResize($imagePath, $baseSize) {
        if (file_exists($imagePath) === false) {
            return false;
        }

        $imagetype = exif_imagetype($imagePath);
        if ($imagetype === false) {
            return false;
        }

        // 画像読み込み
        $image = false;

        switch ($imagetype) {
            case IMAGETYPE_GIF:
                $image = ImageCreateFromGIF($imagePath);
                break;
            case IMAGETYPE_JPEG:
                $image = ImageCreateFromJPEG($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = ImageCreateFromPNG($imagePath);
                break;
            default :
                return false;
        }

        if (!$image) {
            // 画像の読み込み失敗
            return false;
        }

        // 画像の縦横サイズを取得
        $sizeX = ImageSX($image);
        $sizeY = ImageSY($image);
        // リサイズ後のサイズ
        $reSizeX = 0;
        $reSizeY = 0;
        $mag = 1;
        if (!array_key_exists('height', $baseSize)) {
            $baseSize['height'] = 0;
        }
        if (!array_key_exists('width', $baseSize)) {
            $baseSize['width'] = 0;
        }

        if (empty($baseSize['width']) || !empty($baseSize['height']) && $sizeX * $baseSize['height'] < $sizeY * $baseSize['width']) {
            // 縦基準
            $diffSizeY = $sizeY - $baseSize['height'];
            $mag = $baseSize['height'] / $sizeY;
            $reSizeY = $baseSize['height'];
            $reSizeX = $sizeX * $mag;
        } else {
            // 横基準
            $diffSizeX = $sizeX - $baseSize['width'];
            $mag = $baseSize['width'] / $sizeX;
            $reSizeX = $baseSize['width'];
            $reSizeY = $sizeY * $mag;
        }

        // サイズ変更後の画像データを生成
        $outImage = ImageCreateTrueColor($reSizeX, $reSizeY);
        if (!$outImage) {
            // リサイズ後の画像作成失敗
            return false;
        }

        //透過GIF.PNG対策
        $this->setTPinfo($image, $sizeX, $sizeY);


        // 画像で使用する色を透過度を指定して作成
        $bgcolor = imagecolorallocatealpha($outImage, @$this->tp["red"], @$this->tp["green"], @$this->tp["blue"], @$this->tp["alpha"]);

        // 塗り潰す
        imagefill($outImage, 0, 0, $bgcolor);
        // 透明色を定義
        imagecolortransparent($outImage, $bgcolor);
        //!透過GIF.PNG対策
        // 画像リサイズ
        $ret = imagecopyresampled($outImage, $image, 0, 0, 0, 0, $reSizeX, $reSizeY, $sizeX, $sizeY);

        if ($ret === false) {
            // リサイズ失敗
            return false;
        }

        ImageDestroy($image);

        // 画像保存
        //保存パスを変更
        //一度'/'で分解
        $imagePathExplode = explode('/', $imagePath);
        //rename
        $imagePathExplode[count($imagePathExplode) - 1] = $baseSize['width'] . '_' . $baseSize['height'] . '_' . $imagePathExplode[count($imagePathExplode) - 1];
        $renameImagePath = '';
        //分解したものを再結合
        if (!empty($imagePathExplode)) {
            foreach ($imagePathExplode as $imagePathEx) {
                //もともとのディレクトリパスの先頭が'/'なので0番目のカラムは空。
                //なのでそこは無視して残りすべて結合させる。
                if ($imagePathEx != '') {
                    $renameImagePath .= '/';
                    $renameImagePath .= $imagePathEx;
                }
            }
        }
        ImageJPEG($outImage, $renameImagePath, 100);
        ImageDestroy($outImage);

        return true;
    }

    /**
     * setTPinfo
     *  透過GIFか否か？および透過GIF情報セット
     *
     *   透過GIFである場合
     *   プロパティ$tpに透過GIF情報がセットされる
     *
     *     $tp["red"]   = 赤コンポーネントの値
     *     $tp["green"] = 緑コンポーネントの値
     *     $tp["blue"]  = 青コンポーネントの値
     *     $tp["alpha"] = 透過度(0から127/0は完全に不透明な状態/127は完全に透明な状態)
     *
     * @access private
     * @param  resource $src 画像リソース
     * @param  int   $w  対象画像幅(px)
     * @param  int   $h  対象画像高(px)
     * @return boolean
     */
    private function setTPinfo($src, $w, $h) {

        for ($sx = 0; $sx < $w; $sx++) {
            for ($sy = 0; $sy < $h; $sy++) {
                $rgb = imagecolorat($src, $sx, $sy);
                $idx = imagecolorsforindex($src, $rgb);
                if ($idx["alpha"] !== 0) {
                    $tp = $idx;
                    break;
                }
            }
            if (!isset($tp) || $tp !== null)
                break;
        }
        // 透過GIF
        if (isset($tp) && is_array($tp)) {
            $this->tp = $tp;
            return true;
        }
        // 透過GIFではない
        return false;
    }

}

