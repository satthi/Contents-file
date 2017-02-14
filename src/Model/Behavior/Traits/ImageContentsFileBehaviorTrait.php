<?php

namespace ContentsFile\Model\Behavior\Traits;

/**
 * ImageContentsFileBehaviorTrait
 * 画像関係の処理
 */
trait ImageContentsFileBehaviorTrait
{
    private $tp;

    /**
     * imageResize
     * 画像のリサイズ処理(外からでもたたけるようにpublicにする
     * @author hagiwara
     * @param string $imagePath
     * @param array $baseSize
     */
    public function imageResize($imagePath, $baseSize) {

        $imageInfo = $this->getImageInfo($imagePath);
        $image = $imageInfo['image'];
        $imagetype = $imageInfo['imagetype'];
        if (!$image) {
            // 画像の読み込み失敗
            return false;
        }
        // // 画像の縦横サイズを取得
        $imageSizeInfo = $this->imageSizeInfo($image, $baseSize);

        return $this->imageResizeMake($image, $imagetype, $imagePath, $baseSize, $imageSizeInfo);
    }

    /**
     * getImageInfo
     * 画像情報の取得
     * @author hagiwara
     * @param string $imagePath
     */
    private function getImageInfo($imagePath)
    {
        if (file_exists($imagePath) === false) {
            return false;
        }

        $imagetype = exif_imagetype($imagePath);
        if ($imagetype === false) {
            return false;
        }

        // 画像読み込み
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
            default:
                $image = false;
        }
        return [
            'image' => $image,
            'imagetype' => $imagetype,
        ];
    }

    /**
     * imageSizeInfo
     * 画像リサイズ情報の取得
     * @author hagiwara
     * @param resource $image
     * @param array $baseSize
     */
    private function imageSizeInfo($image, $baseSize)
    {
        // 画像の縦横サイズを取得
        $sizeX = ImageSX($image);
        $sizeY = ImageSY($image);
        // リサイズ後のサイズ
        if (!array_key_exists('height', $baseSize)) {
            $baseSize['height'] = 0;
        }
        if (!array_key_exists('width', $baseSize)) {
            $baseSize['width'] = 0;
        }
        // リサイズ種別
        if (!array_key_exists('type', $baseSize)) {
            $baseSize['type'] = 'normal';
        }

        if ($baseSize['type'] == 'normal_s' || $baseSize['type'] == 'scoop') {
            // 短い方基準もしくは、くりぬき
            if (empty($baseSize['width']) || !empty($baseSize['height']) && $sizeX * $baseSize['height'] < $sizeY * $baseSize['width']) {
                // 縦基準
                $mag = $baseSize['width'] / $sizeX;
                $reSizeX = $baseSize['width'];
                $reSizeY = $sizeY * $mag;
            } else {
                // 横基準
                $mag = $baseSize['height'] / $sizeY;
                $reSizeY = $baseSize['height'];
                $reSizeX = $sizeX * $mag;
            }
        } else {
            // 長い方基準
            if (empty($baseSize['width']) || !empty($baseSize['height']) && $sizeX * $baseSize['height'] < $sizeY * $baseSize['width']) {
                // 縦基準
                $mag = $baseSize['height'] / $sizeY;
                $reSizeY = $baseSize['height'];
                $reSizeX = $sizeX * $mag;
            } else {
                // 横基準
                $mag = $baseSize['width'] / $sizeX;
                $reSizeX = $baseSize['width'];
                $reSizeY = $sizeY * $mag;
            }
        }
        return [
            'sizeX' => $sizeX,
            'sizeY' => $sizeY,
            'reSizeX' => $reSizeX,
            'reSizeY' => $reSizeY,
            'type' => $baseSize['type'],
        ];
    }

    /**
     * imageResizeMake
     * 画像リサイズ情報の取得
     * @author hagiwara
     * @param resource $image
     * @param integer $imagetype
     * @param string $imagePath
     * @param array $baseSize
     * @param array $imageSizeInfo
     */
    private function imageResizeMake($image, $imagetype, $imagePath, $baseSize, $imageSizeInfo)
    {
        // サイズ変更後の画像データを生成
        $campusX = $imageSizeInfo['reSizeX'];
        $campusY = $imageSizeInfo['reSizeY'];
        debug($baseSize);
        // くりぬきの場合(幅と高さが両方必要)
        if ($imageSizeInfo['type'] == 'scoop' && !empty($baseSize['width']) && !empty($baseSize['height'])) {
            $campusX = $baseSize['width'];
            $campusY = $baseSize['height'];
        }
        $outImage = ImageCreateTrueColor($campusX, $campusY);
        if (!$outImage) {
            // リサイズ後の画像作成失敗
            return false;
        }

        //透過GIF.PNG対策
        $this->setTPinfo($image, $imageSizeInfo['sizeX'], $imageSizeInfo['sizeY']);

        // 画像で使用する色を透過度を指定して作成
        $bgcolor = imagecolorallocatealpha($outImage, @$this->tp["red"], @$this->tp["green"], @$this->tp["blue"], @$this->tp["alpha"]);

        // 塗り潰す
        imagefill($outImage, 0, 0, $bgcolor);
        // 透明色を定義
        imagecolortransparent($outImage, $bgcolor);
        //!透過GIF.PNG対策
        // 画像リサイズ
        
        $diffX = 0;
        $diffY = 0;
        // くりぬきの場合(幅と高さが両方必要)
        if ($imageSizeInfo['type'] == 'scoop' && !empty($baseSize['width']) && !empty($baseSize['height'])) {
            $diffX = ($imageSizeInfo['sizeX'] - ($baseSize['width'] * $imageSizeInfo['sizeX'] / $imageSizeInfo['reSizeX'])) / 2;
            $diffY = ($imageSizeInfo['sizeY'] - ($baseSize['height'] * $imageSizeInfo['sizeY'] / $imageSizeInfo['reSizeY'])) / 2;
        }
        $ret = imagecopyresampled($outImage, $image, 0, 0, $diffX, $diffY, $imageSizeInfo['reSizeX'], $imageSizeInfo['reSizeY'], $imageSizeInfo['sizeX'], $imageSizeInfo['sizeY']);
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
            default:
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
            if (!isset($tp) || $tp !== null) {
                break;
            }
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
     * @param string $imagePath
     * @param array $resize
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
            if (!isset($resize['type'])) {
                $resize['type'] = 'normal';
            }
            $pathinfo['resize_filepath'] = $pathinfo['resize_dir'] . '/' . $resize['width'] . '_' . $resize['height'];
            if (isset($resize['type']) && $resize['type'] == true) {
                $pathinfo['resize_filepath'] .= '_' . $resize['type'];
            }
        }
        return $pathinfo;
    }
}
