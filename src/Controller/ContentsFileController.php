<?php

namespace ContentsFile\Controller;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Network\Exception\NotFoundException;
use ContentsFile\Controller\AppController;
use ContentsFile\Controller\Traits\NormalContentsFileControllerTrait;
use ContentsFile\Controller\Traits\S3ContentsFileControllerTrait;

class ContentsFileController extends AppController
{
    use S3ContentsFileControllerTrait;
    use NormalContentsFileControllerTrait;
    private $baseModel;

    /**
     * initialize
     * Configureの最後のスラッシュの設定
     */
    public function initialize()
    {
        parent::initialize();
        // /が最後についていない場合はつける
        if (!preg_match('#/$#', Configure::read('ContentsFile.Setting.Normal.tmpDir'))) {
            Configure::write('ContentsFile.Setting.Normal.tmpDir', Configure::read('ContentsFile.Setting.Normal.tmpDir') . '/');
        }
        if (!preg_match('#/$#', Configure::read('ContentsFile.Setting.Normal.fileDir'))) {
            Configure::write('ContentsFile.Setting.Normal.fileDir', Configure::read('ContentsFile.Setting.Normal.fileDir') . '/');
        }
        if (!preg_match('#/$#', Configure::read('ContentsFile.Setting.S3.tmpDir'))) {
            Configure::write('ContentsFile.Setting.S3.tmpDir', Configure::read('ContentsFile.Setting.S3.tmpDir') . '/');
        }
        if (!preg_match('#/$#', Configure::read('ContentsFile.Setting.S3.fileDir'))) {
            Configure::write('ContentsFile.Setting.S3.fileDir', Configure::read('ContentsFile.Setting.S3.fileDir') . '/');
        }
        if (!preg_match('#/$#', Configure::read('ContentsFile.Setting.S3.workingDir'))) {
            Configure::write('ContentsFile.Setting.S3.workingDir', Configure::read('ContentsFile.Setting.S3.workingDir'). '/');
        }
    }

    /**
     * loader
     * @author hagiwara
     */
    public function loader()
    {
        $this->autoRender = false;

        // 必要なパラメータがない場合はエラー
        if (
            empty($this->request->getQuery('model')) ||
            empty($this->request->getQuery('field_name'))
        ) {
            throw new NotFoundException('404 error');
        }

        //Entityに接続して設定値を取得
        $this->baseModel = TableRegistry::getTableLocator()->get($this->request->getQuery('model'));

        // このレベルで切り出す
        $fieldName = $this->request->getQuery('field_name');
        $filename = '';
        if (!empty($this->request->getQuery('tmp_file_name'))) {
            $filename = $this->request->getQuery('tmp_file_name');
            $filepath = $this->{Configure::read('ContentsFile.Setting.type') . 'TmpFilePath'}($filename);
            Configure::read('ContentsFile.Setting.Normal.tmpDir') . $filename;
        } elseif (!empty($this->request->getQuery('model_id'))) {
            //表示条件をチェックする
            $checkMethodName = 'contentsFileCheck' . Inflector::camelize($fieldName);
            if (method_exists($this->baseModel, $checkMethodName)) {
                //エラーなどの処理はTableに任せる
                $this->baseModel->{$checkMethodName}($this->request->getQuery('model_id'));
            }
            //attachementからデータを取得
            $attachmentModel = TableRegistry::getTableLocator()->get('Attachments');
            $attachmentData = $attachmentModel->find('all')
                ->where(['model' => $this->request->getQuery('model')])
                ->where(['model_id' => $this->request->getQuery('model_id')])
                ->where(['field_name' => $this->request->getQuery('field_name')])
                ->first()
            ;
            if (empty($attachmentData)) {
                throw new NotFoundException('404 error');
            }
            $filename = $attachmentData->file_name;
            $filepath = $this->{Configure::read('ContentsFile.Setting.type') . 'FilePath'}($attachmentData);

            //通常のセットの時のみresize設定があれば見る
            if (!empty($this->request->getQuery('resize'))) {
                $filepath = $this->{Configure::read('ContentsFile.Setting.type') . 'ResizeSet'}($filepath, $this->request->getQuery('resize'));
            }
        }

        $this->fileDownloadHeader($filename);
        $this->{Configure::read('ContentsFile.Setting.type') . 'Loader'}($filename, $filepath);
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
            'sit'=>'application/x-stuffit',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
            'sit'=>'application/x-stuffit',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
        $sContentType = 'application/octet-stream';

        if (($pos = strrpos($filename, ".")) !== false) {
            // 拡張子がある場合
            $ext = strtolower(substr($filename, $pos + 1));
            if (strlen($ext)) {
                return array_key_exists($ext, $aContentTypes) ? $aContentTypes[$ext] : $sContentType;
            }
        }
        return $sContentType;
    }

    /**
     * fileDownloadHeader
     * @author hagiwara
     * @param string $filename
     */
    private function fileDownloadHeader($filename)
    {
        // loaderよりダウンロードするかどうか
        if (!empty($this->request->getQuery('download')) && $this->request->getQuery('download') == true) {
            // IE/Edge対応
            if (strstr(env('HTTP_USER_AGENT'), 'MSIE') || strstr(env('HTTP_USER_AGENT'), 'Trident') || strstr(env('HTTP_USER_AGENT'), 'Edge')) {
                $filename = rawurlencode($filename);
            }
            $this->response->header('Content-Disposition', 'attachment;filename="' . $filename . '"');
        }
    }
}
