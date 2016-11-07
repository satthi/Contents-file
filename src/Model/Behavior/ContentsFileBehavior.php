<?php

namespace ContentsFile\Model\Behavior;

use Cake\ORM\Behavior;
use Cake\Event\Event;
use Cake\ORM\Entity;
use ArrayObject;
use Cake\ORM\TableRegistry;
use ContentsFile\Aws\S3;
use Cake\Utility\Security;
use Cake\I18n\Time;
use Cake\Filesystem\Folder;


class ContentsFileBehavior extends Behavior {

    private $__attachmentModel;
    
    /*
     * afterSave
     * 画像をafterSaveで保存する
     */
    public function afterSave(Event $event, Entity $entity, ArrayObject $options)
    {
        //設定値をentityから取得
        $contentsFileConfig = $entity->contentsFileConfig;
        $this->__attachmentModel = TableRegistry::get('Attachments');
        foreach ($contentsFileConfig['fields'] as $field => $field_settings){
            //contents_file_の方に入ったentityをベースに処理する
            $file_info = $entity->{'contents_file_' . $field};
            if (
                !empty($file_info) &&
                //tmp_file_nameがある=アップロードしたファイルがある
                array_key_exists('tmp_file_name', $file_info)
            ){
                $attachmentSaveData = [
                    'model' => $this->_table->alias(),
                    'model_id' => $entity->id,
                    'field_name' => $file_info['field_name'],
                    'file_name' => $file_info['file_name'],
                    'file_content_type' => $file_info['file_content_type'],
                    'file_size' => $file_info['file_size'],
                ];
                $attachmentEntity = $this->__attachmentModel->newEntity($attachmentSaveData);
                if ($field_settings['type'] == 's3') {
                    if (!$this->s3FileSave($file_info, $field_settings, $attachmentSaveData)) {
                        return false;
                    }
                } else {
                    if (!$this->fileSave($file_info, $field_settings, $attachmentSaveData)) {
                        return false;
                    }
                }
                // ここから

                //ファイルの移動

                // ここまで
                
                //元のデータがあるかfind(あれば更新にする)
                $attachmentDataCheck = $this->__attachmentModel->find('all')
                    ->where(['model' => $file_info['model']])
                    ->where(['model_id' => $entity->id])
                    ->where(['field_name' => $file_info['field_name']])
                    ->first(1);
                if (!empty($attachmentDataCheck)){
                    $attachmentEntity->id = $attachmentDataCheck->id;
                }
                if (!$this->__attachmentModel->save($attachmentEntity)) {
                    //失敗時はロールバック
                    $this->__fileRollback($contentsFileConfig, $entity->id);
                    return false;
                }
            }
        }
        
        //成功時はcommit
        $this->__fileCommit($contentsFileConfig, $entity->id);
        return true;
        
    }

    private function s3FileSave($file_info, $field_settings, $attachmentSaveData)
    {
        $S3 = new S3();
        $new_filedir = 'file/' . $attachmentSaveData['model'] . '/' . $attachmentSaveData['model_id'] . '/';
        $new_filepath = $new_filedir . $file_info['field_name'];
        $old_filepath = 'tmp/' . $file_info['tmp_file_name'];

        if (
            !$S3->move($old_filepath, $new_filepath . '/'. 'file')
        ){
            //失敗時はロールバック
            // $this->__fileRollback($contentsFileConfig, $entity->id);
            return false;
        }
        
        //リサイズディレクトリはまず削除する(これは仮に失敗してもアクセス時に復元可能なため
        $S3->delete($new_filepath . '/' . 'contents_file_resize_file');
        
        //リサイズ画像作成
        if (!empty($field_settings['resize'])){
            foreach ($field_settings['resize'] as $resize_settings){
                
                // debug($new_filepath);
                // debug($resize_settings);
                // exit;
                if (!$this->s3ImageResize($new_filepath, $resize_settings)) {
                    // $this->imageResize($new_filepath, $resize_settings)){
                    //失敗時はロールバック
                    // $this->__fileRollback($contentsFileConfig, $entity->id);
                    return false;
                }
            }
        }
        return true;
    }

    private function fileSave($file_info, $field_settings, $attachmentSaveData)
    {
        $new_filedir = $field_settings['filePath'] . $attachmentSaveData['model'] . '/' . $attachmentSaveData['model_id'] . '/';
        $new_filepath = $new_filedir . $file_info['field_name'];
        if (
            !$this->__mkdir($new_filedir, 0777, true) || 
            $this->__fileMove($field_settings['cacheTempDir'] . $file_info['tmp_file_name'] , $new_filepath)
            
        ){
            //失敗時はロールバック
            $this->__fileRollback($contentsFileConfig, $entity->id);
            return false;
        }
        
        //リサイズディレクトリはまず削除する(これは仮に失敗してもアクセス時に復元可能なため
        $this->__resizeDirRemove($new_filepath);
        
        //リサイズ画像作成
        if (!empty($field_settings['resize'])){
            foreach ($field_settings['resize'] as $resize_settings){
                if (!$this->imageResize($new_filepath, $resize_settings)){
                    //失敗時はロールバック
                    $this->__fileRollback($contentsFileConfig, $entity->id);
                    return false;
                }
            }
        }
    }
    
    /*
     * imageResize
     * 画像のリサイズ処理(外からでもたたけるようにpublicにする
     */
    public function imageResize($imagePath, $baseSize) {
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
        $imagepathinfo = $this->getPathInfo($imagePath, $baseSize);
        //resizeファイルを格納するディレクトリを作成
        if (
            !$this->__mkdir($imagepathinfo['resize_dir'], 0777, true)
        ){
            return false;
        }
        
        switch ($imagetype) {
            case IMAGETYPE_GIF:
                ImageGIF($outImage, $imagepathinfo['resize_filepath'], 0);
                break;
            case IMAGETYPE_JPEG:
                ImageJPEG($outImage, $imagepathinfo['resize_filepath'], 0);
                break;
            case IMAGETYPE_PNG:
                ImagePNG($outImage, $imagepathinfo['resize_filepath'], 0);
                break;
            default :
                return false;
        }
        
        ImageDestroy($outImage);

        return true;
    }

