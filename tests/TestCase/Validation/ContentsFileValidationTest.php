<?php
namespace ContentsFile\Test\TestCase\Validation;

use Cake\TestSuite\TestCase;
use ContentsFile\Validation\ContentsFileValidation;

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
        $value['size'] = 2000000;
        $this->assertTrue(ContentsFileValidation::checkMaxSize($value,'2M',[]));
        $this->assertFalse(ContentsFileValidation::checkMaxSize($value,'1M',[]));
    }

    /**
     * test_uploadMaxSizeCheck method
     *
     * @return void
     */
    public function test_uploadMaxSizeCheck()
    {
        $value['error'] = UPLOAD_ERR_INI_SIZE;
        $this->assertFalse(ContentsFileValidation::uploadMaxSizeCheck($value,[]));

        $value['error'] = UPLOAD_ERR_OK;
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($value,[]));

        $value['error'] = UPLOAD_ERR_FORM_SIZE;
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($value,[]));

        $value['error'] = UPLOAD_ERR_PARTIAL;
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($value,[]));

        $value['error'] = UPLOAD_ERR_NO_FILE;
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($value,[]));

        $value['error'] = UPLOAD_ERR_NO_TMP_DIR;
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($value,[]));

        $value['error'] = UPLOAD_ERR_CANT_WRITE;
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($value,[]));

        $value['error'] = UPLOAD_ERR_EXTENSION;
        $this->assertTrue(ContentsFileValidation::uploadMaxSizeCheck($value,[]));
    }
}
