<?php
declare(strict_types=1);

namespace ContentsFile\Aws;

use Aws\Result;
use Aws\S3\S3Client;
use Aws\Sdk;
use Cake\Core\Configure;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;

/**
 * S3
 * AWS SDKのS3関係の処理
 *
 * @author hagiwara
 */
class S3
{
    private S3Client $client;

    /**
     * __construct
     *
     * @author hagiwara
     */
    public function __construct()
    {
        // S3に必要な設定がそろっているかチェックする
        $S3Setting = Configure::read('ContentsFile.Setting.S3');
        if (
            !is_array($S3Setting) ||
            !array_key_exists('bucket', $S3Setting) ||
            !array_key_exists('tmpDir', $S3Setting) ||
            !array_key_exists('fileDir', $S3Setting)
        ) {
            throw new InternalErrorException('contentsFileS3Config paramater shortage');
        }
        // S3に接続するためのクライアントを用意します。
        $config = [
            'version' => 'latest',
            'region' => 'ap-northeast-1',
        ];
        // key, secretが指定されている場合はcredentialsを設定する
        $key = Configure::read('ContentsFile.Setting.S3.key');
        $secret = Configure::read('ContentsFile.Setting.S3.secret');
        if (!empty($key) && !empty($secret)) {
            $config['credentials'] = [
                'key' => $key,
                'secret' => $secret,
            ];
        }
        // minio を使用する場合、endpoint を設定する
        $endpoint = Configure::read('ContentsFile.Setting.S3.endpoint');
        if (!empty($endpoint)) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = true;
        }
        $sdk = new Sdk($config);
        $this->client = $sdk->createS3();
    }

    /**
     * getClient
     * clientを取得
     *
     * @author hagiwara
     */
    public function getClient(): S3Client
    {
        return $this->client;
    }

    /**
     * upload
     * S3へのファイルアップロード
     *
     * @author hagiwara
     */
    public function upload(string $filepath, string $filename): Result
    {
        // アップロードするべきファイルがない場合
        if (!file_exists($filepath)) {
            throw new InternalErrorException('upload file not found');
        }
        $bucketName = Configure::read('ContentsFile.Setting.S3.bucket');
        $mimetype = mime_content_type($filepath);

        // ファイルのアップロード
        $data = file_get_contents($filepath);

        return $this->client->putObject([
            'Bucket' => $bucketName,
            'Key' => $filename,
            'Body' => $data,
            'ContentType' => $mimetype,
        ]);
    }

    /**
     * upload
     * S3からのファイルダウンロード
     *
     * @author hagiwara
     */
    public function download(string $filename): Result
    {
        // ファイルが存在しない場合は404
        if (!$this->fileExists($filename)) {
            throw new NotFoundException('404 error');
        }

        return $this->client->getObject([
            'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
            'Key' => $filename,
        ]);
    }

    /**
     * copy
     * S3上でのファイルコピー
     *
     * @author hagiwara
     */
    public function copy(string $oldFilename, string $newFilename): bool
    {
        // ファイルが存在しない
        if (!$this->fileExists($oldFilename)) {
            return false;
        }
        // 権限不足のExceptionはそのまま出す
        $this->client->copyObject([
            'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
            'Key' => $newFilename,
            'CopySource' => Configure::read('ContentsFile.Setting.S3.bucket') . '/' . $oldFilename,
        ]);

        return true;
    }

    /**
     * delete
     * S3上でのファイル削除
     *
     * @author hagiwara
     */
    public function delete(string $filename): bool
    {
        // 削除するファイルが存在しない
        if (!$this->fileExists($filename)) {
            return false;
        }
        // 権限不足のExceptionはそのまま出す
        $this->client->deleteObject([
            'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
            'Key' => $filename,
        ]);

        return true;
    }

    /**
     * deleteRecursive
     * S3上でのファイル削除(再帰的)
     *
     * @author hagiwara
     */
    public function deleteRecursive(string $dirname): bool
    {
        // $dirnameで消す単位は最低でもIDなので文字列内に数値のディレクトリがあることをチェックする
        if (!preg_match('#/[0-9]+/#', $dirname)) {
            return false;
        }
        $deleteFileLists = $this->getFileList($dirname);

        if (!empty($deleteFileLists)) {
            foreach ($deleteFileLists as $deleteDirInfo) {
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
     *
     * @author hagiwara
     */
    public function move(string $oldFilename, string $newFilename): bool
    {
        // 移動するファイルが存在しない
        if (!$this->fileExists($oldFilename)) {
            return false;
        }
        // 失敗時はException
        return $this->copy($oldFilename, $newFilename) && $this->delete($oldFilename);
    }

    /**
     * fileExists
     * S3上でのファイルの存在チェック(ディレクトリ存在チェックも兼)
     *
     * @author hagiwara
     */
    public function fileExists(string $filename): bool
    {
        return !empty($this->getFileList($filename));
    }

    /**
     * getFileList
     * 特定ディレクトリ内のfileの一覧取得
     *
     * @author hagiwara
     */
    public function getFileList(string $dirname): array
    {
        return $this->client->listObjects([
            'Bucket' => Configure::read('ContentsFile.Setting.S3.bucket'),
            'Prefix' => $dirname,
        ])->get('Contents');
    }
}