    public function s3ImageResize($filepath, $resize)
    {
        $imagepathinfo = $this->getPathinfo($filepath . '/file', $resize);
        $S3 = new S3();
        // Exception = 存在していない場合
        $tmp_file_name = Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss'));
        $tmpPath = TMP . $tmp_file_name;
        // ベースのファイルを取得
        $baseObject = $S3->download($imagepathinfo['dirname'] . '/file');
        $fp = fopen($tmpPath, 'w');
        fwrite($fp, $baseObject['Body']);
        fclose($fp);
        //ない場合はリサイズを実行
        if (!$this->imageResize($tmpPath, $resize)){
            //失敗時はそのままのパスを返す(画像以外の可能性あり)
            unlink($tmpPath);
            return $filepath;
        }
        $resizeFileDir = TMP . 'contents_file_resize_' . $tmp_file_name;
        $resizeFolder = new Folder($resizeFileDir);
        // 一つのはず
        $resizeImg = $resizeFolder->findRecursive()[0];
        
        // リサイズ画像をアップロード
        $S3->upload($resizeImg, $imagepathinfo['resize_filepath']);

        $resizeFolder->delete();
        unlink($tmpPath);
        return $imagepathinfo['resize_filepath'];
    }
    
    /*
     * __fileMove
     * ファイルの移動(元ファイルがいたときのことを考えてbackupファイルを作成する
     */
    private function __fileMove($tmpFile, $new_filepath){
        if (file_exists($new_filepath)){
            $pathinfo = $this->getPathInfo($new_filepath);
            //旧ファイルをバックアップとしてとっておく
            rename($new_filepath,$pathinfo['backup_filepath']);
        }
        return !rename($tmpFile , $new_filepath);
    }
    
    /*
     * __resizeDirRemove
     * リサイズディレクトリの削除
     */
    private function __resizeDirRemove($new_filepath){
        $imagepathinfo = $this->getPathInfo($new_filepath);
        $this->__recursiveRemoveDir($imagepathinfo['resize_dir']);
    }
    

    /**
     * recursiveRemoveDir
     * recursively remove directory
     *
     * @param $dir
     * @return
     * @access protected
     */
    private function __recursiveRemoveDir($dir) {
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
    
    /*
     * __fileCommit
     * 保存成功時の処理(backupファイルを削除)
     */
    private function __fileCommit($contentsFileConfig, $target_id){
        //back削除する
        foreach ($contentsFileConfig['fields'] as $field => $fieldSetting){
            $new_filedir = $fieldSetting['filePath'] . $this->_table->alias() . '/' . $target_id . '/';
            $new_filepath = $new_filedir . $field;
            $new_filepath_pathinfo = $this->getPathInfo($new_filepath);
            
            if (file_exists($new_filepath_pathinfo['backup_filepath'])){
                //旧ファイルを元に戻す
                unlink($new_filepath_pathinfo['backup_filepath']);
            }
            
        }
    }
    
    /*
     * __fileRollback
     * 保存失敗時の処理 アップロードファイルを削除しバックアップファイルを元に戻す
     */
    private function __fileRollback($contentsFileConfig, $target_id){
        //backを元に戻す
        foreach ($contentsFileConfig['fields'] as $field => $fieldSetting){
            $new_filedir = $fieldSetting['filePath'] . $this->_table->alias() . '/' . $target_id . '/';
            $new_filepath = $new_filedir . $field;
            $new_filepath_pathinfo = $this->getPathInfo($new_filepath);
            $backup_file = $new_filepath_pathinfo['backup_filepath'];
            if (file_exists($new_filepath)){
                //新規に登録したファイルを削除
                unlink($new_filepath);
                //resizeDirも削除(これはアクセス時に復元可能なため
                $this->__resizeDirRemove($new_filepath);
            }
            
            if (file_exists($new_filepath_pathinfo['backup_filepath'])){
                //旧ファイルを元に戻す
                rename($new_filepath_pathinfo['backup_filepath'], $new_filepath);
            }
            
            
        }
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
    
    /*
     * __mkdir
     * ディレクトリの作成(パーミッションの設定のため
     */
    private function __mkdir($path, $permission, $recursive){
        if (is_dir($path)){
            return true;
        }
        $oldumask = umask(0);
        $result = mkdir($path, $permission, $recursive);
        umask($oldumask);
        return $result;
    }
    
    /*
     * getPathInfo
     * 通常のpathinfoに加えてContentsFile独自のpathも一緒に設定する
     */
    public function getPathInfo($imagePath, $resize = []){
        $pathinfo = pathinfo($imagePath);
        $pathinfo['resize_dir'] = $pathinfo['dirname'] . '/contents_file_resize_' . $pathinfo['filename'];
        $pathinfo['backup_filepath'] = $pathinfo['dirname'] . '/contents_file_back_' . $pathinfo['filename'];
        //一旦ベースのパスを通しておく
        $pathinfo['resize_filepath'] = $imagePath;
        if (!empty($resize)){
            if (!isset($resize['width'])){
                $resize['width'] = 0;
            }
            if (!isset($resize['height'])){
                $resize['height'] = 0;
            }
            $pathinfo['resize_filepath'] = $pathinfo['resize_dir'] . '/' . $resize['width'] . '_' . $resize['height'];
        }
        
        return $pathinfo;
    }
    
}
