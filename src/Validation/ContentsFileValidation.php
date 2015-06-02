<?php

namespace ContentsFile\Validation;

use Cake\Validation\Validation;

class ContentsFileValidation extends Validation
{
    public static function checkMaxSize ($value, $max, $context){
        $max_value = ContentsFileValidation::__calcFileSizeUnit($max);
        return $max_value >= $value['size'];
    }
    
    
    public static function uploadMaxSizeCheck ($value,$context){
       return $value['error'] != UPLOAD_ERR_INI_SIZE;
    }
    
    /**
     * Calculate file size by unit
     *
     * e.g.) 100KB -> 1024000
     *
     * @param $size mixed
     * @return int file size
     */
    private static function __calcFileSizeUnit($size) {
        $units = ['K', 'M', 'G', 'T'];
        $byte = 1024;

        if (is_numeric($size) || is_int($size)) {
            return $size;
        } else if (is_string($size) && preg_match('/^([0-9]+(?:\.[0-9]+)?)(' . implode('|', $units) . ')B?$/i', $size, $matches)) {
            return $matches[1] * pow($byte, array_search($matches[2], $units) + 1);
        }

        return false;
    }
    
}