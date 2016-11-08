<?php

namespace ContentsFile\Aws;

use Aws\Sdk;
use Cake\Core\Configure;

/**
 * S3
 * AWS SDKのS3関係の処理
 * @author hagiwara
 */
class S3
{
    private $client;

    /**
     * __construct
     * @author hagiwara
     */
    public function __construct()
    {
        // S3に接続するためのクライアントを用意します。
        $key    = Configure::read('ContentsFile.Setting.S3.key');
        $secret = Configure::read('ContentsFile.Setting.S3.secret');
        $sdk = new Sdk([
            'credentials' => array(
                'key'=> $key,
                'secret' => $secret,
            ),
            'version' => 'latest',
            'region'  => 'ap-northeast-1'
        ]);
        $this->client = $sdk->createS3();
    }

    /**
     * upload
     * S3へのファイルアップロード
     * @author hagiwara
     */
    public function upload($filepath, $filename)
    {

        $bucketName = Configure::read('ContentsFile.Setting.S3.bucket');
        $mimetype = mime_content_type($filepath);

        // ファイルのアップロード
        $data = file_get_contents($filepath);

        return $this->client->putObject([
            'Bucket' => $bucketName,
            'Key' => $filename,
            'Body' => $data,
            'ContentType' => $mimetype
        ]);
    }

    /**
     * upload
     * S3からのファイルダウンロード
     * @author hagiwara
     */
    public function download($filename)
    {
        return $this->client->getObject([
            'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
            'Key' => $filename
        ]);
    }

    /**
     * copy
     * S3上でのファイルコピー
     * @author hagiwara
     */
    public function copy($oldFilename, $newFilename)
    {
        // 失敗時はException
        try {
            $this->client->copyObject(array(
                'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
                'Key'        => $newFilename,
                'CopySource' => Configure::read('ContentsFile.Setting.S3.bucket') . '/' . $oldFilename,
            ));
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * delete
     * S3上でのファイル削除
     * @author hagiwara
     */
    public function delete($filename)
    {
        // 失敗時はException
        try {
            $this->client->deleteObject(array(
                'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
                'Key' => $filename,
            ));
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * deleteRecursive
     * S3上でのファイル削除(再帰的)
     * @author hagiwara
     */
    public function deleteRecursive($dirname)
    {
        // $dirnameで消す単位は最低でもIDなので文字列内に数値のディレクトリがあることをチェックする
        if (!preg_match('#/[0-9]+/#', $dirname)) {
            return false;
        }
        $deleteFileLists = $this->client->listObjects(array(
            'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
            'Prefix' => $dirname
        ));

        if (!empty($deleteFileLists->get('Contents'))) {
            foreach ($deleteFileLists->get('Contents') as $deleteDirInfo) {
                if (!$this->delete($deleteDirInfo['Key'])) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * move
     * S3上でのファイル移動(コピー&削除)
     * @author hagiwara
     */
    public function move($oldFilename, $newFilename)
    {
        // 失敗時はException
        return $this->copy($oldFilename, $newFilename) && $this->delete($oldFilename);
    }

    /**
     * fileExists
     * S3上でのファイルの存在チェック
     * @author hagiwara
     */
    public function fileExists($filename)
    {
        // 存在しない場合はExceptionが発行される
        try {
            $this->client->getObject([
                'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
                'Key' => $filename
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }

    }
}
