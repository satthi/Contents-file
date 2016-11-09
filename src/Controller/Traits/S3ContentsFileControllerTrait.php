<?php
namespace ContentsFile\Controller\Traits;

use Cake\Core\Configure;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
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
     */
    private function s3Loader()
    {
        $fieldName = $this->request->query['field_name'];
        if (!empty($this->request->query['tmp_file_name'])) {
            $filename = $this->request->query['tmp_file_name'];
            $filepath = Configure::read('ContentsFile.Setting.S3.tmpDir') . '/' . $filename;
        } elseif (!empty($this->request->query['model_id'])) {
            // //表示条件をチェックする
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
            $filepath = Configure::read('ContentsFile.Setting.S3.fileDir') . '/' . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name;

            //通常のセットの時のみresize設定があれば見る
            if (!empty($this->request->query['resize'])) {
                $filepath = $this->s3ResizeSet($filepath, $this->request->query['resize']);
            }
        }

        // S3より該当ファイルを取得
        $S3 = new S3();
        $fileObject = $S3->download($filepath);
        $topath = Configure::read('ContentsFile.Setting.Normal.tmpDir') . $filename;
        $fp = fopen($topath, 'w');
        fwrite($fp, $fileObject['Body']);
        fclose($fp);

        // ファイルの出力
        header('Content-Length: ' . filesize($topath));
        $fileContentType = $this->getMimeType($topath);
        header('Content-Type: ' . $fileContentType);
        @ob_end_clean(); // clean
        readfile($topath);
        // サーバー上にファイルを残しておく必要がないので削除する
        unlink($topath);
    }

    /**
     * s3ResizeSet
     * S3のリサイズ処理
     * @author hagiwara
     * @param string $filepath
     * @param array $resize
     */
    private function s3ResizeSet($filepath, $resize)
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
            return $this->baseModel->s3ImageResize($imagepathinfo, $resize);
        }
    }
}
