<?php

namespace ContentsFile\Model\Behavior\Traits;

use Cake\Core\Configure;
use Cake\I18n\Time;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use ContentsFile\Aws\S3;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * S3ContentsFileBehaviorTrait
 * 通常のファイルアップ系の処理
 * メソッド名の先頭に必ずs3を付けること
 */
trait S3ContentsFileBehaviorTrait
{

    /**
     * s3ParamCheck
     * 通常の設定値チェック
     * @author hagiwara
     * @return void
     */
    private function s3ParamCheck(): void
    {
        // S3に必要な設定がそろっているかチェックする
        $s3Setting = Configure::read('ContentsFile.Setting.S3');
        if (
            !is_array($s3Setting) ||
            !array_key_exists('bucket', $s3Setting) ||
            !array_key_exists('tmpDir', $s3Setting) ||
            !array_key_exists('fileDir', $s3Setting) ||
            !array_key_exists('workingDir', $s3Setting)

        ) {
            throw new InternalErrorException('contentsFileS3Config paramater shortage');
        }

        // /が最後についていない場合はつける
        if (!preg_match('#/$#', $s3Setting['tmpDir'])) {
            Configure::write('ContentsFile.Setting.S3.tmpDir', $s3Setting['tmpDir'] . '/');
        }
        if (!preg_match('#/$#', $s3Setting['fileDir'])) {
            Configure::write('ContentsFile.Setting.S3.fileDir', $s3Setting['fileDir'] . '/');
        }
        if (!preg_match('#/$#', $s3Setting['workingDir'])) {
            Configure::write('ContentsFile.Setting.S3.workingDir', $s3Setting['workingDir'] . '/');
        }
    }

    /**
     * s3FileSave
     * ファイルをS3に保存
     * @author hagiwara
     * @param array $fileInfo
     * @param array $fieldSettings
     * @param array $attachmentSaveData
     * @return bool
     */
    private function s3FileSave(array $fileInfo, array $fieldSettings, array $attachmentSaveData): bool
    {
        $S3 = new S3();
        $newFiledir = Configure::read('ContentsFile.Setting.S3.fileDir') . $attachmentSaveData['model'] . '/' . $attachmentSaveData['model_id'] . '/';
        if (Configure::read('ContentsFile.Setting.randomFile') === true) {
            $newFilepath = $newFiledir . $attachmentSaveData['file_random_path'];
        } else {
            $newFilepath = $newFiledir . $fileInfo['field_name'];
        }
        if (Configure::read('ContentsFile.Setting.ext') === true) {
            $ext = (new \SplFileInfo($attachmentSaveData['file_name']))->getExtension();
            $newFilepath .= '.' . $ext;
        }
        $oldFilepath = Configure::read('ContentsFile.Setting.S3.tmpDir') . $fileInfo['tmp_file_name'];

        // 該当ファイルを消す
        // 失敗=ディレクトリが存在しないため、成功失敗判定は行わない。
        $this->s3FileDelete($attachmentSaveData['model'], $attachmentSaveData['model_id'], $fileInfo['field_name']);

        // tmpに挙がっているファイルを移
        if (!$S3->move($oldFilepath, $newFilepath)) {
            return false;
        }


        //リサイズ画像作成
        if (!empty($fieldSettings['resize'])) {
            foreach ($fieldSettings['resize'] as $resizeSettings) {
                if (!$this->s3ImageResize($newFilepath, $resizeSettings)) {
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
     * @param string $modelName
     * @param integer $modelId
     * @param string $field
     * @return bool
     */
    private function s3FileDelete(string $modelName, int $modelId, string $field): bool
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

       $S3 = new S3();
        // リサイズのディレクトリ
        $resizeDir = Configure::read('ContentsFile.Setting.S3.fileDir') . $modelName . '/' . $modelId . '/' . 'contents_file_resize_' . $deleteField . '/';
        if (!$S3->deleteRecursive($resizeDir)) {
            return false;
        }

        // 大元のファイル
        $deleteFile = Configure::read('ContentsFile.Setting.S3.fileDir') . $modelName . '/' . $modelId . '/' . $deleteField;
        if (Configure::read('ContentsFile.Setting.ext') === true) {
            $ext = (new \SplFileInfo($attachmentData->file_name))->getExtension();
            $deleteFile .= '.' . $ext;
        }
        if (!$S3->delete($deleteFile)) {
            return false;
        }
        return true;
    }

    /**
     * s3ImageResize
     * 画像のリサイズ処理(S3用)
     * @author hagiwara
     * @param string $filepath
     * @param array $resize
     * @return string
     */
    public function s3ImageResize(string $filepath, array $resize): string
    {
        $imagepathinfo = $this->getPathinfo($filepath, $resize);
        $S3 = new S3();
        // Exception = 存在していない場合
        $tmpFileName = Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss'));
        $tmpPath = Configure::read('ContentsFile.Setting.S3.workingDir') . $tmpFileName;
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

        $resizeFileDir = Configure::read('ContentsFile.Setting.S3.workingDir') . 'contents_file_resize_' . $tmpFileName;

        // 一つのはず
        $resizeImg = '';
        $finder = new Finder();
        $finder->in([$resizeFileDir]);
        foreach ($finder as $a) {
            $resizeImg = $a->getRealPath();
        }

        // リサイズ画像をアップロード
        $S3->upload($resizeImg, $imagepathinfo['resize_filepath']);

        // tmpディレクトリの不要なディレクトリ/ファイルを削除
        $filesystem = new Filesystem();
        $filesystem->remove($resizeFileDir);

        unlink($tmpPath);
        return $imagepathinfo['resize_filepath'];
    }

}
