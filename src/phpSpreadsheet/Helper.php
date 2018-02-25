<?php

namespace yidas\phpSpreadsheet;

use Exception;

/**
 * PhpSpreadsheet Helper
 * 
 * @author      Nick Tsai <myintaer@gmail.com>
 * @version     1.1.1
 * @filesource 	PhpSpreadsheet <https://github.com/PHPOffice/PhpSpreadsheet>
 * @see         https://github.com/yidas/phpspreadsheet-helper
 * @example
 *  \yidas\phpSpreadsheet\Helper::newExcel()
 *      ->addRow(['ID', 'Name', 'Email'])
 *      ->addRows([
 *          ['1', 'Nick','myintaer@gmail.com'],
 *          ['2', 'Eric','eric@.....'],
 *      ])
 *      ->output('My Excel');
 */
class Helper
{
    /**
     * @var object Cached PhpSpreadsheet object
     */
    private static $_objSpreadsheet;
    
    /**
     * @var object Cached PhpSpreadsheet Sheet object
     */
    private static $_objSheet;

    /**
     * @var int Current row offset for the actived sheet
     */
    private static $_offsetRow;

    /**
     * @var int Current column offset for the actived sheet
     */
    private static $_offsetCol;

    /**
     * @var array Map of coordinates by keys
     */
    private static $_keyCoordinateMap;

    /**
     * @var array Map of column alpha by keys
     */
    private static $_keyColumnMap;

    /**
     * @var array Map of row number by keys
     */
    private static $_keyRowMap;

    /**
     * @var int Map of ranges by keys
     */
    private static $_keyRangeMap;

    /**
     * Extension list for reader
     */
    private static $_readerExtensions = [
        'Excel5' => '.xls',
        'Excel2003XML' => '.xls',
        'Excel2007' => '.xlsx',
        'OOCalc' => '.ods',
        'SYLK' => '.slk',
        'Gnumeric' => '.gnumeric',
        'CSV' => '.csv',
        'HTML' => '.html',
    ];

    /**
     * Extension list for writer
     */
    private static $_writerTypeInfo = [
        'Ods' => [
            'extension' => '.ods',
            'contentType' => 'application/vnd.oasis.opendocument.spreadsheet'
        ],
        'Xlsx' => [
            'extension' => '.xlsx',
            'contentType' => 'application/vnd.ms-excel'
        ],
        'Xls' => [
            'extension' => '.xls',
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ],
        'Html' => [
            'extension' => '.html',
            'contentType' => 'text/html'
        ],
        'Csv' => [
            'extension' => '.csv',
            'contentType' => 'text/csv'
        ],
    ];

    /** 
     * New or set an PhpSpreadsheet object
     * 
     * @param object|string $phpSpreadsheet PhpSpreadsheet object or filepath
     * @return self
     */
    public static function newSpreadsheet($phpSpreadsheet=NULL)
    {
        if (is_object($phpSpreadsheet)) {
            
            self::$_objSpreadsheet = &$phpSpreadsheet;
        } 
        elseif (is_string($phpSpreadsheet)) {

            self::$_objSpreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($phpSpreadsheet);
        }
        else {
            self::$_objSpreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        }
        
        return new static();
    }

    /** 
     * Get PhpSpreadsheet object from cache
     * 
     * @return object PhpSpreadsheet object
     */
    public static function getSpreadsheet()
    {
        return self::$_objSpreadsheet;
    }
    
    /** 
     * Reset cached PhpSpreadsheet sheet object and helper data
     * 
     * @return self
     */
    public static function resetSheet()
    {
        self::$_objSheet = NULL;
        self::$_offsetRow = 0;
        self::$_offsetCol = 1; // A1 => 1

        return new static();
    }

