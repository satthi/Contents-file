<?php

namespace ContentsFile\Model\Behavior\Traits;

use ContentsFile\Aws\S3;
use Cake\Utility\Security;
use Cake\Core\Configure;
use Cake\I18n\Time;
use Cake\Filesystem\Folder;
use Cake\ORM\TableRegistry;

/**
 * S3ContentsFileBehaviorTrait
 * 通常のファイルアップ系の処理
 * メソッド名の先頭に必ずs4を付けること
 */
trait S3ContentsFileBehaviorTrait
{

    /**
     * s3FileSave
     * 画像をS3に保存
     * @author hagiwara
     */
    private function s3FileSave($file_info, $field_settings, $attachmentSaveData)
    {
        $S3 = new S3();
        $new_filedir = 'file/' . $attachmentSaveData['model'] . '/' . $attachmentSaveData['model_id'] . '/';
        $new_filepath = $new_filedir . $file_info['field_name'];
        $old_filepath = 'tmp/' . $file_info['tmp_file_name'];

        // tmpに挙がっているファイルを移
        if (!$S3->move($old_filepath, $new_filepath)) {
            return false;
        }

        // リサイズディレクトリはまず削除する
        // 失敗=ディレクトリが存在しないため、成功失敗判定は行わない。
        $S3->deleteRecursive($new_filepath . '/' . 'contents_file_resize_' . $file_info['field_name']);

        //リサイズ画像作成
        if (!empty($field_settings['resize'])) {
            foreach ($field_settings['resize'] as $resize_settings) {
                if (!$this->s3ImageResize($new_filepath, $resize_settings)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * s3FileDelete
     * S3のファイル削除
     * @author hagiwara
     */
    private function s3FileDelete($modelName, $modelId, $field)
    {
        $S3 = new S3();
        // リサイズのディレクトリ
        $resizeDir = 'file/' . $modelName . '/' . $modelId . '/' . 'contents_file_resize_' . $field . '/';
        if (!$S3->deleteRecursive($resizeDir)) {
            return false;
        }

        // 大元のファイル
        $deleteFile = 'file/' . $modelName . '/' . $modelId . '/' . $field;
        if (!$S3->delete($deleteFile)) {
            return false;
        }
        return true;
    }

    /**
     * s3ImageResize
     * 画像のリサイズ処理(S3用)
     * @author hagiwara
     */
    public function s3ImageResize($filepath, $resize)
    {
        $imagepathinfo = $this->getPathinfo($filepath, $resize);
        $S3 = new S3();
        // Exception = 存在していない場合
        $tmp_file_name = Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss'));
        $tmpPath = TMP . $tmp_file_name;
        // ベースのファイルを取得
        $baseObject = $S3->download($filepath);
        $fp = fopen($tmpPath, 'w');
        fwrite($fp, $baseObject['Body']);
        fclose($fp);
        if (!$this->imageResize($tmpPath, $resize)) {
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

        // tmpディレクトリの不要なディレクトリ/ファイルを削除
        $resizeFolder->delete();
        unlink($tmpPath);
        return $imagepathinfo['resize_filepath'];
    }

}
