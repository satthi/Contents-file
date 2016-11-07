<?php
namespace ContentsFile\Controller;

use ContentsFile\Controller\AppController;
use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use ContentsFile\Aws\S3;


class ContentsFileController extends AppController
{
    private $__baseModel;
    private $__attachmentModel;
    
    public function loader(){
        $this->autoRender = false;

        //Entityに接続して設定値を取得
        $this->__baseModel = TableRegistry::get($this->request->query['model']);
        $entity = $this->__baseModel->newEntity();
        $field_name = $this->request->query['field_name'];

        $contentsFileConfig = $entity->contentsFileConfig;
        
        // このレベルで切り出す
        if ($contentsFileConfig['fields'][$field_name]['type'] == 's3') {
            $this->s3Loader($contentsFileConfig['fields'][$field_name]);
        } else {
            $this->normalLoader($contentsFileConfig);
        }
        
    }

    private function s3Loader($field_setting)
    {
        $field_name = $this->request->query['field_name'];
        if (!empty($this->request->query['tmp_file_name'])){
            $filename = $this->request->query['tmp_file_name'];
            $filepath = 'tmp/' . $filename;
        } elseif (!empty($this->request->query['model_id'])){
            // //表示条件をチェックする
            $check_method_name = 'contentsFileCheck' . Inflector::camelize($field_name);
            if (method_exists($this->__baseModel, $check_method_name)){
                //エラーなどの処理はTableに任せる
                $this->__baseModel->{$check_method_name}($this->request->query['model_id']);
            }
            //attachementからデータを取得
            $this->__attachmentModel = TableRegistry::get('Attachments');
            $attachmentData = $this->__attachmentModel->find('all')
                ->where(['model' => $this->request->query['model']])
                ->where(['model_id' => $this->request->query['model_id']])
                ->where(['field_name' => $this->request->query['field_name']])
                ->first()
            ;
            if (empty($attachmentData)){
                //404
            }
            $filename = $attachmentData->file_name;
            $filepath = 'file/' . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name . '/file';
            
            //通常のセットの時のみresize設定があれば見る
            if (!empty($this->request->query['resize'])){
                $filepath = $this->__s3ResizeSet($filepath, $this->request->query['resize']);
            }
        
        }
        
        $S3 = new S3();
        $fileObject = $S3->download($filepath);
        $topath = $field_setting['cacheTempDir'] . $filename;
        // 画像をファイルに書き込みます。
        $fp = fopen($topath, 'w');
        fwrite($fp, $fileObject['Body']);
        fclose($fp);

        $file_ext = null;
        if (preg_match('/\.([^\.]*)$/', $topath, $ext)){
            if ($ext[1]){
                $file_ext = strtolower($ext[1]);
            }
        }

        header('Content-Length: ' . filesize($topath));
        if(!empty($file_ext)){
            $fileContentType = $this->getFileType($file_ext);
            header('Content-Type: ' . $fileContentType);
        }else{
            $fileContentType = $this->getMimeType($topath);
            header('Content-Type: ' . $fileContentType);
        }
        @ob_end_clean(); // clean
        readfile($topath);
        unlink($topath);

    }

    private function normalLoader()
    {
        $field_name = $this->request->query['field_name'];
        if (!empty($this->request->query['tmp_file_name'])){
            $filename = $this->request->query['tmp_file_name'];
            $filepath = $contentsFileConfig['fields'][$field_name]['cacheTempDir'] . $filename;
        } elseif (!empty($this->request->query['model_id'])){
            //表示条件をチェックする
            $check_method_name = 'contentsFileCheck' . Inflector::camelize($field_name);
            if (method_exists($this->__baseModel, $check_method_name)){
                //エラーなどの処理はTableに任せる
                $this->__baseModel->{$check_method_name}($this->request->query['model_id']);
            }
            //attachementからデータを取得
            $this->__attachmentModel = TableRegistry::get('Attachments');
            $attachmentData = $this->__attachmentModel->find('all')
                ->where(['model' => $this->request->query['model']])
                ->where(['model_id' => $this->request->query['model_id']])
                ->where(['field_name' => $this->request->query['field_name']])
                ->first()
            ;
            if (empty($attachmentData)){
                //404
            }
            $filename = $attachmentData->file_name;
            $filepath = $contentsFileConfig['fields'][$field_name]['filePath'] . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name;
            
            //通常のセットの時のみresize設定があれば見る
            if (!empty($this->request->query['resize'])){
                $filepath = $this->__resizeSet($filepath, $this->request->query['resize']);
            }
        
        }
        
        $file_ext = null;
        if (preg_match('/\.([^\.]*)$/', $filename, $ext)){
            if ($ext[1]){
                $file_ext = strtolower($ext[1]);
            }
        }
        
        
        $file = $filepath;

        header('Content-Length: ' . filesize($file));
        if(!empty($file_ext)){
            $fileContentType = $this->getFileType($file_ext);
            header('Content-Type: ' . $fileContentType);
        }else{
            $fileContentType = $this->getMimeType($file);
            header('Content-Type: ' . $fileContentType);
        }
        @ob_end_clean(); // clean
        readfile($file);

    }
    
    private function getFileType($ext){
        $aContentTypes = [
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
    ]; 
        $sContentType = 'application/octet-stream';
        
        if (!empty($aContentTypes[$ext])){
            $sContentType = $aContentTypes[$ext];
        }
        return $sContentType;
    }
    
    private function getMimeType($filename) {
        $aContentTypes = [
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
    ]; 
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
    
    private function __resizeSet($filepath, $resize){
        if (empty($resize['width'])){
            $resize['width'] = 0;
        }
        if (empty($resize['height'])){
            $resize['height'] = 0;
        }
        //両方ゼロの場合はそのまま返す
        if ($resize['width'] == 0 && $resize['height'] == 0){
            return $filepath;
        }
        $imagepathinfo = $this->__baseModel->getPathinfo($filepath, $resize);
        
        //ファイルの存在チェック
        if (file_exists($imagepathinfo['resize_filepath'])){
            return $imagepathinfo['resize_filepath'];
        }
        
        //ない場合はリサイズを実行
        if (!$this->__baseModel->imageResize($filepath, $resize)){
            //失敗時はそのままのパスを返す(画像以外の可能性あり)
            return $filepath;
        }
        return $imagepathinfo['resize_filepath'];
    }
    
    private function __s3ResizeSet($filepath, $resize){
        if (empty($resize['width'])){
            $resize['width'] = 0;
        }
        if (empty($resize['height'])){
            $resize['height'] = 0;
        }
        //両方ゼロの場合はそのまま返す
        if ($resize['width'] == 0 && $resize['height'] == 0){
            return $filepath;
        }
        $imagepathinfo = $this->__baseModel->getPathinfo($filepath, $resize);
        
        //ファイルの存在チェック
        try {
            $S3 = new S3();
            // 落としてこれる場合は存在している
            $resizeObject = $S3->download($imagepathinfo['resize_filepath']);
            return $imagepathinfo['resize_filepath'];
        } catch (\Exception $e) {
            return $this->__baseModel->s3ImageResize($imagepathinfo, $resize);

        }
    }

}