    /** 
     * Set an active PhpSpreadsheet Sheet
     * 
     * @param object|int $sheet PhpSpreadsheet sheet object or index number
     * @param string $title Sheet title
     * @return self
     */
    public static function setSheet($sheet=0, $title=NULL)
    {
        self::resetSheet();

        if (is_object($sheet)) {
            
            self::$_objSheet = &$sheet;
        } 
        elseif (is_numeric($sheet) && $sheet>=0 && self::$_objSpreadsheet) {

            /* Sheets Check */
            $sheetCount = self::$_objSpreadsheet->getSheetCount();
            if ($sheet >= $sheetCount) {
                for ($i=$sheetCount; $i <= $sheet; $i++) { 
                    self::$_objSpreadsheet->createSheet($i);
                }
            }
            // Select sheet
            self::$_objSheet = self::$_objSpreadsheet->setActiveSheetIndex($sheet);
        }
        else {
            throw new Exception("Invalid or empty PhpSpreadsheet Object for setting sheet", 400);
        }

        // Sheet Title
        if ($title) {
            self::$_objSheet->setTitle($title);
        }

        return new static();
    }

    /** 
     * Get PhpSpreadsheet Sheet object from cache
     * 
     * @return object PhpSpreadsheet Sheet object
     */
    public static function getSheet()
    {
        return self::$_objSheet;
    }

    /** 
     * Set the offset of rows for the actived PhpSpreadsheet Sheet
     * 
     * @param int $var The offset number
     * @return self
     */
    public static function setRowOffset($var=0)
    {
        self::$_offsetRow = (int)$var;
        
        return new static();
    }

    /** 
     * Get the offset of rows for the actived PhpSpreadsheet Sheet
     * 
     * @return int The offset number
     */
    public static function getRowOffset()
    {
        return self::$_offsetCol;
    }

    /** 
     * Set the offset of columns for the actived PhpSpreadsheet Sheet
     * 
     * @param int $var The offset number
     * @return self
     */
    public static function setColumnOffset($var=0)
    {
        self::$_offsetCol = (int)$var;
        
        return new static();
    }

    /**
     * Add a row to the actived sheet of PhpSpreadsheet
     * 
     * @param array $rowData 
     *  @param mixed|array Cell value | Data set 
     *   Data set key-value:
     *   @param int 'col' Column span for mergence
     *   @param int 'row' Row span for mergence
     *   @param int 'skip' Column skip counter
     *   @param string|int 'key' Cell key for index
     *   @param mixed 'value' Cell value
     * @return self
     */
    public static function addRow($rowData)
    {
        $sheetObj = self::validSheetObj();
        
        // Column pointer
        $posCol = self::$_offsetCol;

        // Next row
        self::$_offsetRow++;
        
        foreach ($rowData as $key => $value) {
            
            // Optional Cell
            if (is_array($value)) {
                
                // Options
                $colspan = isset($value['col']) ? $value['col'] : 1;
                $rowspan = isset($value['row']) ? $value['row'] : 1;
                $skip = isset($value['skip']) ? $value['skip'] : 1;
                $key = isset($value['key']) ? $value['key'] : NULL;
                $value = isset($value['value']) ? $value['value'] : NULL;

                $sheetObj->setCellValueByColumnAndRow($posCol, self::$_offsetRow, $value);

                // Merge handler
                if ($colspan>1 || $rowspan>1) {
                    $posColLast = $posCol;
                    $posCol = $posCol + $colspan - 1;
                    $posRow = self::$_offsetRow + $rowspan - 1;
                    $mergeVal = self::num2alpha($posColLast).self::$_offsetRow
                        . ':'
                        . self::num2alpha($posCol).$posRow;
                    $sheetObj->mergeCells($mergeVal);
                }

                // Save key Map
                if ($key) {
                    $startColumn = self::num2alpha($posCol);
                    $startCoordinate = $startColumn. self::$_offsetRow;
                    // Range Map
                    if (isset($mergeVal)) {
                        self::$_keyRangeMap[$key] = $mergeVal;
                        // Reset column coordinate
                        $startColumn = self::num2alpha($posColLast);
                        $startCoordinate = $startColumn. self::$_offsetRow;
                    } 
                    elseif ($skip > 1) {
                        self::$_keyRangeMap[$key] = $startCoordinate
                            . ':'
                            . self::num2alpha($posCol+($skip-1)) . self::$_offsetRow;
                    } 
                    else {
                        self::$_keyRangeMap[$key] = "{$startCoordinate}:{$startCoordinate}";
                    }
                    // Coordinate & col-row Map
                    self::$_keyCoordinateMap[$key] = $startCoordinate;
                    self::$_keyColumnMap[$key] = $startColumn;
                    self::$_keyRowMap[$key] = self::$_offsetRow;
                }

                // Skip option
                $posCol += $skip;

            } else {

                $sheetObj->setCellValueByColumnAndRow($posCol, self::$_offsetRow, $value);
                
                $posCol++;
            }
        }

        return new static();
    }

