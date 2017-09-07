<?php

namespace ContentsFile\Model\Entity;

use Cake\Utility\Security;
use Cake\ORM\TableRegistry;
use Cake\I18n\Time;
use ContentsFile\Aws\S3;
use Cake\Network\Exception\InternalErrorException;
use Cake\Core\Configure;
use Cake\Filesystem\File;

trait ContentsFileTrait
{
    private $contentsFileSettings = [];

    /**
     * contentsFileSettings
     * 設定値のセッティング
     *
     * @author hagiwara
     */
    private function contentsFileSettings()
    {
        $default = [];
        //設定値はまとめる
        $settings = $this->contentsFileConfig;
        $this->contentsFileSettings = array_merge($default, $settings);
    }

    /**
     * getContentsFileSettings
     * 設定値のセッティングの取得
     *
     * @author hagiwara
     */
    public function getContentsFileSettings()
    {
        if (empty($this->contentsFileSettings)) {
            $this->contentsFileSettings();
        }
        return $this->contentsFileSettings;
    }

    /**
     * getContentsFile
     * ファイルのgetterのセッティング
     *
     * @author hagiwara
     * @param string $property
     * @param array $value
     */
    public function getContentsFile($property, $value)
    {
        $this->contentsFileSettings();
        if (
            //attachmentにデータが登録時のみ
            !empty($this->id) &&
            //設定値に設定されているとき
            preg_match('/^contents_file_(.*)$/', $property, $match) &&
            array_key_exists($match[1], $this->contentsFileSettings['fields'])
        ) {
            //何もセットされていないとき
            if (empty($this->_properties[$property])) {
                //attachmentからデータを探しに行く
                $attachmentModel = TableRegistry::get('Attachments');
                $attachmentData = $attachmentModel->find('all')
                    ->where(['model' => $this->source()])
                    ->where(['model_id' => $this->id])
                    ->where(['field_name' => $match[1]])
                    ->first()
                ;
                if (!empty($attachmentData)) {
                    $value = [
                        'model' => $attachmentData->model,
                        'model_id' => $attachmentData->model_id,
                        'field_name' => $attachmentData->field_name,
                        'file_name' => $attachmentData->file_name,
                        'file_content_type' => $attachmentData->file_content_type,
                        'file_size' => $attachmentData->file_size,
                        'file_random_path' => $attachmentData->file_random_path,
                    ];
                }
            } else {
                //それ以外はpropertiesの値を取得(setterで値を編集している場合はそれを反映するために必要)
                $value = $this->_properties[$property];
            }
        }
        return $value;
    }

    /**
     * setContentsFile
     * ファイルのsetterのセッティング
     *
     * @author hagiwara
     */
    public function setContentsFile()
    {
        $this->contentsFileSettings();
        foreach ($this->contentsFileSettings['fields'] as $field => $fieldSetting) {
            // 通常のパターン
            if (!array_key_exists('type', $fieldSetting) || $fieldSetting['type'] == 'normal') {
                $this->normalSetContentsFile($field, $fieldSetting);
            } else {
                $this->ddSetContentsFile($field, $fieldSetting);
            }
        }
        return $this;
    }

    /**
     * normalSetContentsFile
     * ファイルのsetterのセッティング
     *
     * @author hagiwara
     */
    private function normalSetContentsFile($field, $fieldSetting)
    {
            $fileInfo = $this->{$field};
            if (
                //ファイルの情報がある
                !empty($fileInfo) &&
                //エラーのフィールドがある=ファイルをアップロード中
                array_key_exists('error', $fileInfo) &&
                //空アップロード時は通さない(もともとのデータを活かす)
                $fileInfo['error'] != UPLOAD_ERR_NO_FILE
            ) {
                $fileSet = [
                    'model' => $this->source(),
                    'model_id' => $this->id,
                    'field_name' => $field,
                    'file_name' => $fileInfo['name'],
                    'file_content_type' => Configure::read('ContentsFile.Setting.type'),
                    'file_size' => $fileInfo['size'],
                    'file_error' => $fileInfo['error'],
                ];

                //$fileInfoにtmp_nameがいるときはtmpディレクトリへのファイルのコピーを行う
                if (!empty($fileInfo['tmp_name'])) {
                    $tmpFileName = Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss') . $fileInfo['name']);

                    if ($this->getExt($fileInfo['name']) !== null) {
                        $tmpFileName .= '.' . $this->getExt($fileInfo['name']);
                    }

                    // tmpディレクトリへのアップロードのエラー(パーミッションなど)
                    if (!$this->tmpUpload($fileInfo['tmp_name'], $fieldSetting, $tmpFileName)) {
                        throw new InternalErrorException('tmp upload error');
                    }
                    $fileSet['tmp_file_name'] = $tmpFileName;
                }
                //これを残して次に引き渡したくないので
                unset($this->{$field});

                $this->{'contents_file_' . $field} = $fileSet;
                $this->{'contents_file_' . $field . '_filename'} = $fileInfo['name'];
            }
    }

