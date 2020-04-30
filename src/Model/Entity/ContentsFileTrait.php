<?php

namespace ContentsFile\Model\Entity;

use Cake\Core\Configure;
use Cake\Filesystem\File;
use Cake\Http\Exception\InternalErrorException;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use ContentsFile\Aws\S3;
use Laminas\Diactoros\UploadedFile;

trait ContentsFileTrait
{
    private $contentsFileSettings = [];

    /**
     * contentsFileSettings
     * 設定値のセッティング
     *
     * @author hagiwara
     * @return void
     */
    private function contentsFileSettings(): void
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
     * @return array
     */
    public function getContentsFileSettings(): array
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
     * @param mixed $value
     * @return mixed
     */
    public function getContentsFile(string $property, $value)
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
            if (empty($this->_fields[$property])) {
                //attachmentからデータを探しに行く
                $attachmentModel = TableRegistry::getTableLocator()->get('Attachments');
                $attachmentData = $attachmentModel->find('all')
                    ->where(['model' => $this->getSource()])
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
                $value = $this->_fields[$property];
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
     * @param string $field
     * @param array $fieldSetting
     * @return void
     * @author hagiwara
     */
    private function normalSetContentsFile(string $field, array $fieldSetting): void
    {
        $fileInfo = $this->{$field};
        if (
            //ファイルの情報がある
            is_object($fileInfo) &&
            //空アップロード時は通さない(もともとのデータを活かす)
            $fileInfo->getError() != UPLOAD_ERR_NO_FILE
        ) {
            $fileSet = [
                'model' => $this->getSource(),
                'model_id' => $this->id,
                'field_name' => $field,
                'file_name' => $fileInfo->getClientFilename(),
                'file_content_type' => Configure::read('ContentsFile.Setting.type'),
                'file_size' => $fileInfo->getSize(),
                'file_error' => $fileInfo->getError(),
            ];

            //$fileInfoにtmp_nameがいるときはtmpディレクトリへのファイルのコピーを行う
            // if (!empty($fileInfo['tmp_name'])) {
            $tmpFileName = Security::hash(rand() . Time::now()->i18nFormat('YYYY/MM/dd HH:ii:ss') . $fileInfo->getClientFilename());

            if ($this->getExt($fileInfo->getClientFilename()) !== null) {
                $tmpFileName .= '.' . $this->getExt($fileInfo->getClientFilename());
            }

            // tmpディレクトリへのアップロードのエラー(パーミッションなど)
            if (!$this->tmpUpload($fileInfo, $fieldSetting, $tmpFileName)) {
                throw new InternalErrorException('tmp upload error');
            }
            $fileSet['tmp_file_name'] = $tmpFileName;
            // }
            //これを残して次に引き渡したくないので
            unset($this->{$field});

            $nowNew = $this->isNew();

            $this->set('contents_file_' . $field, $fileSet);
            $this->set('contents_file_' . $field . '_filename', $fileInfo->getClientFilename());
        }
    }

    /**
     * ddSetContentsFile
     * ファイルのsetterのセッティング
     *
     * @param string $field
     * @param array $fieldSetting
     * @return void
     * @author hagiwara
     */
    private function ddSetContentsFile(string $field, array $fieldSetting): void
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
                'model' => $this->getSource(),
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
     * @return string|null
     */
    private function getExt(string $file): ?string
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
     * @param \Laminas\Diactoros\UploadedFile $fileInfo
     * @param array $fieldSetting
     * @param string $tmpFileName
     * @return mixed
     */
    private function tmpUpload(UploadedFile $fileInfo, array $fieldSetting, string $tmpFileName)
    {
        // すでにtraitのため、ここはif文での分岐処理
        if (Configure::read('ContentsFile.Setting.type') == 'normal') {
            $fileInfo->moveTo(Configure::read('ContentsFile.Setting.Normal.tmpDir') . $tmpFileName);
            // 向きの調整をする場合
            if (Configure::read('ContentsFile.Setting.exifRotate') == true) {
                $this->orientationFixedImage($tmpFileName, $tmpFileName);
            }

            return true;

        } elseif (Configure::read('ContentsFile.Setting.type') == 's3') {
            $tmpName = Configure::read('ContentsFile.Setting.S3.workingDir') . $tmpFileName;
            $fileInfo->moveTo($tmpName);
            $uploadFileName = Configure::read('ContentsFile.Setting.S3.tmpDir') . $tmpFileName;

            $S3 = new S3();
            return $S3->upload($tmpName, $uploadFileName);
        } else {
            throw new InternalErrorException('contentsFileConfig type illegal');
        }
    }

    /**
     * orientationFixedImage
     * http://www.glic.co.jp/blog/archives/88 よりコピペ
     * 画像の方向を正す
     * 向きだけロジックが逆そうなので調整
     *
     * @param string $input
     * @param string $output
     * @return void
     * @author hagiwara
     */
    private function orientationFixedImage(string $input, string $output): void
    {
        $imagetype = exif_imagetype($input);
        // 何も取れない場合何もしない
        if ($imagetype === false) {
            return;
        }
        // exif情報の取得
        $exif_datas = [];
        // 画像読み込み
        switch ($imagetype) {
            case IMAGETYPE_GIF:
                $image = ImageCreateFromGIF($input);
                break;
            case IMAGETYPE_JPEG:
                $image = ImageCreateFromJPEG($input);
                // exif情報の取得(jpegのみ
                $exif_datas = @exif_read_data($input);
                break;
            case IMAGETYPE_PNG:
                $image = ImageCreateFromPNG($input);
                break;
            default:
                $image = false;
        }

        // 画像以外は何もしない
        if (!$image) {
            return;
        }

        // 向き補正
        if(isset($exif_datas['Orientation'])){
            $orientation = $exif_datas['Orientation'];
            if($image){
                // 未定義
                if($orientation == 0) {
                    // 通常
                }else if($orientation == 1) {
                    // 左右反転
                }else if($orientation == 2) {
                    $image = $this->imageFlop($image);
                    // 180°回転
                }else if($orientation == 3) {
                    $image = $this->imageRotate($image,180, 0);
                    // 上下反転
                }else if($orientation == 4) {
                    $image = $this->imageFlip($image);
                    // 反時計回りに90°回転 上下反転
                }else if($orientation == 5) {
                    $image = $this->imageRotate($image,90, 0);
                    $image = $this->imageFlip($image);
                    // 時計回りに90°回転
                }else if($orientation == 6) {
                    $image = $this->imageRotate($image,-90, 0);
                    // 時計回りに90°回転 上下反転
                }else if($orientation == 7) {
                    $image = $this->imageRotate($image,-90, 0);
                    $image = $this->imageFlip($image);
                // 反時計回りに90°回転
                }else if($orientation == 8) {
                    $image = $this->imageRotate($image,90, 0);
                }
            }
        }

        switch ($imagetype) {
            case IMAGETYPE_GIF:
                ImageGIF($image ,$output);
                break;
            case IMAGETYPE_JPEG:
                ImageJPEG($image ,$output, 100);
                break;
            case IMAGETYPE_PNG:
                ImagePNG($image ,$output);
                break;
            default:
                return;
        }
    }

    /**
     * imageFlop
     * http://www.glic.co.jp/blog/archives/88 よりコピペ
     * 画像の左右反転
     *
     * @param resource $image
     * @return resource
     * @author hagiwara
     */
    private function imageFlop($image)
    {
        // 画像の幅を取得
        $w = imagesx($image);
        // 画像の高さを取得
        $h = imagesy($image);
        // 変換後の画像の生成（元の画像と同じサイズ）
        $destImage = @imagecreatetruecolor($w,$h);
        // 逆側から色を取得
        for($i=($w-1);$i>=0;$i--){
            for($j=0;$j<$h;$j++){
                $color_index = imagecolorat($image,$i,$j);
                $colors = imagecolorsforindex($image,$color_index);
                imagesetpixel($destImage,abs($i-$w+1),$j,imagecolorallocate($destImage,$colors["red"],$colors["green"],$colors["blue"]));
            }
        }
        return $destImage;
    }

    /**
     * imageFlip
     * http://www.glic.co.jp/blog/archives/88 よりコピペ
     * 上下反転
     * @param resource $image
     * @return resource
     *
     * @author hagiwara
     */
    private function imageFlip($image)
    {
        // 画像の幅を取得
        $w = imagesx($image);
        // 画像の高さを取得
        $h = imagesy($image);
        // 変換後の画像の生成（元の画像と同じサイズ）
        $destImage = @imagecreatetruecolor($w,$h);
        // 逆側から色を取得
        for($i=0;$i<$w;$i++){
            for($j=($h-1);$j>=0;$j--){
                $color_index = imagecolorat($image,$i,$j);
                $colors = imagecolorsforindex($image,$color_index);
                imagesetpixel($destImage,$i,abs($j-$h+1),imagecolorallocate($destImage,$colors["red"],$colors["green"],$colors["blue"]));
            }
        }
        return $destImage;
    }


    /**
     * imageRotate
     * http://www.glic.co.jp/blog/archives/88 よりコピペ
     * 画像を回転
     * @param resouce $image
     * @param integer $angle
     * @param integer $bgd_color
     * @return resource
     *
     * @author hagiwara
     */
    private function imageRotate($image, int $angle, int $bgd_color)
    {
        return imagerotate($image, $angle, $bgd_color, 0);
    }
}
