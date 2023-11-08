<?php
declare(strict_types=1);

namespace ContentsFile\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Http\Exception\InternalErrorException;
use Cake\ORM\Behavior;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use ContentsFile\Model\Behavior\Traits\ImageContentsFileBehaviorTrait;
use ContentsFile\Model\Behavior\Traits\NormalContentsFileBehaviorTrait;
use ContentsFile\Model\Behavior\Traits\S3ContentsFileBehaviorTrait;

class ContentsFileBehavior extends Behavior
{
    use NormalContentsFileBehaviorTrait;
    use S3ContentsFileBehaviorTrait;
    use ImageContentsFileBehaviorTrait;

    /**
     * __construct
     *
     * @author hagiwara
     * @param \Cake\ORM\Table $table
     * @param array $config
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);
        // 指定外のものが指定されている場合はエラーとする
        if (!in_array(Configure::read('ContentsFile.Setting.type'), ['s3', 'normal'])) {
            throw new InternalErrorException('contentsFileConfig type illegal');
        }
        // Configureの設定不足をチェックする
        $this->{Configure::read('ContentsFile.Setting.type') . 'ParamCheck'}();
    }

    /**
     * afterSave
     * 画像をafterSaveで保存する
     *
     * @author hagiwara
     * @param \Cake\Event\Event $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     * @return bool
     */
    public function afterSave(Event $event, EntityInterface $entity, ArrayObject $options): bool
    {
        //設定値をentityから取得
        $contentsFileConfig = $entity->getContentsFileSettings();
        $attachmentModel = TableRegistry::getTableLocator()->get('ContentsFile.Attachments');
        foreach ($contentsFileConfig['fields'] as $field => $fieldSettings) {
            // ファイルの削除を最初に確認
            if ($entity->{'delete_' . $field} == true) {
                // 該当フィールドを削除
                if (!$this->fileDelete($entity, [$field])) {
                    return false;
                }
                // ファイルの削除に成功したら保存処理は飛ばす
                continue;
            }

            //contents_file_の方に入ったentityをベースに処理する
            $fileInfo = $entity->{'contents_file_' . $field};
            if (
                !empty($fileInfo) &&
                //tmp_file_nameがある=アップロードしたファイルがある
                array_key_exists('tmp_file_name', $fileInfo)
            ) {
                // ファイルの削除
                $attachmentSaveData = [
                    'model' => $fileInfo['model'],
                    'model_id' => $entity->id,
                    'field_name' => $fileInfo['field_name'],
                    'file_name' => $fileInfo['file_name'],
                    'file_content_type' => $fileInfo['file_content_type'],
                    'file_size' => $fileInfo['file_size'],
                ];
                if (Configure::read('ContentsFile.Setting.randomFile') === true) {
                    $attachmentSaveData['file_random_path'] = $this->makeRandomPath();
                }
                $attachmentEntity = $attachmentModel->newEntity($attachmentSaveData);
                //元のデータがあるかfind(あれば元のファイルを消す)
                $oldAttachmentData = $attachmentModel->find('all')
                    ->where(['model' => $fileInfo['model']])
                    ->where(['model_id' => $entity->id])
                    ->where(['field_name' => $fileInfo['field_name']])
                    ->first();

                // 通常とS3で画像保存方法の切り替え
                if (!$this->{Configure::read('ContentsFile.Setting.type') . 'FileSave'}($fileInfo, $fieldSettings, $attachmentSaveData, $oldAttachmentData)) {
                    return false;
                }

                //元のデータがあれば更新にする
                if (!empty($oldAttachmentData)) {
                    $attachmentEntity->id = $oldAttachmentData->id;
                }
                if (!$attachmentModel->save($attachmentEntity)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * fileDelete
     * ファイル削除
     *
     * @author hagiwara
     * @param \Cake\Datasource\EntityInterface $entity
     * @param array $fields
     * @return bool
     */
    public function fileDelete(EntityInterface $entity, array $fields = []): bool
    {
        // 新規作成データ時は何もしない
        if (empty($entity->id)) {
            return true;
        }
        $contentsFileConfig = $entity->getContentsFileSettings();
        if (!empty($contentsFileConfig['fields'])) {
            foreach ($contentsFileConfig['fields'] as $field => $config) {
                // fieldsの指定がない場合は全部消す
                if (!empty($fields) && !in_array($field, $fields)) {
                    continue;
                }
                if (!$this->fileDeleteParts($entity, $field)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * fileValidationWhen
     * ファイルのバリデーションのwhenに使用可能なメソッド
     *
     * @author hagiwara
     * @param array $context
     * @param string $field
     * @return bool
     */
    public function fileValidationWhen(array $context, string $field): bool
    {
        // 初期遷移時などでdataがそもそもいない場合はチェックしない
        if (empty($context['data'])) {
            return false;
        }
        // content_file_fileがいる場合はチェックしない
        if (!empty($context['data']['contents_file_' . $field])) {
            return false;
        }

        // 新規作成時はチェックする
        if ($context['newRecord'] == true) {
            return true;
        }
        $fileInfo = $this->_table->find('all')
            ->where([$this->_table->aliasField('id') => $context['data']['id']])
            ->first();
        // 編集時はfileがアップロードされていなければチェックする
        return empty($fileInfo->{'contents_file_' . $field});
    }

    /**
     * fileDeleteParts
     * ファイル削除
     *
     * @author hagiwara
     * @param \Cake\Datasource\EntityInterface $entity
     * @param string $field
     */
    private function fileDeleteParts(EntityInterface $entity, string $field): bool
    {
        $attachmentModel = TableRegistry::getTableLocator()->get('ContentsFile.Attachments');
        $modelName = $entity->getSource();
        $modelId = $entity->id;
        // 添付ファイルデータの削除
        $deleteAttachmentData = $attachmentModel->find('all')
            ->where(['Attachments.model' => $modelName])
            ->where(['Attachments.model_id' => $modelId])
            ->where(['Attachments.field_name' => $field])
            ->first();

        if (!empty($deleteAttachmentData->id)) {
            // 通常とS3でファイルの削除方法の切り替え
            if (!$this->{Configure::read('ContentsFile.Setting.type') . 'FileDelete'}($modelName, $modelId, $field)) {
                return false;
            }
            $attachmentModel->delete($deleteAttachmentData);
        }

        return true;
    }

    /**
     * mkdir
     * ディレクトリの作成(パーミッションの設定のため
     *
     * @author hagiwara
     * @param string $path
     * @param int $permission
     * @param bool $recursive
     * @return bool
     */
    private function mkdir(string $path, int $permission, bool $recursive): bool
    {
        if (is_dir($path)) {
            return true;
        }
        $oldumask = umask(0);
        $result = mkdir($path, $permission, $recursive);
        umask($oldumask);

        return $result;
    }

    /**
     * makeRandomKey
     *
     * @author hagiwara
     * @return string
     */
    private function makeRandomPath(): string
    {
        $hash = Security::hash(time() . rand());
        $attachmentModel = TableRegistry::getTableLocator()->get('ContentsFile.Attachments');
        $check = $attachmentModel->find('all')
            ->where(['Attachments.file_random_path' => $hash])
            ->count();
        // データがある場合は再作成
        if ($check > 0) {
            return $this->makeRandomPath();
        }

        return $hash;
    }
}
