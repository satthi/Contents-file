<?php
declare(strict_types=1);

namespace ContentsFile\Validation;

use Cake\Validation\Validation;
use Laminas\Diactoros\UploadedFile;

class ContentsFileValidation extends Validation
{
    /**
     * checkMaxSize
     *
     * @param \Laminas\Diactoros\UploadedFile $value
     * @param string|int $max
     * @param mixed $context
     * @return bool
     */
    public static function checkMaxSize(UploadedFile $value, int|string $max, mixed $context): bool
    {
        $maxValue = self::calcFileSizeUnit($max);

        return $maxValue >= $value->getSize();
    }

    /**
     * uploadMaxSizeCheck
     *
     * @param \Laminas\Diactoros\UploadedFile $value
     * @param mixed $context
     * @return bool
     */
    public static function uploadMaxSizeCheck(UploadedFile $value, mixed $context): bool
    {
        return $value->getError() != UPLOAD_ERR_INI_SIZE;
    }

    /**
     * Calculate file size by unit
     *
     * e.g.) 100KB -> 1024000
     *
     * @param string|int $size
     * @return int|bool
     */
    private static function calcFileSizeUnit(int|string $size): int|bool
    {
        $units = ['K', 'M', 'G', 'T'];
        $byte = 1024;

        if (is_numeric($size) || is_int($size)) {
            return $size;
        } elseif (is_string($size) && preg_match('/^([0-9]+(?:\.[0-9]+)?)(' . implode('|', $units) . ')B?$/i', $size, $matches)) {
            return $matches[1] * pow($byte, array_search($matches[2], $units) + 1);
        }

        return false;
    }

    /**
     * checkExtension
     * 拡張子のチェック
     *
     * @param \Laminas\Diactoros\UploadedFile $value
     * @param array $extensions
     * @return bool
     */
    public static function checkExtension(UploadedFile $value, array $extensions = ['gif', 'jpeg', 'png', 'jpg']): bool
    {
        $check = $value->getClientFilename();
        if (is_null($check)) {
            return true;
        }

        $checkExtension = strtolower(pathinfo($check, PATHINFO_EXTENSION));
        foreach ($extensions as $extension) {
            if ($checkExtension === strtolower($extension)) {
                return true;
            }
        }

        return false;
    }

    /* ドラッグアンドドロップアップロード専用  @Todo: 後*/
    /**
     * extensionDd
     * 拡張子のチェック
     *
     * @param string $value
     * @param array $extensions
     * @param string $filenameField
     * @param mixed $context
     * @return bool
     */
    public static function extensionDd(string $value, array $extensions, string $filenameField, mixed $context): bool
    {
        // チェックに必要なフィールドがない
        if (!array_key_exists($filenameField, $context['data'])) {
            return false;
        }
        $filename = $context['data'][$filenameField];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        foreach ($extensions as $value) {
            if ($extension === strtolower($value)) {
                return true;
            }
        }

        return false;
    }
}
