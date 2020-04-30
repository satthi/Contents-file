<?php
namespace ContentsFile\Test\TestCase\Validation;

use Cake\TestSuite\TestCase;
use ContentsFile\Validation\ContentsFileValidation;
use Laminas\Diactoros\UploadedFile;

/**
 * Test Case for Validation Class
 *
 */
class ContentsFileValidationTest extends TestCase
{

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * test_checkMaxSize method
     *
     * @return void
     */
    public function test_checkMaxSize()
    {
        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_OK);
        // $value['size'] = 2000000;
        $this->assertTrue(ContentsFileValidation::checkMaxSize($fp,'2M',[]));
        $this->assertFalse(ContentsFileValidation::checkMaxSize($fp,'1M',[]));
    }

    /**
     * test_uploadMaxSizeCheck method
     *
     * @return void
     */
    public function test_uploadMaxSizeCheck()
    {
        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_INI_SIZE);
        $this->assertFalse(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));

        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_OK);
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));

        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_FORM_SIZE);
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));

        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_PARTIAL);
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));

        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_NO_FILE);
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));

        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_NO_TMP_DIR);
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));

        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_CANT_WRITE);
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));

        $fp = new UploadedFile('dummy', 2000000, UPLOAD_ERR_EXTENSION);
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($fp,[]));
    }
}
