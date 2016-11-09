<?php

namespace ContentsFile\Controller;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use ContentsFile\Controller\AppController;
use ContentsFile\Controller\Traits\NormalContentsFileControllerTrait;
use ContentsFile\Controller\Traits\S3ContentsFileControllerTrait;

class ContentsFileController extends AppController
{
    use S3ContentsFileControllerTrait;
    use NormalContentsFileControllerTrait;
    private $baseModel;

    /**
     * loader
     * @author hagiwara
     */
    public function loader()
    {
        $this->autoRender = false;

        //Entityに接続して設定値を取得
        $this->baseModel = TableRegistry::get($this->request->query['model']);

        // このレベルで切り出す
        $this->{Configure::read('ContentsFile.Setting.type') . 'Loader'}();

    }

    /**
     * getFileType
     * @author hagiwara
     * @param string $ext
     */
    private function getFileType($ext)
    {
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

        if (!empty($aContentTypes[$ext])) {
            $sContentType = $aContentTypes[$ext];
        }
        return $sContentType;
    }

    /**
     * getMimeType
     * @author hagiwara
     * @param string $filename
     */
    private function getMimeType($filename)
    {
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
            $ext = strtolower(substr($filename, $pos + 1));
            if (strlen($ext)) {
                return $aContentTypes[$ext] ? $aContentTypes[$ext] : $sContentType;
            }
        }
        return $sContentType;
    }

}
