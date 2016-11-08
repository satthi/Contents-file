<?php

namespace ContentsFile\View\Helper;

use Cake\View\Helper;

class ContentsFileHelper extends Helper {

    public $helpers = ['Html', 'Url'];
    private $__default_option = [
        'target' => '_blank',
        'escape' => false
    ];

    public function link($file_info, $options = [], $title = null)
    {
        //一時パス用の設定
        if ($title === null) {
            $title = $file_info['file_name'];
        }
        if (isset($options['resize'])) {
            $file_info['resize'] = $options['resize'];
            unset($options['resize']);
        }
        $options = array_merge(
            $this->__default_option,
            $options
        );

        return $this->Html->link(
            $title,
            $this->__urlArray($file_info),
            $options
        );
    }

    public function image($file_info, $options = [])
    {
        if (!empty($file_info)) {
            if (isset($options['resize'])) {
                $file_info['resize'] = $options['resize'];
                unset($options['resize']);
            }
            return $this->Html->image($this->__urlArray($file_info) ,$options);
        }
        return '';
    }

    public function url($file_info, $full = false)
    {
        if (!isset($file_info['resize'])) {
            $file_info['resize'] = false;
        }
        return $this->Url->build($this->__urlArray($file_info),$full);
    }

    private function __urlArray($file_info)
    {
        if (!empty($file_info['tmp_file_name'])) {
            return [
                'controller' => 'contents_file',
                'action' => 'loader',
                'plugin' => 'ContentsFile',
                'model' => $file_info['model'],
                'field_name' => $file_info['field_name'],
                'tmp_file_name' => $file_info['tmp_file_name'],
            ];
        } else {
            if (!isset($file_info['resize'])) {
                $file_info['resize'] = false;
            }
            return [
                'controller' => 'contents_file',
                'action' => 'loader',
                'plugin' => 'ContentsFile',
                'model' => $file_info['model'],
                'field_name' => $file_info['field_name'],
                'model_id' => $file_info['model_id'],
                'resize' => $file_info['resize'],
            ];
        }
    }
}
