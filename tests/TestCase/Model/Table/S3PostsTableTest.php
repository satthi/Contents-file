<?php
declare(strict_types=1);

namespace ContentsFile\Test\TestCase\Model\Table;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use ContentsFile\Aws\S3;
use ContentsFile\Test\App\Model\Table\PostsTable;
use Laminas\Diactoros\UploadedFile;

/**
 * App\Model\Table\PostsTable Test Case
 */
class S3PostsTableTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    public array $fixtures = [
        'plugin.ContentsFile.Posts',
        'plugin.ContentsFile.Attachments',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = dirname(dirname(dirname(dirname(__FILE__)))) . '/test_app/App/tmp/';
        Configure::write('ContentsFile.Setting', [
            'type' => 's3',
            'S3' => [
                // Key/Secretをコミットはできないので自動テストは走らせない
                // 'key' => 'KEY',
                // 'secret' => 'SECRET',
                'key' => 'AKIATVTTUP5XTBC4LJEQ',
                'secret' => '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',
                'bucket' => 'cf-cake4-dev',
                'tmpDir' => 'contents_file_test/tmp',
                'fileDir' => 'contents_file_test/file',
                'workingDir' => $this->tmpDir,
            ],
        ]);

        $this->Posts = new PostsTable();
        $this->demoFileDir = dirname(dirname(dirname(dirname(__FILE__)))) . '/test_app/App/demo/';

        $this->S3 = new S3();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Posts);

        // 不要なディレクトリは削除する必要がある
        $this->deleteRecursive('contents_file_test');

        unset($this->S3);
        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function test_file保存通常()
    {
        //デモ画像をtmpディレクトリにコピーしておく
        $demo_filepath = $this->demoFileDir . 'demo1.png';
        $rand = Security::hash((string)rand());
        copy($demo_filepath, $this->tmpDir . $rand);

        $fileinfo = new UploadedFile(
            $this->tmpDir . $rand,
            filesize($demo_filepath),
            0,
            'demo1.png',
            'image/png'
        );

        $entity = $this->Posts->newEntity([]);
        $data = [
            'name' => 'text',
            'file' => $fileinfo,
        ];

        $entity = $this->Posts->patchEntity($entity, $data);
        $this->assertTrue((bool)$this->Posts->save($entity));

        //保存データのチェック
        $last_id = $entity->id;
        $check_data = $this->Posts->get($last_id);

        //fileについてデータが正常に取得できているかどうか
        $assert_data = [
            'model' => 'Posts',
            'model_id' => $last_id,
            'field_name' => 'file',
            'file_name' => 'demo1.png',
            'file_content_type' => 's3',
            'file_size' => (string)filesize($demo_filepath),
            'file_random_path' => null,
        ];

        $this->assertEquals($check_data->contents_file_file, $assert_data);

        $s3_file = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/file');
        $s3_file_cont = $s3_file['Body']->getContents();

        //ファイルが指定の個所にアップロードされており、同一ファイルか

        $origin_fp = fopen($demo_filepath, 'r');
        $origin_cont = fread($origin_fp, filesize($demo_filepath));
        fclose($origin_fp);

        $this->assertEquals($origin_cont, $s3_file_cont);
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function test_file保存リサイズ()
    {
        //デモ画像をtmpディレクトリにコピーしておく
        $demo_filepath = $this->demoFileDir . 'demo1.png';
        $rand = Security::hash((string)rand());
        copy($demo_filepath, $this->tmpDir . $rand);

        $fileinfo = new UploadedFile(
            $this->tmpDir . $rand,
            filesize($demo_filepath),
            0,
            'demo1.png',
            'image/png'
        );

        $entity = $this->Posts->newEntity([]);
        $data = [
            'name' => 'text',
            'img' => $fileinfo,
        ];

        $entity = $this->Posts->patchEntity($entity, $data);

        $this->assertTrue((bool)$this->Posts->save($entity));

        //保存データのチェック
        $last_id = $entity->id;
        $check_data = $this->Posts->get($last_id);

        //fileについてデータが正常に取得できているかどうか
        $assert_data = [
            'model' => 'Posts',
            'model_id' => $last_id,
            'field_name' => 'img',
            'file_name' => 'demo1.png',
            'file_content_type' => 's3',
            'file_size' => (string)filesize($demo_filepath),
            'file_random_path' => null,
        ];

        $this->assertEquals($check_data->contents_file_img, $assert_data);

        $s3_file = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/img');
        $s3_file_cont = $s3_file['Body']->getContents();

        //ファイルが指定の個所にアップロードされており、同一ファイルか

        $origin_fp = fopen($demo_filepath, 'r');
        $origin_cont = fread($origin_fp, filesize($demo_filepath));
        fclose($origin_fp);

        $this->assertEquals($origin_cont, $s3_file_cont);

        //リサイズ画像が上がっているか
        $resize_filepath1 = $this->tmpDir . 'Posts_' . $last_id . '_300_0';
        $resize_filepath2 = $this->tmpDir . 'Posts_' . $last_id . '_300_400';

        // S3からファイルを落としてくる
        $s3_resize_file1 = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/contents_file_resize_img/300_0_normal');
        $s3_resize_file2 = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/contents_file_resize_img/300_400_normal');

        $s3_resize_file1_cont = $s3_resize_file1['Body']->getContents();
        $s3_resize_file2_cont = $s3_resize_file2['Body']->getContents();

        $fp = fopen($resize_filepath1, 'w');
        fwrite($fp, $s3_resize_file1_cont);
        fclose($fp);

        $fp = fopen($resize_filepath2, 'w');
        fwrite($fp, $s3_resize_file2_cont);
        fclose($fp);

        $this->assertTrue(file_exists($resize_filepath1));
        $this->assertTrue(file_exists($resize_filepath2));

        $image1 = ImageCreateFromPNG($resize_filepath1);
        $image1_x = ImageSX($image1);
        //リサイズのチェック
        $this->assertEquals($image1_x, 300);
        ImageDestroy($image1);

        $image2 = ImageCreateFromPNG($resize_filepath2);
        $image2_x = ImageSX($image2);
        $image2_y = ImageSY($image2);
        //リサイズのチェック
        $this->assertTrue(
            ($image2_x == 300 && $image2_y <= 400) ||
            ($image2_x <= 300 && $image2_y == 400)
        );
        ImageDestroy($image2);
    }

    /**
     * deleteRecursive
     * テスト用のディレクトリ全消しをするので通常のロジックは使用しない
     *
     * @author hagiwara
     */
    private function deleteRecursive($dirname)
    {
        $deleteFileLists = $this->S3->getFileList($dirname);

        if (!empty($deleteFileLists)) {
            foreach ($deleteFileLists as $deleteDirInfo) {
                if (!$this->S3->delete($deleteDirInfo['Key'])) {
                    return false;
                }
            }
        }

        return true;
    }
}
