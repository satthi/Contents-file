<?php

class ContentsFileComponent extends Object {

    var $components = array('Session');

    /**
     * initialize
     *
     * @param &$controller
     * @param $settions
     * @return
     */
    function initialize(&$controller, $settings = array()) {
        $this->controller = $controller;
    }

    /**
     * startup
     */
    public function startup(&$controller) {
        $controller->helpers[] = 'ContentsFile.ContentsFile';
    }

    /**
     * Before render
     */
    public function beforeRender(&$controller) {
        
    }

    /**
     * tmpSave
     */
    public function tmpSave($modelName = null) {
        if (!$modelName) {
            $modelName = $this->controller->modelClass;
        }
        if (!empty($this->controller->$modelName->contentsFileField)) {
            if (array_key_exists('column', $this->controller->$modelName->contentsFileField)) {
                $this->_tmpSave($modelName, $this->controller->$modelName->contentsFileField);
            } else {
                foreach ($this->controller->$modelName->contentsFileField as $field) {
                    $this->_tmpSave($modelName, $field);
                }
            }
        }
    }

    /*
     * _tmpSave
     */

    private function _tmpSave($modelName, $field) {
        if (!empty($this->controller->data[$modelName][$field['column']]['name'])) {
            $name = $this->controller->data[$modelName][$field['column']]['name'];
            $tmp_name = $this->controller->data[$modelName][$field['column']]['tmp_name'];
            $ext = substr(strrchr($name, '.'), 1);
            //一時保存ファイル名の作成
            $cache_tmp_name = 'contents_' . Security::hash(mt_rand() . strtotime(date('Y/m/d H:i:s')) . $name) . '.' . $ext;
            if (is_uploaded_file($tmp_name)) {
                move_uploaded_file($tmp_name, $field['cacheTempDir'] . $cache_tmp_name);
                $this->controller->data[$modelName][$field['column']]['cache_tmp_name'] = $cache_tmp_name;
                $this->controller->data[$modelName][$field['column']]['model'] = $modelName;
                $this->controller->data[$modelName][$field['column']]['field_name'] = $field['column'];
                $this->controller->data[$modelName][$field['column']]['file_name'] = $name;
            }
        } else {
            if (!empty($this->controller->data)) {
                $this->controller->data[$modelName][$field['column']] = $this->Session->read('ContentsFile.' . $modelName . '__' . $field['column']);
            }
            $this->Session->delete('ContentsFile.' . $modelName . '__' . $field['column']);
        }
    }

    /*
     * tmpSet
     */

    function tmpSet($modelName = null) {
        if (!$modelName) {
            $modelName = $this->controller->modelClass;
        }
        if (!empty($this->controller->$modelName->contentsFileField)) {
            if (array_key_exists('column', $this->controller->$modelName->contentsFileField)) {
                $this->_tmpSet($modelName, $this->controller->$modelName->contentsFileField);
            } else {
                foreach ($this->controller->$modelName->contentsFileField as $field) {
                    $this->_tmpSet($modelName, $field);
                }
            }
        }
    }

    /*
     * _tmpSet
     */

    function _tmpSet($modelName, $field) {
        //データがあった場合セッションに書き込んでおく。(次に表示するために
        if (!empty($this->controller->data[$modelName][$field['column']])) {
            $data = $this->controller->data[$modelName][$field['column']];
            $this->Session->write('ContentsFile.' . $modelName . '__' . $field['column'], $data);
        }
    }

}