<?php

namespace ContentsFile\Model\Behavior\Traits;

use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\I18n\Time;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;

/**
 * NormalContentsFileBehaviorTrait
 * 通常のファイルアップ系の処理
 * メソッド名の先頭に必ずnormalを付けること
 */
trait NormalContentsFileBehaviorTrait
{

    private function normalParamCheck()
    {
        // S3に必要な設定がそろっているかチェックする
        $normalSetting = Configure::read('ContentsFile.Setting.Normal');
        if (
            !is_array($normalSetting) ||
            !array_key_exists('tmpDir', $normalSetting) ||
            !array_key_exists('fileDir', $normalSetting)
        ) {
            throw new InternalErrorException('contentsFileNormalConfig paramater shortage');
        }
    }

    /**
     * normalFileSave
     * ファイルを保存
     * @author hagiwara
     */
    private function normalFileSave($fileInfo, $fieldSettings, $attachmentSaveData)
    {
        $newFiledir = Configure::read('ContentsFile.Setting.Normal.fileDir') . $attachmentSaveData['model'] . '/' . $attachmentSaveData['model_id'] . '/';
        $newFilepath = $newFiledir . $fileInfo['field_name'];
        if (
            !$this->mkdir($newFiledir, 0777, true) ||
            !rename(Configure::read('ContentsFile.Setting.Normal.tmpDir') . $fileInfo['tmp_file_name'] , $newFilepath)
        ) {
            return false;
        }

        //リサイズディレクトリはまず削除する
        $Folder = new Folder($newFilepath);
        $Folder->delete();

        //リサイズ画像作成
        if (!empty($fieldSettings['resize'])) {
            foreach ($fieldSettings['resize'] as $resizeSettings) {
                if (!$this->normalImageResize($newFilepath, $resizeSettings)) {
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
        $resizeDir = Configure::read('ContentsFile.Setting.Normal.fileDir') . $modelName . '/' . $modelId . '/' . 'contents_file_resize_' . $field . '/';
        if (is_dir($resizeDir)) {
            $deleteFolder = new Folder($resizeDir);
            if (!$deleteFolder->delete()) {
                return false;
            }
        }

        // 大元のファイル
        $deleteFile = Configure::read('ContentsFile.Setting.Normal.fileDir') . $modelName . '/' . $modelId . '/' . $field;
        if (file_exists($deleteFile) && !unlink($deleteFile)) {
            return false;
        }
        return true;
    }

    private function normalImageResize($newFilepath, $resizeSettings)
    {
        return $this->imageResize($newFilepath, $resizeSettings);
    }
}
