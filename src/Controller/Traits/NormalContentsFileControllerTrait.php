<?php

namespace ContentsFile\Controller\Traits;

use Cake\Core\Configure;

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

        $this->response->withHeader('Content-Length', filesize($filepath));
        if (!empty($fileExt)) {
            $fileContentType = $this->getFileType($fileExt);
        } else {
            $fileContentType = $this->getMimeType($filepath);
        }
        $this->response->withType($fileContentType);
        @ob_end_clean(); // clean
        $fp = fopen($filepath, 'r');
        $body = fread($fp, filesize($filepath));
        fclose($fp);
        $this->response->getBody()->write($body);
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
        $ext = '';
        if (Configure::read('ContentsFile.Setting.ext') === true) {
            $ext = '.' . (new \SplFileInfo($attachmentData->file_name))->getExtension();
        }
        if (Configure::read('ContentsFile.Setting.randomFile') === true && $attachmentData->file_random_path != '') {
            return Configure::read('ContentsFile.Setting.Normal.fileDir') . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->file_random_path . $ext;
        } else {
            return Configure::read('ContentsFile.Setting.Normal.fileDir') . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name . $ext;
        }
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
