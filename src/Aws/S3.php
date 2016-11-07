<?php

namespace ContentsFile\Aws;

use Aws\S3\S3Client;
use Aws\Sdk;
use Cake\Core\Configure;

class S3
{
    private $client;
    public function __construct()
    {
        // S3に接続するためのクライアントを用意します。
        $key    = Configure::read('ContentsFile.S3Setting.key');
        $secret = Configure::read('ContentsFile.S3Setting.secret');
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

    public function upload($filepath, $filename)
    {

        $bucket_name = Configure::read('ContentsFile.S3Setting.bucket');
        $mimetype = mime_content_type($filepath);

        // ファイルのアップロード
        $data = file_get_contents($filepath);

        return $this->client->putObject([
            'Bucket' => $bucket_name,
            'Key' => $filename,
            'Body' => $data,
            'ContentType' => $mimetype
        ]);
    }

    public function download($filename)
    {
        return $this->client->getObject([
            'Bucket' => Configure::read('ContentsFile.S3Setting.bucket'),
            'Key' => $filename
        ]);
    }

    public function copy($oldFilename, $newFilename)
    {
        try {
            $this->client->copyObject(array(
                'Bucket' => Configure::read('ContentsFile.S3Setting.bucket'),
                'Key'        => $newFilename,
                'CopySource' => Configure::read('ContentsFile.S3Setting.bucket') . '/' . $oldFilename,
            ));
        } catch (S3Exception $e) {
            return false;
        }
        return true;
    }

    public function delete($filename)
    {
        try {
            $this->client->deleteObject(array(
            'Bucket' => Configure::read('ContentsFile.S3Setting.bucket'),
            'Key'        => $filename,
        ));
        } catch (S3Exception $e) {
            return false;
        }
        return true;
    }

    public function move($oldFilename, $newFilename)
    {
        return $this->copy($oldFilename, $newFilename) && $this->delete($oldFilename);
    }
}