    /**
     * Add rows to the actived sheet of PhpSpreadsheet
     * 
     * @param array array of rowData for addRow()
     * @return self
     */
    public static function addRows($data)
    {
         foreach ($data as $key => $row) {

            self::addRow($row);
        }

        return new static();
    }

    /** 
     * Output an Excel file
     * 
     * @param string $filename
     * @param string $format
     */
    public static function output($filename='excel', $format='Xlsx')
    {
        $objPhpSpreadsheet = self::validExcelObj();

        // Create Writer first
        $objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($objPhpSpreadsheet, $format);

        /**
         * @todo Type Mapping
         */
        $inTypeList = isset(self::$_writerTypeInfo[$format]) ? true : false;
        $extension = ($inTypeList) ? self::$_writerTypeInfo[$format]['extension'] : '';
        $contentType = ($inTypeList) 
            ? self::$_writerTypeInfo[$format]['contentType'] 
            : 'application/octet-stream';

        // Redirect output to a client's web browser
        header("Content-Type: {$contentType}");
        header("Content-Disposition: attachment;filename=\"{$filename}{$extension}\"");
        header("Cache-Control: max-age=0");

        $objWriter->save('php://output');
        exit;
    }

    /**
     * Get rows from the actived sheet of PhpSpreadsheet
     * 
     * @param bool $toString All values from sheet to be string type
     * @param bool $options [
     *  row (int) Ended row number
     *  column (int) Ended column number
     *  timestamp (bool) Excel datetime to Unixtime
     *  timestampFormat (string) Format for date() when usgin timestamp
     *  ]
     * @return array Data of Spreadsheet
     */
    public static function getRows($toString=true, Array $options=[])
    {
        $worksheet = self::validSheetObj();

        // Options
        $defaultOptions = [
            'row' => NULL,
            'column' => NULL,
            'timestamp' => true,
            'timestampFormat' => 'Y-m-d H:i:s', // False would use Unixtime
        ];
        $options = array_replace($defaultOptions, $options);

        // Get the highest row and column numbers referenced in the worksheet
        $highestRow = ($options['row']) ?: $worksheet->getHighestRow();
        $highestColumn = ($options['column']) ?: self::alpha2num($worksheet->getHighestColumn());

        // Fetch data from the sheet
        $data = [];
        $pointerRow = &$data;
        for ($row = 1; $row <= $highestRow; ++$row) {
            $pointerColumn = &$pointerRow[];
            for ($col = 1; $col <= $highestColumn; ++$col) {
                $cell = $worksheet->getCellByColumnAndRow($col, $row);
                $value = $cell->getValue();
                // Timestamp option
                if ($options['timestamp'] && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                    $value = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($value);
                    // Timestamp Format option
                    $value = ($options['timestampFormat']) 
                        ? date($options['timestampFormat'], $value) : $value;
                }
                $value = ($toString) ? (string)$value : $value;
                $pointerColumn[] = $value;
            }
        }

        return $data;
    }

