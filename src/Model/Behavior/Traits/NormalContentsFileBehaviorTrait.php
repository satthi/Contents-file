<?php

namespace ContentsFile\Model\Behavior\Traits;

use Cake\Core\Configure;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Symfony\Component\Filesystem\Filesystem;

/**
 * NormalContentsFileBehaviorTrait
 * 通常のファイルアップ系の処理
 * メソッド名の先頭に必ずnormalを付けること
 */
trait NormalContentsFileBehaviorTrait
{

    /**
     * normalParamCheck
     * 通常の設定値チェック
     * @author hagiwara
     * @return void
     */
    private function normalParamCheck(): void
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
        // /が最後についていない場合はつける
        if (!preg_match('#/$#', $normalSetting['tmpDir'])) {
            Configure::write('ContentsFile.Setting.Normal.tmpDir', $normalSetting['tmpDir'] . '/');
        }
        if (!preg_match('#/$#', $normalSetting['fileDir'])) {
            Configure::write('ContentsFile.Setting.Normal.fileDir', $normalSetting['fileDir'] . '/');
        }
    }

    /**
     * normalFileSave
     * ファイルを保存
     * @author hagiwara
     * @param array $fileInfo
     * @param array $fieldSettings
     * @param array $attachmentSaveData
     * @return bool
     */
    private function normalFileSave(array $fileInfo, array $fieldSettings, array $attachmentSaveData): bool
    {
        $newFiledir = Configure::read('ContentsFile.Setting.Normal.fileDir') . $attachmentSaveData['model'] . '/' . $attachmentSaveData['model_id'] . '/';
        // ランダムパスの場合の分岐
        if (Configure::read('ContentsFile.Setting.randomFile') === true) {
            $newFilepath = $newFiledir . $attachmentSaveData['file_random_path'];
        } else {
            $newFilepath = $newFiledir . $fileInfo['field_name'];
        }

        if (Configure::read('ContentsFile.Setting.ext') === true) {
            $ext = (new \SplFileInfo($attachmentSaveData['file_name']))->getExtension();
            $newFilepath .= '.' . $ext;
        }

        //元ファイルは削除する
        $this->normalFileDelete($attachmentSaveData['model'], $attachmentSaveData['model_id'], $fileInfo['field_name']);

        if (
            !$this->mkdir($newFiledir, 0777, true) ||
            !rename(Configure::read('ContentsFile.Setting.Normal.tmpDir') . $fileInfo['tmp_file_name'], $newFilepath)
        ) {
            return false;
        }

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
     * @param string $modelName
     * @param integer $modelId
     * @param string $field
     * @return bool
     */
    private function normalFileDelete(string $modelName, int $modelId, string $field): bool
    {
        //attachementからデータを取得
        $attachmentModel = TableRegistry::getTableLocator()->get('Attachments');
        $attachmentData = $attachmentModel->find('all')
            ->where(['model' => $modelName])
            ->where(['model_id' => $modelId])
            ->where(['field_name' => $field])
            ->first()
        ;
        // 削除するべきファイルがない
        if (empty($attachmentData)) {
            return false;
        }
        if (Configure::read('ContentsFile.Setting.randomFile') === true && $attachmentData->file_random_path != '') {
            $deleteField = $attachmentData->file_random_path;
        } else {
            $deleteField = $attachmentData->field_name;
        }

        // リサイズのディレクトリ
        $resizeDir = Configure::read('ContentsFile.Setting.Normal.fileDir') . $modelName . '/' . $modelId . '/' . 'contents_file_resize_' . $deleteField . '/';
        if (is_dir($resizeDir)) {
            $filesystem = new Filesystem();
            if (!$filesystem->remove($resizeDir)) {
                return false;
            }
        }

        // 大元のファイル
        $deleteFile = Configure::read('ContentsFile.Setting.Normal.fileDir') . $modelName . '/' . $modelId . '/' . $deleteField;
        if (Configure::read('ContentsFile.Setting.ext') === true) {
            $ext = (new \SplFileInfo($attachmentData->file_name))->getExtension();
            $deleteFile .= '.' . $ext;
        }
        if (file_exists($deleteFile) && !unlink($deleteFile)) {
            return false;
        }
        return true;
    }

    /**
     * normalImageResize
     * 通常のファイルのリサイズ
     * @author hagiwara
     * @param string $newFilepath
     * @param array $resizeSettings
     * @return bool
     */
    private function normalImageResize(string $newFilepath, array $resizeSettings): bool
    {
        return $this->imageResize($newFilepath, $resizeSettings);
    }
}
