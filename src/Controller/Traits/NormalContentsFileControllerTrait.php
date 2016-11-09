<?php

namespace ContentsFile\Controller\Traits;

use Cake\Core\Configure;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

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
    private function normalLoader($filename, $filepath)
    {
        $fileExt = null;
        if (preg_match('/\.([^\.]*)$/', $filename, $ext)) {
            if ($ext[1]) {
                $fileExt = strtolower($ext[1]);
            }
        }

        header('Content-Length: ' . filesize($filepath));
        if (!empty($fileExt)) {
            $fileContentType = $this->getFileType($fileExt);
            header('Content-Type: ' . $fileContentType);
        } else {
            $fileContentType = $this->getMimeType($filepath);
            header('Content-Type: ' . $fileContentType);
        }
        @ob_end_clean(); // clean
        readfile($filepath);
    }

    /**
     * normalTmpFilePath
     * 通常用のtmpのパス作成
     * @author hagiwara
     * @param string $filename
     */
    private function normalTmpFilePath($filename)
    {
        return Configure::read('ContentsFile.Setting.Normal.tmpDir') . $filename;
    }

    /**
     * normalFilePath
     * 通常用のファイルのパス作成
     * @author hagiwara
     * @param Entity $attachmentData
     */
    private function normalFilePath($attachmentData)
    {
        return Configure::read('ContentsFile.Setting.Normal.fileDir') . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name;
    }

    /**
     * normalResizeSet
     * 通常のリサイズ処理
     * @author hagiwara
     * @param string $filepath
     * @param array $resize
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
