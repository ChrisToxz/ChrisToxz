<?php

namespace App\Readers;

use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

class FeedReader
{

    public $reader, $spreadsheet, $worksheet;
    public $maxCell, $header_range, $products;
    public $exchange, $weeknum;


    public function __construct($file)
    {
        $this->reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $this->spreadsheet = $this->reader->load($file);
        $this->worksheet = $this->spreadsheet->getActiveSheet();
    }

    public function validate()
    {
        // headers check
        $this->header_range = $this->worksheet
            ->rangeToArray(
                Config::get('feedreader.headersRange'),     // The worksheet range that we want to retrieve
                NULL,        // Value that should be returned for empty cells
                TRUE,        // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
                TRUE,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
                TRUE         // Should the array be indexed by cell row and cell column
            );

        if ($this->header_range[Config::get('feedreader.headersRow')] != Config::get('feedreader.correctHeaders')) {
            throw ValidationException::withMessages(['file' => 'The headers are not as expected, please check and try again.']);
        }
        $this->getData();
        return true;
    }

    public function getData()
    {
        $this->maxCell = $this->worksheet->getHighestRow();

        $this->exchange = $this->worksheet->getCell('J2');
        $this->weeknum = $this->worksheet->getCell('A2')->getCalculatedValue();
        $this->products = $this->getProducts();
    }

    public function getProducts()
    {
        $data = $this->worksheet
            ->rangeToArray(
                Config::get('feedreader.priceRange') . $this->maxCell,
                NULL,
                TRUE,
                TRUE,
                TRUE
            );

        foreach($data as $key=>$product){
            for ($i = 'B'; $i !== 'I'; $i++){
                if($product[$i] == '#N/A' OR empty($product[$i])){
                    $data[$key][$i] = '0.00';
                }
            }
        }

        return $data;
    }

}