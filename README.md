# Contents-file

[![Build Status](https://travis-ci.org/satthi/Contents-file.svg?branch=master)](https://travis-ci.org/satthi/Contents-file)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/satthi/Contents-file/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/satthi/Contents-file/?branch=master)

このプラグインはCakePHP用ファイルアップロードツールです。  
Ver 3.1 よりS3にも。対応しました。

## インストール
composer.json
```
{
	"require": {
		"satthi/contents-file": "*"
	}
}
```

`composer install`

## 使い方
###(初期設定・ローカルファイル保存の場合)
① bootstrap.phpなど
```php
Configure::write('ContentsFile.Setting', [
    'type' => 'normal',
    'Normal' => [
        'tmpDir' => TMP . 'cache/files',
        'fileDir' => ROOT . '/files/',
    ],
]);
```

② tmpDir及びfileDirを権限777で準備

###(初期設定・S3保存の場合)
① bootstrap.phpなど
```php
Configure::write('ContentsFile.Setting', [
    'type' => 's3',
    'S3' => [
        'key' => 'KEY',
        'secret' => 'SECRET',
        'bucket' => 'BUCKET_NAME',
        'tmpDir' => 'tmp',
        'fileDir' => 'file',
        'workingDir' => TMP,
    ]
]);
```
② workingDirを権限777で準備

③ S3のｊバケットを準備し、IAMの権限について以下を設定
```
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::BUCKET_NAME",
                "arn:aws:s3:::BUCKET_NAME/*"
            ]
        }
    ]
}
```
※ 作業するbucketについてs3:GetObject、s3:PutObject、s3:DeleteObject、s3:ListBucketの権限があれば良いです

###各種基本設定(共通)
マイグレーション実行

`ContentsFile/config/Migrations/20161109095904_AttachmentsAdd`

にファイルを置いているので利用してください。

※topicsを例とする
Table
```php
<?php
namespace App\Model\Table;

use Cake\ORM\Table;

class TopicsTable extends Table
{
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('topics');
        $this->primaryKey('id');
        // 追加項目
        $this->addBehavior('ContentsFile.ContentsFile');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator)
    {
        // providerを読み込み
        $validator->provider('contents_file', 'ContentsFile\Validation\ContentsFileValidation');
        $validator
            ->notEmpty('img', 'ファイルを添付してください' , function ($context){
                // 新規作成時はチェックする
                if ($context['newRecord'] == true) {
                    return true;
                }
                $fileInfo = $this->find('all')
                    ->where([$this->alias() . '.id' => $context['data']['id']])
                    ->first();
                // 編集時はfileがアップロードされていなければチェックする
                return empty($fileInfo->contents_file_img);
            })
            ->add('img', 'uploadMaxSizeCheck', [
                'rule' => 'uploadMaxSizeCheck',
                'provider' => 'contents_file',
                'message' => 'ファイルアップロード容量オーバーです',
                'last' => true,
            ])
            ->add('img', 'checkMaxSize', [
                'rule' => ['checkMaxSize' , '1M'],
                'provider' => 'contents_file',
                'message' => 'ファイルアップロード容量オーバーです',
                'last' => true,
            ])
            ->add('img', 'extension', [
                'rule' => ['extension', ['jpg', 'jpeg', 'gif', 'png',]],
                'message' => '画像のみを添付して下さい',
                'last' => true,
            ])
            ;
        return $validator;
    }
}
```

Entity
```php
<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;
// 追加項目
use ContentsFile\Model\Entity\ContentsFileTrait;

class Topic extends Entity
{
    // 追加項目
    use ContentsFileTrait;

    // 追加項目
    public $contentsFileConfig = [
        'fields' => [
            // 使用したいフィールドを設定
            'file' => [
                'resize' => false,
            ],
            'img' => [
                'resize' => [
                    // 画像のリサイズが必要な場合
                    ['width' => 300],
                    ['width' => 300, 'height' => 400],
                ],
            ],
        ],
    ];

    //&getメソッドをoverride
    public function &get($property)
    {
        $value = parent::get($property);
        $value = $this->getContentsFile($property, $value);
        return $value;
    }

    //setメソッドをoverride
    public function set($property, $value = null, array $options = []){
        parent::set($property, $value , $options);
        $this->setContentsFile();
        return $this;
    }
}
```

Controller(※ほとんど変更の必要なし)
```php
<?php
namespace App\Controller;

use App\Controller\AppController;
class TopicsController extends AppController
{
    // ヘルパー読み込み
    public $helpers = [
        'ContentsFile.ContentsFile',
    ];
}
```

Template
form.ctp
```php
    <?= $this->Form->create($topic, ['type' => 'file']) ?>
    <fieldset>
        <legend><?= __('Edit Topic') ?></legend>
        <?php
            echo $this->Form->input('file', ['type' => 'file']);
            if (!empty($topic->contents_file_file)) {
                echo $this->ContentsFile->link($topic->contents_file_file);
                // 「delete_フィールド名」がtrueでファイルを削除
                echo $this->Form->input('delete_file', ['type' => 'checkbox', 'label' => 'delete']);
            }
            echo $this->Form->input('img', ['type' => 'file']);
            if (!empty($topic->contents_file_img)) {
                echo $this->ContentsFile->image($topic->contents_file_img);
                echo $this->Form->input('delete_img', ['type' => 'checkbox', 'label' => 'delete']);
            }
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
```

view.ctp
```php
<?php // linkでファイルのリンク作成?>
<?= $this->ContentsFile->link($topic->contents_file_file);?>
<?php // imgでimgタグ作成 リサイズお画像の指定はオプションで指定?>
<?= $this->ContentsFile->image($topic->contents_file_img, ['resize' => ['width' => 300]]);?>
```


