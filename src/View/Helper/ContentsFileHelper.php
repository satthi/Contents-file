<?php

namespace ContentsFile\View\Helper;

use Cake\View\Helper;

class ContentsFileHelper extends Helper {

    public $helpers = ['Html', 'Url'];
    private $defaultOption = [
        'target' => '_blank',
        'escape' => false
    ];

    /**
     * link
     *
     */
    public function link($fileInfo, $options = [], $title = null)
    {
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
            $this->urlArray($fileInfo),
            $options
        );
    }

    /**
     * image
     *
     */
    public function image($fileInfo, $options = [])
    {
        if (!empty($fileInfo)) {
            if (isset($options['resize'])) {
                $fileInfo['resize'] = $options['resize'];
                unset($options['resize']);
            }
            return $this->Html->image($this->urlArray($fileInfo) ,$options);
        }
        return '';
    }

    /**
     * url
     *
     */
    public function url($fileInfo, $full = false)
    {
        if (!isset($fileInfo['resize'])) {
            $fileInfo['resize'] = false;
        }
        return $this->Url->build($this->urlArray($fileInfo),$full);
    }

    /**
     * urlArray
     *
     */
    private function urlArray($fileInfo)
    {
        if (!empty($fileInfo['tmp_file_name'])) {
            return [
                'controller' => 'contents_file',
                'action' => 'loader',
                'plugin' => 'ContentsFile',
                'model' => $fileInfo['model'],
                'field_name' => $fileInfo['field_name'],
                'tmp_file_name' => $fileInfo['tmp_file_name'],
            ];
        } else {
            if (!isset($fileInfo['resize'])) {
                $fileInfo['resize'] = false;
            }
            return [
                'controller' => 'contents_file',
                'action' => 'loader',
                'plugin' => 'ContentsFile',
                'model' => $fileInfo['model'],
                'field_name' => $fileInfo['field_name'],
                'model_id' => $fileInfo['model_id'],
                'resize' => $fileInfo['resize'],
            ];
        }
    }
}
