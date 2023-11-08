# Contents-file

このプラグインはCakePHP5用ファイルアップロードツールです。

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
### (初期設定・ローカルファイル保存の場合)

① 設定を記述
bootstrap.phpなど
```php
Configure::write('ContentsFile.Setting', [
    'type' => 'normal',
    // trueでファイル名がランダム文字列に
    'randomFile' => true,
    // trueで拡張子付きでファイルを保存する。loaderを通さずに使用する場合は設定しておいたほうが良い。
    'ext' => true,
    'Normal' => [
        'tmpDir' => TMP . 'cache/files/',
        'fileDir' => ROOT . '/files/',
    ],
]);
```

② プラグイン読込
Application.php
```php
public function bootstrap()
{
    $this->addPlugin('Migrations');
    // 追加
    $this->addPlugin('ContentsFile', ['routes' => true]);
}
```

③ tmpDir及びfileDirを権限777で準備

### (初期設定・S3保存の場合)

① 設定を記述
bootstrap.phpなど
```php
Configure::write('ContentsFile.Setting', [
    'type' => 's3',
    // trueでファイル名がランダム文字列に
    'randomFile' => true,
    // trueで拡張子付きでファイルを保存する。awsの場合は別途ヘッダーを吐き出すため設定する必要性はあまり高くない。
    'ext' => true,
    'S3' => [
        'key' => 'KEY',
        'secret' => 'SECRET',      // IAM Roleを利用する場合、省略可能
        'bucket' => 'BUCKET_NAME', // IAM Roleを利用する場合、省略可能
        'tmpDir' => 'tmp',
        'fileDir' => 'file',
        'workingDir' => TMP,
        // ファイルのURLをloaderを通さず直接awsに接続したい場合に設定
        /*
        //s3-ap-northeast-1.amazonaws.com/BUCKET_NAME でも
        //BUCKET_NAME.s3-website-ap-northeast-1.amazonaws.com でも
        //指定の文字列.cloudfront.net でも使用したいものを設定
        */
        'static_domain' => '//s3-ap-northeast-1.amazonaws.com/BUCKET_NAME',
        // minio 使用時にendpointを使用
        //'endpoint' => 'http://{{ip_address}}:9000',
    ]
]);
```
② プラグイン読込
Application.php
```php
public function bootstrap()
{
    // 追加
    $this->addPlugin('ContentsFile', ['routes' => true]);
}
```

③ workingDirを権限777で準備

④ S3のｊバケットを準備し、IAMの権限について以下を設定
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

### 各種基本設定(共通)

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

        $this->setTable('topics');
        $this->setPrimaryKey('id');
        // 追加項目
        $this->addBehavior('ContentsFile.ContentsFile');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator)
    {
        // providerを読み込み
        $validator->setProvider('contents_file', 'ContentsFile\Validation\ContentsFileValidation');
        $validator
            ->notEmpty('img', 'ファイルを添付してください' , function ($context){
                // fileValidationWhenメソッドを追加しました。
                return $this->fileValidationWhen($context, 'img');
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
                    // typeには
                    // normal(default) 長い方を基準に画像をリサイズする
                    // normal_s 短い方を基準に画像をリサイズする
                    // scoop 短い方を基準に画像をリサイズし、中央でくりぬきする
                    ['width' => 300, 'height' => 400, 'type' => 'scoop'],
                ],
            ],
        ],
    ];

    protected array $_accessible = [
        'title' => true,
        // 初期状態に追記
        'file' => true,
        'contents_file_file' => true,
        'delete_file' => true,
        'img' => true,
        'contents_file_img' => true,
        'delete_img' => true,
    ];


    //&getメソッドをoverride
    public function &get(string $property): mixed
    {
        $value = parent::get($property);
        $value = $this->getContentsFile($property, $value);
        return $value;
    }

    //setメソッドをoverride
    public function set(array|string $field, mixed $value = null, array $options = []){
        parent::set($field, $value , $options);
        $this->setContentsFile();
        return $this;
    }
}
```

Controller(※ほとんど変更の必要なし)
```php
<?php
namespace App\Controller;
use Cake\Event\EventInterface;

use App\Controller\AppController;
class TopicsController extends AppController
{
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->viewBuilder()->setHelpers(['ContentsFile.ContentsFile']);
    }
}
```

Template
form.php
```php
    <?= $this->Form->create($topic, ['type' => 'file']) ?>
    <fieldset>
        <legend><?= __('Edit Topic') ?></legend>
        <?php
            echo $this->Form->control('file', ['type' => 'file']);
            // バリデーションに引っかかった際に、再度ファイルを登録しなくて済むための対応
            echo $this->ContentsFile->contentsFileHidden($topic->contents_file_file, 'contents_file_file');
            if (!empty($topic->contents_file_file)) {
                echo $this->ContentsFile->link($topic->contents_file_file);
                // 「delete_フィールド名」がtrueでファイルを削除
                echo $this->Form->control('delete_file', ['type' => 'checkbox', 'label' => 'delete']);
            }
            echo $this->Form->control('img', ['type' => 'file']);
            echo $this->ContentsFile->contentsFileHidden($topic->contents_file_img, 'contents_file_img');
            if (!empty($topic->contents_file_img)) {
                echo $this->ContentsFile->image($topic->contents_file_img);
                echo $this->Form->control('delete_img', ['type' => 'checkbox', 'label' => 'delete']);
            }
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
```

view.php
```php
<?php // linkでファイルのリンク作成?>
<?= $this->ContentsFile->link($topic->contents_file_file);?>
<?php // imgでimgタグ作成 リサイズお画像の指定はオプションで指定?>
<?= $this->ContentsFile->image($topic->contents_file_img, ['resize' => ['width' => 300, 'height' => 400, 'type' => 'scoop']]);?>

<?php // 静的ホスティングにアクセス?>
<?php // linkでファイルのリンク作成?>
<?= $this->ContentsFile->link($topic->contents_file_file, ['static_s3' => true]);?>
<?php // imgでimgタグ作成 リサイズお画像の指定はオプションで指定?>
<?= $this->ContentsFile->image($topic->contents_file_img, ['resize' => ['width' => 300], 'static_s3' => true]);?>
```
