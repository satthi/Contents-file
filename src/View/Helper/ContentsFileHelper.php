<?php

namespace ContentsFile\View\Helper;

use Cake\Core\Configure;
use Cake\ORM\Entity;
use Cake\View\Helper;

class ContentsFileHelper extends Helper {

    public array $helpers = ['Html', 'Url', 'Form'];
    private array $defaultOption = [
        'target' => '_blank',
        'escape' => false,
        'download' => false
    ];

    /**
     * link
     * @author hagiwara
     * @param array|null $fileInfo
     * @param array $options
     * @param string $title
     * @return string|null
     */
    public function link($fileInfo, array $options = [], string $title = null): string
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

        $urlOption = $options;
        $linkOptions = $options;
        // download属性がいるとEdgeでダウンロード属性が優先されてしまう
        unset($linkOptions['download']);

        return $this->Html->link(
            $title,
            $this->urlArray($fileInfo, $urlOption),
            $linkOptions
        );
    }

    /**
     * image
     * @author hagiwara
     * @param array|null $fileInfo
     * @param array $options
     * @return string
     */
    public function image($fileInfo, array $options = []): string
    {
        if (empty($fileInfo)) {
            return '';
        }
        $options = array_merge(
            $this->defaultOption,
            $options
        );
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
     * @param array|null $fileInfo
     * @param boolean|array $buildOptions
     * @param array $urlArrayOptions
     * @return string
     */
    public function url($fileInfo, $buildOptions = [], array $urlArrayOptions = []): string
    {
        if (empty($fileInfo)) {
            return [];
        }

        // 互換性保持のためにboolを許可する
        if (is_bool($buildOptions)) {
            $buildOptions = [
                'fullBase' => $buildOptions
            ];
        }

        if (isset($urlArrayOptions['resize'])) {
            $fileInfo['resize'] = $urlArrayOptions['resize'];
            unset($urlArrayOptions['resize']);
        }
        $urlArrayOptions = array_merge(
            $this->defaultOption,
            $urlArrayOptions
        );
        return $this->Url->build($this->urlArray($fileInfo, $urlArrayOptions), $buildOptions);
    }

    /**
     * contentsFileHidden
     *
     * バリデーションに引っかかった際にファイルをそのまま送る用
     *
     * @author hagiwara
     * @param array|null $contentFileData
     * @param string $field
     * @return string
     */
    public function contentsFileHidden($contentFileData, string $field): string
    {
        $hiddenInput = '';
        if (!empty($contentFileData)) {
            foreach ($contentFileData as $fieldParts => $v) {
                $hiddenInput .= $this->Form->input($field . '.' . $fieldParts, ['type' => 'hidden', 'value' => $v]);
            }
        }
        return $hiddenInput;
    }

    /**
     * urlArray
     * @author hagiwara
     * @param array|null $fileInfo
     * @param array $options
     * @return array
     */
    private function urlArray($fileInfo, array $options)
    {
        if (!empty($fileInfo['tmp_file_name'])) {
            return [
                'controller' => 'contents_file',
                'action' => 'loader',
                'plugin' => 'ContentsFile',
                // prefixは無視する
                'prefix' => false,
                '?' => [
                    'model' => $fileInfo['model'],
                    'field_name' => $fileInfo['field_name'],
                    'tmp_file_name' => $fileInfo['tmp_file_name'],
                    'download' => $options['download'],
                ]
            ];
        } else {
            if (!isset($fileInfo['resize'])) {
                $fileInfo['resize'] = false;
            }
            // S3のホスティングの場合
            // downloadとかはつけられないので無視
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
                    // prefixは無視する
                    'prefix' => false,
                    '?' => [
                        'model' => $fileInfo['model'],
                        'field_name' => $fileInfo['field_name'],
                        'model_id' => $fileInfo['model_id'],
                        'resize' => $fileInfo['resize'],
                        'download' => $options['download'],
                    ]
                ];
            }
        }
    }

    /**
     * makeStaticS3Url
     * 静的ホスティング用のURL作成
     * @author hagiwara
     * @param array $fileInfo
     * @return string
     */
    private function makeStaticS3Url(array $fileInfo): string
    {
        $staticS3Url = Configure::read('ContentsFile.Setting.S3.static_domain') . '/' . Configure::read('ContentsFile.Setting.S3.fileDir') . $fileInfo['model'] . '/' . $fileInfo['model_id'] . '/';
        if ($fileInfo['resize'] == false) {
            if (Configure::read('ContentsFile.Setting.randomFile') === true && $fileInfo['file_random_path'] != '') {
                $staticS3Url .= $fileInfo['file_random_path'];
            } else {
                $staticS3Url .= $fileInfo['field_name'];
            }
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
            if (Configure::read('ContentsFile.Setting.randomFile') === true && $fileInfo['file_random_path'] != '') {
                $staticS3Url .= 'contents_file_resize_' . $fileInfo['file_random_path'] . '/' . $resizeText;
            } else {
                $staticS3Url .= 'contents_file_resize_' . $fileInfo['field_name'] . '/' . $resizeText;
            }
        }

        // 拡張子設定をtrueにした場合S3にも拡張子付きとなるのでURLにも拡張子をつける
        if (Configure::read('ContentsFile.Setting.ext') === true) {
            $staticS3Url .= '.' . (new \SplFileInfo($fileInfo['file_name']))->getExtension();
        }

        return $staticS3Url;
    }

    /* ここからはDD専用のHelper */
    /**
     * displayFilename
     * 静的ホスティング用のURL作成
     * @author hagiwara
     * @param Entity $entity
     * @param array $field
     * @return string
     */
    public function displayFilename(Entity $entity, string $field): string
    {
        if (!is_null($entity->{'contents_file_' . $field . '_filename'})) {
            return $entity->{'contents_file_' . $field . '_filename'};
        } elseif (is_array($entity->{'contents_file_' . $field})) {
            return $entity->{'contents_file_' . $field}['file_name'];
        } else {
            return '';
        }
    }
}
