<?php

App::uses('ContentsFileAppModel', 'ContentsFile.Model');

class ContentsFileResize extends ContentsFileAppModel {

    public $useTable = false;
    public $actsAs = array('ContentsFile.ContentsFile');

}

