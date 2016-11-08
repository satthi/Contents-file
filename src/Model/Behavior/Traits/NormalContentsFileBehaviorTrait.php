<?php

namespace ContentsFile\Model\Behavior\Traits;

use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;

/**
 * NormalContentsFileBehaviorTrait
 * 通常のファイルアップ系の処理
 * メソッド名の先頭に必ずnormalを付けること
 */
trait NormalContentsFileBehaviorTrait
{
    /**
     * fileSave
     * 画像を保存
     * @author hagiwara
     */
    private function normalFileSave($file_info, $field_settings, $attachmentSaveData)
    {
        $new_filedir = Configure::read('ContentsFile.Setting.filePath') . $attachmentSaveData['model'] . '/' . $attachmentSaveData['model_id'] . '/';
        $new_filepath = $new_filedir . $file_info['field_name'];
        if (
            !$this->mkdir($new_filedir, 0777, true) ||
            !rename(Configure::read('ContentsFile.Setting.cacheTempDir') . $file_info['tmp_file_name'] , $new_filepath)
        ) {
            return false;
        }

        //リサイズディレクトリはまず削除する
        $Folder = new Folder($new_filepath);
        $Folder->delete();

        //リサイズ画像作成
        if (!empty($field_settings['resize'])) {
            foreach ($field_settings['resize'] as $resize_settings) {
                if (!$this->normalImageResize($new_filepath, $resize_settings)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * normalFileDelete
     * 通常のファイル削除
     * @author hagiwara
     */
    private function normalFileDelete($modelName, $modelId, $field)
    {
        // リサイズのディレクトリ
        $resizeDir = Configure::read('ContentsFile.Setting.filePath') . $modelName . '/' . $modelId . '/' . 'contents_file_resize_' . $field . '/';
        if (is_dir($resizeDir)) {
            $deleteFolder = new Folder($resizeDir);
            if (!$deleteFolder->delete()) {
                return false;
            }
        }

        // 大元のファイル
        $deleteFile = Configure::read('ContentsFile.Setting.filePath') . $modelName . '/' . $modelId . '/' . '/' . $field;
        if (file_exists($deleteFile) && !unlink($deleteFile)) {
            return false;
        }
        return true;
    }

    private function normalImageResize($new_filepath, $resize_settings)
    {
        return $this->imageResize($new_filepath, $resize_settings);
    }
}
