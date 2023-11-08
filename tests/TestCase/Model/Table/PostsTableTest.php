<?php
declare(strict_types=1);

namespace ContentsFile\Test\TestCase\Model\Table;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;
use ContentsFile\Test\App\Model\Table\PostsTable;

/**
 * App\Model\Table\PostsTable Test Case
 */
class PostsTableTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    protected array $fixtures = [
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
        $this->fileDir = dirname(dirname(dirname(dirname(__FILE__)))) . '/test_app/App/files/';
        Configure::write('ContentsFile.Setting', [
            'type' => 'normal',
            'Normal' => [
                'tmpDir' => $this->tmpDir,
                'fileDir' => $this->fileDir,
            ],
        ]);

        $this->Posts = new PostsTable();

        $this->demoFileDir = dirname(dirname(dirname(dirname(__FILE__)))) . '/test_app/App/demo/';
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Posts);

        //不要なディレクトリは削除する必要がある
        $this->removeDir($this->fileDir . 'Posts/');

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

        $fileinfo = [
            'model' => 'Posts',
            'model_id' => null,
            'field_name' => 'file',
            'file_name' => 'demo1.png',
            'file_content_type' => 'image/png',
            'file_size' => filesize($demo_filepath),
            'file_error' => (int)0,
            'tmp_file_name' => $rand,
        ];

        $entity = $this->Posts->newEntity([]);
        $entity->name = 'test';

        $entity->contents_file_file = $fileinfo;
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
            'file_content_type' => 'image/png',
            'file_size' => (string)filesize($demo_filepath),
            'file_random_path' => null,
        ];

        $this->assertEquals($check_data->contents_file_file, $assert_data);

        //ファイルが指定の個所にアップロードされており、同一ファイルか
        $uploaded_filepath = $this->fileDir . '/Posts/' . $last_id . '/file';
        $this->assertTrue(file_exists($uploaded_filepath));

        $origin_fp = fopen($demo_filepath, 'r');
        $origin_cont = fread($origin_fp, filesize($demo_filepath));
        fclose($origin_fp);

        $upload_fp = fopen($uploaded_filepath, 'r');
        $upload_cont = fread($upload_fp, filesize($uploaded_filepath));
        fclose($upload_fp);

        $this->assertEquals($origin_cont, $upload_cont);
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

        $fileinfo = [
            'model' => 'Posts',
            'model_id' => null,
            'field_name' => 'img',
            'file_name' => 'demo1.png',
            'file_content_type' => 'image/png',
            'file_size' => filesize($demo_filepath),
            'file_error' => (int)0,
            'tmp_file_name' => $rand,
        ];

        $entity = $this->Posts->newEntity([]);
        $entity->name = 'test';

        $entity->contents_file_img = $fileinfo;
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
            'file_content_type' => 'image/png',
            'file_size' => (string)filesize($demo_filepath),
            'file_random_path' => null,
        ];

        $this->assertEquals($check_data->contents_file_img, $assert_data);

        //ファイルが指定の個所にアップロードされており、同一ファイルか
        $uploaded_filepath = $this->fileDir . '/Posts/' . $last_id . '/img';
        $this->assertTrue(file_exists($uploaded_filepath));

        $origin_fp = fopen($demo_filepath, 'r');
        $origin_cont = fread($origin_fp, filesize($demo_filepath));
        fclose($origin_fp);

        $upload_fp = fopen($uploaded_filepath, 'r');
        $upload_cont = fread($upload_fp, filesize($uploaded_filepath));
        fclose($upload_fp);

        $this->assertEquals($origin_cont, $upload_cont);

        //リサイズ画像が上がっているか
        $resize_filepath1 = $this->fileDir . '/Posts/' . $last_id . '/contents_file_resize_img/300_0_normal';
        $resize_filepath2 = $this->fileDir . '/Posts/' . $last_id . '/contents_file_resize_img/300_400_normal';
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
     * 再帰的にディレクトリを削除する。
     *
     * @param string $dir ディレクトリ名（フルパス）
     *
     * http://blog3.logosware.com/archives/624
     */
    private function removeDir($dir)
    {
        $cnt = 0;

        $handle = opendir($dir);
        if (!$handle) {
            return;
        }

        while (($item = readdir($handle)) !== false) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                // 再帰的に削除
                $cnt = $cnt + $this->removeDir($path);
            } else {
                // ファイルを削除
                unlink($path);
            }
        }
        closedir($handle);

        // ディレクトリを削除
        if (!rmdir($dir)) {
            return;
        }
    }
}
