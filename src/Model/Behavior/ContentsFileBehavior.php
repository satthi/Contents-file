<?php

namespace ContentsFile\Model\Behavior;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Filesystem\Folder;
use Cake\I18n\Time;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use ContentsFile\Aws\S3;
use ContentsFile\Model\Behavior\Traits\NormalContentsFileBehaviorTrait;
use ContentsFile\Model\Behavior\Traits\S3ContentsFileBehaviorTrait;

class ContentsFileBehavior extends Behavior {

    use NormalContentsFileBehaviorTrait;
    use S3ContentsFileBehaviorTrait;
    private $__attachmentModel;

    /**
     * __construct
     * @author hagiwara
     */
    public function __construct(Table $table, array $config = [])
    {
        parent::__construct($table, $config);
        // 指定外のものが指定されている場合はエラーとする
        if (!in_array(Configure::read('ContentsFile.Setting.type'), ['s3', 'normal'])) {
            throw new InternalErrorException('contentsFileConfig type illegal');
        }
    }

    /**
     * afterSave
     * 画像をafterSaveで保存する
     * @author hagiwara
     */
    public function afterSave(Event $event, Entity $entity, ArrayObject $options)
    {
        //設定値をentityから取得
        $contentsFileConfig = $entity->getContentsFileSettings();
        $this->__attachmentModel = TableRegistry::get('Attachments');
        foreach ($contentsFileConfig['fields'] as $field => $field_settings) {
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
            $file_info = $entity->{'contents_file_' . $field};
            if (
                !empty($file_info) &&
                //tmp_file_nameがある=アップロードしたファイルがある
                array_key_exists('tmp_file_name', $file_info)
            ) {
                // ファイルの削除
                $attachmentSaveData = [
                    'model' => $this->_table->alias(),
                    'model_id' => $entity->id,
                    'field_name' => $file_info['field_name'],
                    'file_name' => $file_info['file_name'],
                    'file_content_type' => $file_info['file_content_type'],
                    'file_size' => $file_info['file_size'],
                ];
                $attachmentEntity = $this->__attachmentModel->newEntity($attachmentSaveData);
                // 通常とS3で画像保存方法の切り替え
                if (!$this->{Configure::read('ContentsFile.Setting.type') . 'FileSave'}($file_info, $field_settings, $attachmentSaveData)) {
                    return false;
                }

                //元のデータがあるかfind(あれば更新にする)
                $attachmentDataCheck = $this->__attachmentModel->find('all')
                    ->where(['model' => $file_info['model']])
                    ->where(['model_id' => $entity->id])
                    ->where(['field_name' => $file_info['field_name']])
                    ->first(1);
                if (!empty($attachmentDataCheck)) {
                    $attachmentEntity->id = $attachmentDataCheck->id;
                }
                if (!$this->__attachmentModel->save($attachmentEntity)) {
                    return false;
                }
            }
        }

        return true;

    }

