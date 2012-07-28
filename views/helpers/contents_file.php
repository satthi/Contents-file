<?php

class ContentsFileHelper extends AppHelper {

    var $helpers = array('Html');

    /**
     * image
     *
     * @param $file
     * @return
     */
    // modified by s.hagiwara 2011.11.18
    //sizeを追加
    function image($file = null, $options = array(), $size = array()) {
        if (!$this->_makePath($file, $options, $size)) {
            return '';
        }
        return $this->Html->image($this->_makePath($file, $options, $size), $options);
    }

    /**
     * link
     *
     * $param $file
     * @return
     */
    // modified by s.hagiwara 2011.11.18
    //sizeを追加
    function link($file = null, $options = array(), $size = array()) {
        $options = Set::merge($options, array('target' => '_blank'));
        return $this->Html->link($file['file_name'], $this->_makePath($file, $options, $size), $options);
    }

    /**
     * url
     *
     * @param
     * @return
     */
    // modified by s.hagiwara 2011.11.18
    //sizeを追加
    function url($file = null, $options = array(), $size = array()) {
        return $this->Html->url($this->_makePath($file, $options, $size));
    }

    /**
     * _makeSrc
     *
     * @param $file
     * @param $options
     * @return
     */
    function _makePath($file = null, $options = array(), $sizeset = array()) {
        //画像表示に必要な情報の整理
        if (empty($sizeset)) {
            $size = 'default';
        } else {
            $size = '';
            if (!empty($sizeset['width'])) {
                $size .= $sizeset['width'] . '_';
            } else {
                $size .= '0_';
            }
            if (!empty($sizeset['height'])) {
                $size .= $sizeset['height'] . '_';
            } else {
                $size .= '0_';
            }
            if (!empty($sizeset['type'])) {
                $size .= $sizeset['type'];
            } else {
                $size .= 'large';
            }
        }
        //ダウンロードか直接表示か
        $isDownload = (isset($options['is_download']) && $options['is_download'] === false) ? 0 : 1;
        //一時ファイルか、保存済みファイルか
        if (array_key_exists('cache_tmp_name', $file)) {
            $url_file = array(
                'model' => $file['model'],
                'model_id' => 0,
                'field_name' => $file['field_name'],
                'file_name' => $file['cache_tmp_name']
            );
        } else {
            $url_file = $file;
        }
        //空の場合は何も表示しない
        if (empty($url_file)) {
            return array();
        }

        return array(
            'controller' => 'contents_files',
            'action' => 'loader',
            'plugin' => 'contents_file',
            $isDownload,
            $url_file['model'],
            $url_file['model_id'],
            $url_file['field_name'],
            $size,
            $url_file['file_name']
        );
    }

}