    /**
     * Get Coordinate Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return string|array Coordinate string | Key-Coordinate array
     */
    public static function getCoordinateMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyCoordinateMap[$key]) ? self::$_keyCoordinateMap[$key] : NULL;
        } else {
            return self::$_keyCoordinateMap;
        }
    }

    /**
     * Get Column Alpha Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return string|array Column alpha string | Key-Coordinate array
     */
    public static function getColumnMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyColumnMap[$key]) ? self::$_keyColumnMap[$key] : NULL;
        } else {
            return self::$_keyColumnMap;
        }
    }

    /**
     * Get Row Number Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return int|array Row number | Key-Coordinate array
     */
    public static function getRowMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyRowMap[$key]) ? self::$_keyRowMap[$key] : NULL;
        } else {
            return self::$_keyRowMap;
        }
    }

    /**
     * Get Range Map by key or all from the actived sheet
     * 
     * @param string|int $key Key set by addRow()
     * @return string|array Range string | Key-Range array
     */
    public static function getRangeMap($key=NULL)
    {
        if ($key) {
            return isset(self::$_keyRangeMap[$key]) ? self::$_keyRangeMap[$key] : NULL;
        } else {
            return self::$_keyRangeMap;
        }
    }

    /**
     * Get Range of all actived cells from the actived sheet
     * 
     * @return string Range string
     */
    public static function getRangeAll()
    {
        $sheetObj = self::validSheetObj();
        
        return self::num2alpha(self::$_offsetCol). '1:'. $sheetObj->getHighestColumn(). $sheetObj->getHighestRow();
    }

    /**
     * Set WrapText for all cells or set by giving range to the actived sheet
     * 
     * @param string $range Cells range format
     * @param bool $value PhpSpreadsheet setWrapText() argument
     * @return self
     */
    public static function setWrapText($range=NULL, $value=true)
    {
        $sheetObj = self::validSheetObj();

        $range = ($range) ? $range : self::getRangeAll();

        $sheetObj->getStyle($range)
            ->getAlignment()
            ->setWrapText($value); 
        
        return new static();
    }

    /**
     * Set AutoSize for all cells or set by giving column range to the actived sheet
     * 
     * @param string $colAlphaStart Column Alpah of start
     * @param string $colAlphaEnd Column Alpah of end
     * @param bool $value PhpSpreadsheet AutoSize() argument
     * @return self
     */
    public static function setAutoSize($colAlphaStart=NULL, $colAlphaEnd=NULL, $value=true)
    {
        $sheetObj = self::validSheetObj();

        $colStart = ($colAlphaStart) ? self::alpha2num($colAlphaStart) : self::$_offsetCol;
        $colEnd = ($colAlphaEnd) 
            ? self::alpha2num($colAlphaEnd) 
            : self::alpha2num($sheetObj->getHighestColumn());

        foreach (range($colStart,$colEnd ) as $key => $colNum) {
            $sheetObj->getColumnDimension(self::num2alpha($colNum))->setAutoSize($value);
        }

        return new static();
    }

    /**
     * Number to Alpha
     * 
     * Optimizing from \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex()
     * 
     * @example
     *  1 => A, 27 => AA
     * @param int $n column number
     * @return string Excel column alpha
     */
    public static function num2alpha($n)
    {
        $n = $n - 1;
        $r = '';
        for ($i = 1; $n >= 0 && $i < 10; $i++) {
            $r = chr(0x41 + ($n % pow(26, $i) / pow(26, $i - 1))) . $r;
            $n -= pow(26, $i);
        }
        return $r;
    }

    /**
     * Alpha to Number
     * 
     * Optimizing from \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString()
     * 
     * @example
     *  A => 1, AA => 27 
     * @param int $n Excel column alpha
     * @return string column number
     */
    public static function alpha2num($a)
    {
        $r = 0;
        $l = strlen($a);
        for ($i = 0; $i < $l; $i++) {
            $r += pow(26, $i) * (ord($a[$l - $i - 1]) - 0x40);
        }
        return $r;
    }

    /**
     * Validate and return the selected PhpSpreadsheet Object
     * 
     * @param object $excelObj PhpSpreadsheet Object
     * @return object Cached object or given object
     */
    private static function validExcelObj($excelObj=NULL)
    {
        if (is_object($excelObj)) {

            return $excelObj;
        } 
        elseif (is_object(self::$_objSpreadsheet)) {

            return self::$_objSpreadsheet;
        } 
        else {
            
            throw new Exception("Invalid or empty PhpSpreadsheet Object", 400);
        }
    }

    /**
     * Validate and return the selected PhpSpreadsheet Sheet Object
     * 
     * @param object $excelObj PhpSpreadsheet Sheet Object
     * @return object Cached object or given object
     */
    private static function validSheetObj($sheetObj=NULL)
    {
        if (is_object($sheetObj)) {

            return $sheetObj;
        } 
        elseif (is_object(self::$_objSheet)) {

            return self::$_objSheet;
        } 
        elseif (is_object(self::$_objSpreadsheet)) {

            // Set to default sheet if is unset
            return self::setSheet()->getSheet();
        }
        else {
            
            throw new Exception("Invalid or empty PhpSpreadsheet Sheet Object", 400);
        }
    }
}
