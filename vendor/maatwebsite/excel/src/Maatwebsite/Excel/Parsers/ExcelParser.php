<?php namespace Maatwebsite\Excel\Parsers;

use Config;
use Carbon\Carbon;
use PHPExcel_Cell;
use PHPExcel_Shared_Date;
use Illuminate\Support\Str;
use PHPExcel_Style_NumberFormat;
use Maatwebsite\Excel\Collections\RowCollection;
use Maatwebsite\Excel\Collections\CellCollection;
use Maatwebsite\Excel\Collections\SheetCollection;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;

/**
 *
 * LaravelExcel Excel Parser
 *
 * @category   Laravel Excel
 * @version    1.0.0
 * @package    maatwebsite/excel
 * @copyright  Copyright (c) 2013 - 2014 Maatwebsite (http://www.maatwebsite.nl)
 * @author     Maatwebsite <info@maatwebsite.nl>
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt    LGPL
 */
class ExcelParser {

    /**
     * If file is parsed
     * @var boolean
     */
    public $isParsed = false;

    /**
     * Reader object
     * @var LaravelExcelReader
     */
    protected $reader;

    /**
     * Excel object
     * @var PHPExcel
     */
    protected $excel;

    /**
     * Worksheet object
     * @var LaravelExcelWorksheet
     */
    protected $worksheet;

    /**
     * Row object
     * @var PHPExcel_Worksheet_Row
     */
    protected $row;

    /**
     * Cell object
     * @var PHPExcel_Cell
     */
    protected $cell;

    /**
     * Indices
     * @var array
     */
    protected $indices;

    /**
     * Columns we want to fetch
     * @var array
     */
    protected $columns = array();

    /**
     * Row counter
     * @var integer
     */
    protected $currentRow = 1;

    /**
     * Default startrow
     * @var integer
     */
    protected $defaultStartRow = 1;

    /**
     * Construct excel parser
     * @param LaravelExcelReader $reader
     * @return  void
     */
    public function  __construct($reader)
    {
        $this->reader = $reader;
        $this->excel = $reader->excel;
    }

    /**
     *  Parse the file
     *  @param array $columns
     *  @return SheetCollection
     */
    public function parseFile($columns = array())
    {
        // Init new sheet collection
        $workbook = new SheetCollection();

        // Set the selected columns
        $this->setSelectedColumns($columns);

        // If not parsed yet
        if(!$this->isParsed)
        {
            // Set worksheet count
            $this->w = 0;

            // Loop through the worksheets
            foreach($this->excel->getWorksheetIterator() as $this->worksheet)
            {
                // Parse the worksheet
                $worksheet = $this->parseWorksheet();

                // If multiple sheets
                if($this->parseAsMultiple())
                {
                    // Push every sheet
                    $workbook->push($worksheet);
                }
                else
                {
                    // Ignore the sheet collection
                    $workbook = $worksheet;
                    break;
                }
                $this->w++;
            }
        }

        $this->isParsed = true;

        // Return itself
        return $workbook;
    }

    /**
     * Check if we want to parse it as multiple sheets
     * @return boolean
     */
    protected function parseAsMultiple()
    {
        return $this->excel->getSheetCount() > 1 || Config::get('excel::import.force_sheets_collection', false);
    }

    /**
     * Parse the worksheet
     * @return RowCollection
     */
    protected function parseWorksheet()
    {
        // Set the active worksheet
        $this->excel->setActiveSheetIndex($this->w);

        // Get the worksheet name
        $title = $this->excel->getActiveSheet()->getTitle();

        // Fetch the labels
        $this->indices = $this->reader->hasHeading() ? $this->getIndices() : array();

        // Parse the rows
        return $this->parseRows();
    }

    /**
     *  Get the indices
     *  @return array
     */
    protected function getIndices()
    {
        // Fetch the first row
        $this->row = $this->worksheet->getRowIterator(1)->current();

        // Set empty labels array
        $this->indices = array();

        // Loop through the cells
        foreach ($this->row->getCellIterator() as $this->cell)
        {
            // Set labels
            if(Config::get('excel::import.to_ascii', true))
            {
                $this->indices[] = Str::slug($this->cell->getValue(), $this->reader->getSeperator());
            }
            else
            {
                $this->indices[] = strtolower(str_replace(array(' '), $this->reader->getSeperator(), $this->cell->getValue()));
            }
        }

        // Return the labels
        return $this->indices;
    }

    /**
     *  Parse the rows
     *  @return RowCollection
     */
    protected function parseRows()
    {
        // Set empty parsedRow array
        $parsedRows = new RowCollection();

        // Get the startrow
        $startRow = $this->getStartRow();

        // Loop through the rows inside the worksheet
        foreach ($this->worksheet->getRowIterator($startRow) as $this->row)
        {
            // Limit the results when needed
            if($this->hasReachedLimit())
                break;

            // Push the parsed cells inside the parsed rows
            $parsedRows->push($this->parseCells());

            // Count the rows
            $this->currentRow++;
        }

        // Return the parsed array
        return $parsedRows;
    }