    /**
     * fileDelete
     * ファイル削除
     * @author hagiwara
     */
    public function fileDelete(Entity $entity, $fields = [])
    {
        $contentsFileConfig = $entity->getContentsFileSettings();
        if (!empty($contentsFileConfig['fields'])) {
            foreach ($contentsFileConfig['fields'] as $field => $config) {
                // fieldsの指定がない場合は全部消す
                if (empty($fields) || in_array($field, $fields)) {
                    if (empty($entity->id)) {
                        return true;
                    }

                    $modelName = $entity->source();
                    $modelId = $entity->id;
                    // 添付ファイルデータの削除
                    $attachmentModel = TableRegistry::get('Attachments');
                    $deleteAttachmentData = $attachmentModel->find('all')
                        ->where(['Attachments.model' => $modelName])
                        ->where(['Attachments.model_id' => $modelId])
                        ->where(['Attachments.field_name' => $field])
                        ->first();

                    if (!empty($deleteAttachmentData->id)) {
                        $attachmentModel->delete($deleteAttachmentData);
                        // 通常とS3でファイルの削除方法の切り替え
                        if (!$this->{Configure::read('ContentsFile.Setting.type') . 'FileDelete'}($modelName, $modelId, $field)) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * imageResize
     * 画像のリサイズ処理(外からでもたたけるようにpublicにする
     * @author hagiwara
     */
    public function imageResize($imagePath, $baseSize) {
        if (file_exists($imagePath) === false) {
            return false;
        }

        $imagetype = exif_imagetype($imagePath);
        if ($imagetype === false) {
            return false;
        }

        // 画像読み込み
        $image = false;

        switch ($imagetype) {
            case IMAGETYPE_GIF:
                $image = ImageCreateFromGIF($imagePath);
                break;
            case IMAGETYPE_JPEG:
                $image = ImageCreateFromJPEG($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = ImageCreateFromPNG($imagePath);
                break;
            default :
                return false;
        }

        if (!$image) {
            // 画像の読み込み失敗
            return false;
        }

        // 画像の縦横サイズを取得
        $sizeX = ImageSX($image);
        $sizeY = ImageSY($image);
        // リサイズ後のサイズ
        $reSizeX = 0;
        $reSizeY = 0;
        $mag = 1;
        if (!array_key_exists('height', $baseSize)) {
            $baseSize['height'] = 0;
        }
        if (!array_key_exists('width', $baseSize)) {
            $baseSize['width'] = 0;
        }

        if (empty($baseSize['width']) || !empty($baseSize['height']) && $sizeX * $baseSize['height'] < $sizeY * $baseSize['width']) {
            // 縦基準
            $diffSizeY = $sizeY - $baseSize['height'];
            $mag = $baseSize['height'] / $sizeY;
            $reSizeY = $baseSize['height'];
            $reSizeX = $sizeX * $mag;
        } else {
            // 横基準
            $diffSizeX = $sizeX - $baseSize['width'];
            $mag = $baseSize['width'] / $sizeX;
            $reSizeX = $baseSize['width'];
            $reSizeY = $sizeY * $mag;
        }

        // サイズ変更後の画像データを生成
        $outImage = ImageCreateTrueColor($reSizeX, $reSizeY);
        if (!$outImage) {
            // リサイズ後の画像作成失敗
            return false;
        }

        //透過GIF.PNG対策
        $this->setTPinfo($image, $sizeX, $sizeY);

        // 画像で使用する色を透過度を指定して作成
        $bgcolor = imagecolorallocatealpha($outImage, @$this->tp["red"], @$this->tp["green"], @$this->tp["blue"], @$this->tp["alpha"]);

        // 塗り潰す
        imagefill($outImage, 0, 0, $bgcolor);
        // 透明色を定義
        imagecolortransparent($outImage, $bgcolor);
        //!透過GIF.PNG対策
        // 画像リサイズ
        $ret = imagecopyresampled($outImage, $image, 0, 0, 0, 0, $reSizeX, $reSizeY, $sizeX, $sizeY);

        if ($ret === false) {
            // リサイズ失敗
            return false;
        }

        ImageDestroy($image);

        // 画像保存
        $imagepathinfo = $this->getPathInfo($imagePath, $baseSize);
        //resizeファイルを格納するディレクトリを作成
        if (
            !$this->mkdir($imagepathinfo['resize_dir'], 0777, true)
        ) {
            return false;
        }

        switch ($imagetype) {
            case IMAGETYPE_GIF:
                ImageGIF($outImage, $imagepathinfo['resize_filepath']);
                break;
            case IMAGETYPE_JPEG:
                ImageJPEG($outImage, $imagepathinfo['resize_filepath'], 100);
                break;
            case IMAGETYPE_PNG:
                ImagePNG($outImage, $imagepathinfo['resize_filepath']);
                break;
            default :
                return false;
        }

        ImageDestroy($outImage);

        return true;
    }

    /**
     * setTPinfo
     *  透過GIFか否か？および透過GIF情報セット
     *
     *   透過GIFである場合
     *   プロパティ$tpに透過GIF情報がセットされる
     *
     *     $tp["red"]   = 赤コンポーネントの値
     *     $tp["green"] = 緑コンポーネントの値
     *     $tp["blue"]  = 青コンポーネントの値
     *     $tp["alpha"] = 透過度(0から127/0は完全に不透明な状態/127は完全に透明な状態)
     *
     * @access private
     * @param  resource $src 画像リソース
     * @param  int   $w  対象画像幅(px)
     * @param  int   $h  対象画像高(px)
     * @return boolean
     */
    private function setTPinfo($src, $w, $h) {

        for ($sx = 0; $sx < $w; $sx++) {
            for ($sy = 0; $sy < $h; $sy++) {
                $rgb = imagecolorat($src, $sx, $sy);
                $idx = imagecolorsforindex($src, $rgb);
                if ($idx["alpha"] !== 0) {
                    $tp = $idx;
                    break;
                }
            }
            if (!isset($tp) || $tp !== null)
                break;
        }
        // 透過GIF
        if (isset($tp) && is_array($tp)) {
            $this->tp = $tp;
            return true;
        }
        // 透過GIFではない
        return false;
    }

    /**
     * getPathInfo
     * 通常のpathinfoに加えてContentsFile独自のpathも一緒に設定する
     * @author hagiwara
     */
    public function getPathInfo($imagePath, $resize = []) {
        $pathinfo = pathinfo($imagePath);
        $pathinfo['resize_dir'] = $pathinfo['dirname'] . '/contents_file_resize_' . $pathinfo['filename'];
        //一旦ベースのパスを通しておく
        $pathinfo['resize_filepath'] = $imagePath;
        if (!empty($resize)) {
            if (!isset($resize['width'])) {
                $resize['width'] = 0;
            }
            if (!isset($resize['height'])) {
                $resize['height'] = 0;
            }
            $pathinfo['resize_filepath'] = $pathinfo['resize_dir'] . '/' . $resize['width'] . '_' . $resize['height'];
        }

        return $pathinfo;
    }

    /**
     * mkdir
     * ディレクトリの作成(パーミッションの設定のため
     * @author hagiwara
     */
    private function mkdir($path, $permission, $recursive)
    {
        if (is_dir($path)) {
            return true;
        }
        $oldumask = umask(0);
        $result = mkdir($path, $permission, $recursive);
        umask($oldumask);
        return $result;
    }

}