    /**
     * ddSetContentsFile
     * ファイルのsetterのセッティング
     *
     * @author hagiwara
     */
    private function ddSetContentsFile($field, $fieldSetting)
    {

        $fileInfo = $this->{$field};
        if (!empty($fileInfo)) {
            if (!preg_match('/^data:([^;]+);base64,(.+)$/', $fileInfo, $fileMatch)) {
                // ちゃんとファイルアップがないのでエラー
                throw new InternalErrorException('tmp upload erroar');
            }
            $filename = $this->{'contents_file_' . $field . '_filename'};

            $filebody = base64_decode($fileMatch[2]);
            $filesize = strlen($filebody);

            $tmpFileName = Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss') . $filename);

            if ($this->getExt($filename) !== null) {
                $tmpFileName .= '.' . $this->getExt($filename);
            }
            // まずは一時的にファイルを書き出す
            $ddTmpFileName = TMP . Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss') . $filename);
            $fp = new File($ddTmpFileName);
            $fp->write($filebody);

            // tmpディレクトリへのアップロードのエラー(パーミッションなど)
            if (!$this->tmpUpload($ddTmpFileName, $fieldSetting, $tmpFileName)) {
                throw new InternalErrorException('tmp upload error');
            }
            $fp->delete();
            $fp->close();

            $fileSet = [
                'model' => $this->source(),
                'model_id' => $this->id,
                'field_name' => $field,
                'file_name' => $filename,
                'file_content_type' => Configure::read('ContentsFile.Setting.type'),
                'file_size' => $filesize,
                'file_error' => 0,
            ];

            $fileSet['tmp_file_name'] = $tmpFileName;

            //これを残して次に引き渡したくないので
            unset($this->{$field});

            $this->{'contents_file_' . $field} = $fileSet;
            $this->{'contents_file_' . $field . '_filename'} = $filename;
        }
    }

    /**
     * getExt
     * 拡張子の取得
     *
     * @author hagiwara
     * @param string $file
     */
    private function getExt($file)
    {
        $fileExplode = explode('.', $file);
        //この場合拡張子なし
        if (count($fileExplode) == 1) {
            return null;
        }
        return $fileExplode[(count($fileExplode) - 1)];
    }

    /**
     * tmpUpload
     * tmpディレクトリへのアップロード
     *
     * @author hagiwara
     * @param string $tmpFileName
     * @param array $fieldSetting
     * @param string $tmpFileName
     */
    private function tmpUpload($tmpName, $fieldSetting, $tmpFileName)
    {
        // すでにtraitのため、ここはif文での分岐処理
        if (Configure::read('ContentsFile.Setting.type') == 'normal') {
            return copy($tmpName, Configure::read('ContentsFile.Setting.Normal.tmpDir') . $tmpFileName);
        } elseif (Configure::read('ContentsFile.Setting.type') == 's3') {
            $uploadFileName = Configure::read('ContentsFile.Setting.S3.tmpDir') . $tmpFileName;
            $S3 = new S3();
            return $S3->upload($tmpName, $uploadFileName);
        } else {
            throw new InternalErrorException('contentsFileConfig type illegal');
        }
    }
}
