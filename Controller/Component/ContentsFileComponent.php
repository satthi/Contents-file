<?php

App::uses('Security', 'Utility');

class ContentsFileComponent extends Component {

    public $components = array('Session');

    /**
     * __construct
     */
    public function __construct(ComponentCollection $collection, $settings = array()) {
        $this->controller = $collection->getController();
        parent::__construct($collection, $settings);
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
    public function tmpSave($hash = null,$modelName = null) {
        if (!$modelName) {
            $modelName = $this->controller->modelClass;
        }
        if (!empty($this->controller->$modelName->contentsFileField)) {
            if (array_key_exists('column', $this->controller->$modelName->contentsFileField)) {
                $this->_tmpSave($hash,$modelName, $this->controller->$modelName->contentsFileField);
            } else {
                foreach ($this->controller->$modelName->contentsFileField as $field) {
                    $this->_tmpSave($hash,$modelName, $field);
                }
            }
        }
    }

    /*
     * _tmpSave
     */

    private function _tmpSave($hash,$modelName, $field) {
        if (!empty($this->controller->request->data[$modelName][$field['column']]['name'])) {
            $name = $this->controller->request->data[$modelName][$field['column']]['name'];
            $tmp_name = $this->controller->request->data[$modelName][$field['column']]['tmp_name'];
            $ext = substr(strrchr($name, '.'), 1);
            //一時保存ファイル名の作成
            $cache_tmp_name = 'contents_' . Security::hash(mt_rand() . strtotime(date('Y/m/d H:i:s')) . $name) . '.' . $ext;
            if (is_uploaded_file($tmp_name)) {
                move_uploaded_file($tmp_name, $field['cacheTempDir'] . $cache_tmp_name);
                $this->controller->request->data[$modelName][$field['column']]['cache_tmp_name'] = $cache_tmp_name;
                $this->controller->request->data[$modelName][$field['column']]['model'] = $modelName;
                $this->controller->request->data[$modelName][$field['column']]['field_name'] = $field['column'];
                $this->controller->request->data[$modelName][$field['column']]['file_name'] = $name;
            }
        } else {
            if (!empty($this->controller->request->data)) {
                $this->controller->request->data[$modelName][$field['column']] = $this->Session->read('ContentsFile.' . $modelName . '__' . $field['column'] . '__' . $hash);
            }
            $this->Session->delete('ContentsFile.' . $modelName . '__' . $field['column'] . '__' . $hash);
        }
    }

    /*
     * tmpSet
     */

    function tmpSet($hash = null,$modelName = null) {
        if (!$modelName) {
            $modelName = $this->controller->modelClass;
        }
        if (!empty($this->controller->$modelName->contentsFileField)) {
            if (array_key_exists('column', $this->controller->$modelName->contentsFileField)) {
                $this->_tmpSet($hash,$modelName, $this->controller->$modelName->contentsFileField);
            } else {
                foreach ($this->controller->$modelName->contentsFileField as $field) {
                    $this->_tmpSet($hash,$modelName, $field);
                }
            }
        }
    }

    /*
     * _tmpSet
     */

    function _tmpSet($hash,$modelName, $field) {
        //データがあった場合セッションに書き込んでおく。(次に表示するために
        if (!empty($this->controller->request->data[$modelName][$field['column']])) {
            $data = $this->controller->request->data[$modelName][$field['column']];
            $this->Session->write('ContentsFile.' . $modelName . '__' . $field['column'] . '__' . $hash, $data);
        }
    }

}