    /**
     * Get the startrow
     * @return integer
     */
    protected function getStartRow()
    {
        // Set default start row
        $startRow = $this->defaultStartRow;

        // If the reader has a heading, skip the first row
        if($this->reader->hasHeading())
            $startRow++;

        // Get the amount of rows to skip
        $skip = $this->reader->getSkip();

        // If we want to skip rows, add the amount of rows
        if($skip > 0)
            $startRow = $startRow + $skip;

        // Return the startrow
        return $startRow;
    }

    /**
     * Check for the limit
     * @return boolean
     */
    protected function hasReachedLimit()
    {
        // Get skip
        $limit = $this->reader->getLimit();

        // If we have a limit, check if we hit this limit
        return $limit && $this->currentRow > $limit ? true : false;
    }

    /**
     * Parse the cells of the given row
     * @return CellCollection
     */
    protected function parseCells()
    {
        $i = 0;
        $parsedCells = array();

        // Set the cell iterator
        $cellIterator = $this->row->getCellIterator();

        // Ignore empty cells if needed
        $cellIterator->setIterateOnlyExistingCells($this->reader->needsIgnoreEmpty());

        // Foreach cells
        foreach ($cellIterator as $this->cell) {

            // Check how we need to save the parsed array
            $index = ($this->reader->hasHeading() && isset($this->indices[$i])) ? $this->indices[$i] : $this->getIndexFromColumn();

            // Check if we want to select this column
            if($this->cellNeedsParsing($index) )
            {
                // Set the value
                $parsedCells[$index] = $this->parseCell($index);

            }

            $i++;
        }

        // Return array with parsed cells
        return CellCollection::make($parsedCells);
    }

    /**
     * Parse a single cell
     * @param  integer $index
     * @return string
     */
    protected function parseCell($index)
    {
        // If the cell is a date time
        if($this->cellIsDate($index))
        {
            // Parse the date
            return $this->parseDate();
        }

        // Check if we want calculated values or not
        elseif($this->reader->needsCalculation())
        {
            // Get calculated value
            return $this->getCalculatedValue();
        }
        else
        {
            // Get real value
            return $this->getCellValue();
        }
    }

    /**
     * Return the cell value
     * @return string
     */
    protected function getCellValue()
    {
        $value = $this->cell->getValue();
        return $this->encode($value);
    }

    /**
     * Get the calculated value
     * @return string
     */
    protected function getCalculatedValue()
    {
        $value = $this->cell->getCalculatedValue();
        return $this->encode($value);
    }

    /**
     * Encode with iconv
     * @param  string $value
     * @return string
     */
    protected function encode($value)
    {
        // Get input and output encoding
        list($input, $output) = array_values(Config::get('excel::import.encoding', array('UTF-8', 'UTF-8')));

        // If they are the same, return the value
        if($input == $output)
            return $value;

        // Encode
        return iconv($input, $output, $value);
    }

    /**
     * Parse the date
     * @return Carbon\Carbon|string
     */
    protected function parseDate()
    {
        // If the date needs formatting
        if($this->reader->needsDateFormatting())
        {
            // Parse the date with carbon
            return $this->parseDateAsCarbon();
        }
        else
        {
            // Parse the date as a normal string
            return $this->parseDateAsString();
        }
    }

    /**
     * Parse and return carbon object or formatted time string
     * @return Carbon\Carbon
     */
    protected function parseDateAsCarbon()
    {
        // Convert excel time to php date object
        $date = PHPExcel_Shared_Date::ExcelToPHPObject($this->cell->getCalculatedValue())->format('Y-m-d H:i:s');

        // Parse with carbon
        $date = Carbon::parse($date);

        // Format the date if wanted
        return $this->reader->getDateFormat() ? $date->format($this->reader->getDateFormat()) : $date;
    }

    /**
     * Return date string
     * @return string
     */
    protected function parseDateAsString()
    {
        //Format the date to a formatted string
        return (string) PHPExcel_Style_NumberFormat::toFormattedString(
            $this->cell->getCalculatedValue(),
            $this->cell->getWorksheet()->getParent()
                ->getCellXfByIndex($this->cell->getXfIndex())
                ->getNumberFormat()
                ->getFormatCode()
        );
    }

    /**
     * Check if cell is a date
     * @param  integer $index
     * @return boolean
     */
    protected function cellIsDate($index)
    {
        // if is a date or if is a date column
        return PHPExcel_Shared_Date::isDateTime($this->cell) || in_array($index, $this->reader->getDateColumns());
    }

    /**
     * Check if cells needs parsing
     * @return array
     */
    protected function cellNeedsParsing($index)
    {
        // if no columns are selected or if the column is selected
        return !$this->hasSelectedColumns() || ($this->hasSelectedColumns() && in_array($index, $this->getSelectedColumns()));
    }

    /**
     * Get the cell index from column
     * @return integer
     */
    protected function getIndexFromColumn()
    {
        return PHPExcel_Cell::columnIndexFromString($this->cell->getColumn());
    }

    /**
     * Set selected columns
     * @param array $columns
     */
    protected function setSelectedColumns($columns = array())
    {
        // Set the columns
        $this->columns = $columns;
    }

    /**
     * Check if we have selected columns
     * @return boolean
     */
    protected function hasSelectedColumns()
    {
        return !empty($this->columns);
    }

    /**
     * Set selected columns
     * @param array $columns
     */
    protected function getSelectedColumns()
    {
        // Set the columns
        return $this->columns;

    }

}
