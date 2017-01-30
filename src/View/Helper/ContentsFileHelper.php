<?php

namespace ContentsFile\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;

class ContentsFileHelper extends Helper {

    public $helpers = ['Html', 'Url', 'Form'];
    private $defaultOption = [
        'target' => '_blank',
        'escape' => false
    ];

    /**
     * link
     * @author hagiwara
     * @param array $fileInfo
     * @param array $options
     * @param string $title
     */
    public function link($fileInfo, $options = [], $title = null)
    {
        if (empty($fileInfo)) {
            return '';
        }
        //一時パス用の設定
        if ($title === null) {
            $title = $fileInfo['file_name'];
        }
        if (isset($options['resize'])) {
            $fileInfo['resize'] = $options['resize'];
            unset($options['resize']);
        }
        $options = array_merge(
            $this->defaultOption,
            $options
        );

        return $this->Html->link(
            $title,
            $this->urlArray($fileInfo, $options),
            $options
        );
    }

    /**
     * image
     * @author hagiwara
     * @param array $fileInfo
     * @param array $options
     */
    public function image($fileInfo, $options = [])
    {
        if (empty($fileInfo)) {
            return '';
        }
        if (!empty($fileInfo)) {
            if (isset($options['resize'])) {
                $fileInfo['resize'] = $options['resize'];
                unset($options['resize']);
            }
            return $this->Html->image($this->urlArray($fileInfo, $options), $options);
        }
        return '';
    }

    /**
     * url
     * @author hagiwara
     * @param array $fileInfo
     * @param boolean $full
     * @param array $options
     */
    public function url($fileInfo, $full = false, $options = [])
    {
        if (empty($fileInfo)) {
            return [];
        }
        if (!isset($fileInfo['resize'])) {
            $fileInfo['resize'] = false;
        }
        return $this->Url->build($this->urlArray($fileInfo, $options), $full);
    }
    
    /**
     * contentsFileHidden
     *
     * バリデーションに引っかかった際にファイルをそのまま送る用
     *
     * @author hagiwara
     * @param array|null $contentFileData
     * @param text $field
     */
    public function contentsFileHidden($contentFileData, $field)
    {
        $hiddenInput = '';
        if (!empty($contentFileData)) {
            foreach ($contentFileData as $fieldParts => $v) {
                $hiddenInput .= $this->Form->input($field . '.' . $fieldParts, ['type' => 'hidden']);
            }
        }
        return $hiddenInput;
    }

    /**
     * urlArray
     * @author hagiwara
     * @param array $fileInfo
     */
    private function urlArray($fileInfo, $options)
    {
        if (!empty($fileInfo['tmp_file_name'])) {
            return [
                'controller' => 'contents_file',
                'action' => 'loader',
                'plugin' => 'ContentsFile',
                'model' => $fileInfo['model'],
                'field_name' => $fileInfo['field_name'],
                'tmp_file_name' => $fileInfo['tmp_file_name'],
                // prefixは無視する
                'prefix' => false,
            ];
        } else {
            if (!isset($fileInfo['resize'])) {
                $fileInfo['resize'] = false;
            }
            // S3のホスティングの場合
            if (
                array_key_exists('static_s3', $options) &&
                $options['static_s3'] == true &&
                Configure::read('ContentsFile.Setting.type') == 's3' &&
                !is_null(Configure::read('ContentsFile.Setting.S3.static_domain'))
            ) {
                return $this->makeStaticS3Url($fileInfo);
            } else {
                // loaderを通す場合
                return [
                    'controller' => 'contents_file',
                    'action' => 'loader',
                    'plugin' => 'ContentsFile',
                    'model' => $fileInfo['model'],
                    'field_name' => $fileInfo['field_name'],
                    'model_id' => $fileInfo['model_id'],
                    'resize' => $fileInfo['resize'],
                    // prefixは無視する
                    'prefix' => false,
                ];
            }
        }
    }

    /**
     * makeStaticS3Url
     * 静的ホスティング用のURL作成
     * @author hagiwara
     * @param array $fileInfo
     */
    private function makeStaticS3Url($fileInfo)
    {
        $staticS3Url = Configure::read('ContentsFile.Setting.S3.static_domain') . '/' . Configure::read('ContentsFile.Setting.S3.fileDir') . '/' . $fileInfo['model'] . '/' . $fileInfo['model_id'] . '/';
        if ($fileInfo['resize'] == false) {
            $staticS3Url .= $fileInfo['field_name'];
        } else {
            $resizeText = '';
            if (
                empty($fileInfo['resize']['width'])
            ) {
                $resizeText .= '0';
            } else {
                $resizeText .= $fileInfo['resize']['width'];
            }
            $resizeText .= '_';
            if (
                empty($fileInfo['resize']['height'])
            ) {
                $resizeText .= '0';
            } else {
                $resizeText .= $fileInfo['resize']['height'];
            }
            $staticS3Url .= 'contents_file_resize_' . $fileInfo['field_name'] . '/' . $resizeText;
        }
        return $staticS3Url;
    }
}
