<?php

namespace ContentsFile\Model\Entity;

use Cake\Utility\Security;
use Cake\ORM\TableRegistry;
use Cake\I18n\Time;
use ContentsFile\Aws\S3;

trait ContentsFileTrait
{
    private $__contentsFileSettings = [];
    private $__attachmentModel;
    
    private function __contentsFileSettings()
    {
        
        $default = [];
        
        //設定値はまとめる
        $settings = $this->contentsFileConfig;
        
        $this->__contentsFileSettings = array_merge($default,$settings);
    }
    
    public function getContentsFile($property, $value){
        $this->__contentsFileSettings();
        if (
            //attachmentにデータが登録時のみ
            !empty($this->id) && 
            //設定値に設定されているとき
            preg_match('/^contents_file_(.*)$/', $property, $match) &&
            array_key_exists($match[1] , $this->__contentsFileSettings['fields'])// &&
        ){
            //何もセットされていないとき
            if (empty($this->_properties[$property])){
                //attachmentからデータを探しに行く
                $this->__attachmentModel = TableRegistry::get('Attachments');
                $attachmentData = $this->__attachmentModel->find('all')
                    ->where(['model' => $this->_registryAlias])
                    ->where(['model_id' => $this->id])
                    ->where(['field_name' => $match[1]])
                    ->first()
                ;
                // debug($attachmentData);
                // exit;
                if (!empty($attachmentData)){
                    $value = [
                        'model' => $attachmentData->model,
                        'model_id' => $attachmentData->model_id,
                        'field_name' => $attachmentData->field_name,
                        'file_name' => $attachmentData->file_name,
                        'file_content_type' => $attachmentData->file_content_type,
                        'file_size' => $attachmentData->file_size,
                    ];
                }
            } else {
                //それ以外はpropertiesの値を取得(setterで値を編集している場合はそれを反映するために必要)
                $value = $this->_properties[$property];
            }
        }
        return $value;
    }
    
    public function setContentsFile(){
        $this->__contentsFileSettings();
        foreach ($this->__contentsFileSettings['fields'] as $field => $field_setting){
            $file_info = $this->{$field};
            if (
                //ファイルの情報がある
                !empty($file_info) && 
                //エラーのフィールドがある=ファイルをアップロード中
                array_key_exists('error', $file_info) && 
                //空アップロード時は通さない(もともとのデータを活かす)
                $file_info['error'] != UPLOAD_ERR_NO_FILE
            ) {
                $file_set = [
                    'model' => $this->_registryAlias,
                    'model_id' => $this->id,
                    'field_name' => $field,
                    'file_name' => $file_info['name'],
                    'file_content_type' => $file_info['type'],
                    'file_size' => $file_info['size'],
                    'file_error' => $file_info['error'],
                ];
                
                //$file_infoにtmp_nameがいるときはtmpディレクトリへのファイルのコピーを行う
                if (!empty($file_info['tmp_name'])){
                    $tmp_file_name = Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss') . $file_info['name']);
                    
                    if ($this->__getExt($file_info['name']) !== null ){
                        $tmp_file_name .= '.' . $this->__getExt($file_info['name']);
                    }
                    // S3そのいち

                    if (!$this->tmpUpload($file_info['tmp_name'], $field_setting, $tmp_file_name)){
                        //エラー
                    }
                    $file_set['tmp_file_name'] = $tmp_file_name;
                }
                //これを残して次に引き渡したくないので
                unset($this->{$field});

                $this->{'contents_file_' . $field} = $file_set;
            }
            
        }
        return $this;
    }
    
    private function __getExt($file){
        $file_explode = explode('.',$file);
        //この場合拡張子なし
        if (count($file_explode) == 1){
            return null;
        }
        return $file_explode[(count($file_explode) - 1)];
    }

    private function tmpUpload($tmp_name, $field_setting, $tmp_file_name)
    {
        if ($field_setting['type'] == 's3') {
            $upload_file_name = 'tmp/' . $tmp_file_name;
            $S3 = new S3();
            return $S3->upload($tmp_name, $upload_file_name);
        } else {
            return copy($tmp_name, $field_setting['cacheTempDir'] . $tmp_file_name);
        }
    }
}
