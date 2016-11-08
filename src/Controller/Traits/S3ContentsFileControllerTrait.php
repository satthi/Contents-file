<?php
namespace ContentsFile\Controller\Traits;

use Cake\Network\Exception\InternalErrorException;
use Cake\Network\Exception\NotFoundException;
use ContentsFile\Aws\S3;
use Cake\Utility\Inflector;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

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
    private function s3Loader($field_setting)
    {
        $field_name = $this->request->query['field_name'];
        if (!empty($this->request->query['tmp_file_name'])) {
            $filename = $this->request->query['tmp_file_name'];
            $filepath = 'tmp/' . $filename;
        } elseif (!empty($this->request->query['model_id'])) {
            // //表示条件をチェックする
            $check_method_name = 'contentsFileCheck' . Inflector::camelize($field_name);
            if (method_exists($this->__baseModel, $check_method_name)) {
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
            if (empty($attachmentData)) {
                throw new NotFoundException('404 error');
            }
            $filename = $attachmentData->file_name;
            $filepath = 'file/' . $attachmentData->model . '/' . $attachmentData->model_id . '/' . $attachmentData->field_name;

            //通常のセットの時のみresize設定があれば見る
            if (!empty($this->request->query['resize'])) {
                $filepath = $this->s3ResizeSet($filepath, $this->request->query['resize']);
            }

        }

        // S3より該当ファイルを取得
        $S3 = new S3();
        $fileObject = $S3->download($filepath);
        $topath = Configure::read('ContentsFile.Setting.cacheTempDir') . $filename;
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
        $imagepathinfo = $this->__baseModel->getPathinfo($filepath, $resize);

        $S3 = new S3();
        // 落としてこれる場合は存在している
        if ($S3->fileExists($imagepathinfo['resize_filepath'])) {
            return $imagepathinfo['resize_filepath'];
        } else {
            return $this->__baseModel->s3ImageResize($imagepathinfo, $resize);
        }
    }
}
