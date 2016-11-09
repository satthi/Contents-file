<?php

namespace ContentsFile\Controller\Traits;

use Cake\Core\Configure;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

/**
 * 通常のファイルローダー周り
 * NormalContentsFileControllerTrait
 */
trait NormalContentsFileControllerTrait
{
    /**
     * normalLoader
     * 通常のローダー
     * @author hagiwara
     */
    private function normalLoader()
    {
        $fieldName = $this->request->query['field_name'];
        if (!empty($this->request->query['tmp_file_name'])) {
            $filename = $this->request->query['tmp_file_name'];
            $filepath = Configure::read('ContentsFile.Setting.cacheTempDir') . $filename;
        } elseif (!empty($this->request->query['model_id'])) {
            //表示条件をチェックする
            $checkMethodName = 'contentsFileCheck' . Inflector::camelize($fieldName);
            if (method_exists($this->baseModel, $checkMethodName)) {
                //エラーなどの処理はTableに任せる
                $this->baseModel->{$checkMethodName}($this->request->query['model_id']);
            }
            //attachementからデータを取得
            $attachmentModel = TableRegistry::get('Attachments');
            $attachmentData = $attachmentModel->find('all')
                ->where(['model' => $this->request->query['model']])
                ->where(['model_id' => $this->request->query['model_id']])
                ->where(['field_name' => $this->request->query['field_name']])
                ->first()
            ;
            if (empty($attachmentData)) {
                throw new NotFoundException('404 error');
            }
            $filename = $attachmentData->file_name;
            $filepath = Configure::read('ContentsFile.Setting.filePath') . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name;

            //通常のセットの時のみresize設定があれば見る
            if (!empty($this->request->query['resize'])) {
                $filepath = $this->normalResizeSet($filepath, $this->request->query['resize']);
            }
        }

        $fileExt = null;
        if (preg_match('/\.([^\.]*)$/', $filename, $ext)) {
            if ($ext[1]) {
                $fileExt = strtolower($ext[1]);
            }
        }

        $file = $filepath;

        header('Content-Length: ' . filesize($file));
        if(!empty($fileExt)) {
            $fileContentType = $this->getFileType($fileExt);
            header('Content-Type: ' . $fileContentType);
        } else {
            $fileContentType = $this->getMimeType($file);
            header('Content-Type: ' . $fileContentType);
        }
        @ob_end_clean(); // clean
        readfile($file);
    }

    /**
     * normalResizeSet
     * 通常のリサイズ処理
     * @author hagiwara
     */
    private function normalResizeSet($filepath, $resize)
    {
        if (empty($resize['width'])) {
            $resize['width'] = 0;
        }
        if (empty($resize['height'])) {
            $resize['height'] = 0;
        }
        //両方ゼロの場合はそのまま返す
        if ($resize['width'] == 0 && $resize['height'] == 0) {
            return $filepath;
        }
        $imagepathinfo = $this->baseModel->getPathinfo($filepath, $resize);

        //ファイルの存在チェック
        if (file_exists($imagepathinfo['resize_filepath'])) {
            return $imagepathinfo['resize_filepath'];
        }

        //ない場合はリサイズを実行
        if (!$this->baseModel->imageResize($filepath, $resize)) {
            //失敗時はそのままのパスを返す(画像以外の可能性あり)
            return $filepath;
        }
        return $imagepathinfo['resize_filepath'];
    }
}
