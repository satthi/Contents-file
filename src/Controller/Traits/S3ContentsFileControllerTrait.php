<?php
namespace ContentsFile\Controller\Traits;

use Cake\Core\Configure;
use Cake\ORM\Entity;
use ContentsFile\Aws\S3;

/**
 * S3のファイルローダー周り
 * S3ContentsFileControllerTrait
 */
trait S3ContentsFileControllerTrait
{
    /**
     * s3Loader
     * S3用のファイルローダー
     * @author hagiwara
     * @param string $filename
     * @param string $filepath
     * @return void
     */
    private function s3Loader(string $filename, string $filepath): void
    {
        // S3より該当ファイルを取得
        $S3 = new S3();
        $fileObject = $S3->download($filepath);
        $topath = Configure::read('ContentsFile.Setting.S3.workingDir') . $filename;
        $fp = fopen($topath, 'w');
        fwrite($fp, $fileObject['Body']);
        fclose($fp);

        // ファイルの出力
        $this->response = $this->response->withHeader('Content-Length', filesize($topath));
        $fileContentType = $this->getMimeType($topath);
        $this->response = $this->response->withType($fileContentType);
        @ob_end_clean(); // clean
        $fp = fopen($topath, 'r');
        $body = fread($fp, filesize($topath));
        fclose($fp);
        $this->response->getBody()->write($body);
        // サーバー上にファイルを残しておく必要がないので削除する
        unlink($topath);
    }

    /**
     * s3TmpFilePath
     * S3のtmpのパス作成
     * @author hagiwara
     * @param string $filename
     * @return string
     */
    private function s3TmpFilePath($filename): string
    {
        return Configure::read('ContentsFile.Setting.S3.tmpDir') . $filename;
    }

    /**
     * s3FilePath
     * S3のファイルのパス作成
     * @author hagiwara
     * @param Entity $attachmentData
     * @return string
     */
    private function s3FilePath(Entity $attachmentData): string
    {
        $ext = '';
        if (Configure::read('ContentsFile.Setting.ext') === true) {
            $ext = '.' . (new \SplFileInfo($attachmentData->file_name))->getExtension();
        }
        if (Configure::read('ContentsFile.Setting.randomFile') === true && $attachmentData->file_random_path != '') {
            return Configure::read('ContentsFile.Setting.S3.fileDir') . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->file_random_path . $ext;
        } else {
            return Configure::read('ContentsFile.Setting.S3.fileDir') . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name . $ext;
        }
    }


    /**
     * s3ResizeSet
     * S3のリサイズ処理
     * @author hagiwara
     * @param string $filepath
     * @param array $resize
     * @param string
     */
    private function s3ResizeSet(string $filepath, array $resize): string
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

        $S3 = new S3();
        // 落としてこれる場合は存在している
        if ($S3->fileExists($imagepathinfo['resize_filepath'])) {
            return $imagepathinfo['resize_filepath'];
        } else {
            return $this->baseModel->s3ImageResize($filepath, $resize);
        }
    }
}
