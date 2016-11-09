<?php
namespace ContentsFile\Test\TestCase\Model\Table;

use ContentsFile\Test\App\Model\Table\PostsTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use Cake\Core\Configure;
use ContentsFile\Aws\S3;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\Fixture\FixtureManager;


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
    public $fixtures = [
        'plugin.contents_file.posts',
        'plugin.contents_file.attachments',
    ];


    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->tmpDir = dirname(dirname(dirname(dirname(__FILE__)))) . '/test_app/App/tmp/';
        Configure::write('ContentsFile.Setting', [
            'type' => 's3',
            'S3' => [
                // Key/Secretをコミットはできないので自動テストは走らせない
                'key' => 'KEY',
                'secret' => 'SECRET',
                'bucket' => 'contents-file-dev',
                'tmpDir' => 'contents_file_test/tmp',
                'fileDir' => 'contents_file_test/file',
                'workingDir' => $this->tmpDir,
            ]
        ]);

        $this->connection = ConnectionManager::get('test');
        $this->Posts = new PostsTable([
            'alias' => 'Posts',
            'table' => 'posts',
            'connection' => $this->connection
        ]);

        //fixtureManagerを呼び出し、fixtureを実行する
        $this->fixtureManager = new FixtureManager();
        $this->fixtureManager->fixturize($this);
        $this->fixtureManager->loadSingle('Posts');
        $this->fixtureManager->loadSingle('Attachments');

        $this->demoFileDir = dirname(dirname(dirname(dirname(__FILE__)))) . '/test_app/App/demo/';

        $this->S3 = new S3();

    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Posts);

        //不要なディレクトリは削除する必要がある
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
        $rand = Security::hash(rand());
        copy($demo_filepath , $this->tmpDir . $rand);

        $fileinfo = [
            'name' => 'demo1.png',
            'type' => 'image/png',
            'tmp_name' => $this->tmpDir . $rand,
            'error' => 0,
            'size' => filesize($demo_filepath)
        ];

        $entity = $this->Posts->newEntity();
        $data = [
            'name' => 'text',
            'file' => $fileinfo,
        ];

        $entity =  $this->Posts->patchEntity($entity, $data);

        $this->assertTrue((bool) $this->Posts->save($entity));

        //保存データのチェック
        $last_id = $entity->id;
        $check_data = $this->Posts->get($last_id);

        //fileについてデータが正常に取得できているかどうか
        $assert_data = [
            'model' => 'Posts',
            'model_id' =>  $last_id,
            'field_name' => 'file',
            'file_name' => 'demo1.png',
            'file_content_type' => 's3',
            'file_size' => (string) filesize($demo_filepath),
        ];

        $this->assertTrue($check_data->contents_file_file === $assert_data);

        $s3_file = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/file');
        $s3_file_cont = $s3_file['Body']->getContents();

        //ファイルが指定の個所にアップロードされており、同一ファイルか

        $origin_fp = fopen($demo_filepath, 'r');
        $origin_cont = fread($origin_fp, filesize($demo_filepath));
        fclose($origin_fp);

        $this->assertEquals($origin_cont , $s3_file_cont);
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
        $rand = Security::hash(rand());
        copy($demo_filepath , $this->tmpDir . $rand);

        $fileinfo = [
            'name' => 'demo1.png',
            'type' => 'image/png',
            'tmp_name' => $this->tmpDir . $rand,
            'error' => 0,
            'size' => filesize($demo_filepath)
        ];

        $entity = $this->Posts->newEntity();
        $data = [
            'name' => 'text',
            'img' => $fileinfo,
        ];

        $entity =  $this->Posts->patchEntity($entity, $data);

        $this->assertTrue((bool) $this->Posts->save($entity));

        //保存データのチェック
        $last_id = $entity->id;
        $check_data = $this->Posts->get($last_id);

        //fileについてデータが正常に取得できているかどうか
        $assert_data = [
            'model' => 'Posts',
            'model_id' =>  $last_id,
            'field_name' => 'img',
            'file_name' => 'demo1.png',
            'file_content_type' => 's3',
            'file_size' => (string) filesize($demo_filepath),
        ];

        $this->assertTrue($check_data->contents_file_img === $assert_data);

        $s3_file = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/img');
        $s3_file_cont = $s3_file['Body']->getContents();

        //ファイルが指定の個所にアップロードされており、同一ファイルか

        $origin_fp = fopen($demo_filepath, 'r');
        $origin_cont = fread($origin_fp, filesize($demo_filepath));
        fclose($origin_fp);

        $this->assertEquals($origin_cont , $s3_file_cont);

        //リサイズ画像が上がっているか
        $resize_filepath1 = $this->tmpDir . 'Posts_' . $last_id . '_300_0';
        $resize_filepath2 = $this->tmpDir . 'Posts_' . $last_id . '_300_400';

        // S3からファイルを落としてくる
        $s3_resize_file1 = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/contents_file_resize_img/300_0');
        $s3_resize_file2 = $this->S3->download('contents_file_test/file/Posts/' . $last_id . '/contents_file_resize_img/300_400');

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
        $this->assertEquals($image1_x , 300);
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
