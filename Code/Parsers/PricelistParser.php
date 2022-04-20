<?php

namespace App\Parsers;

use App\Helpers\GoodZipArchive;
use App\Models\Feed;
use Carbon\Carbon;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PricelistParser
{

    public $reader, $spreadsheet, $worksheet;
    public $maxCell, $products;

    public $_productColumn, $_productStartRow;


    public function __construct()
    {
        $file = storage_path(Config::get('pricelist.template'));
        $this->reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $this->spreadsheet = $this->reader->load($file);
        $this->worksheet = $this->spreadsheet->getActiveSheet();
        $this->_productColumn = Config::get('pricelist.productColumn');
        $this->_productStartRow = Config::get('pricelist.productStartRow');
        $this->getData();
    }

    public function getData()
    {
        $this->maxCell = $this->worksheet->getHighestDataRow();
        $this->products = $this->getProducts();
    }

    public function getProducts()
    {
        $data = $this->worksheet
            ->rangeToArray(
                $this->_productColumn . $this->_productStartRow . ':' .
                $this->_productColumn . $this->maxCell,
                NULL,
                TRUE,
                TRUE,
                TRUE
            );
        return $data;
    }

    public function generatePricelist(Feed $feed)
    {
        $fullpath = '\\pricelists\\Week ' . $feed->week;
        Storage::makeDirectory($fullpath);
        $prices = $feed->prices->toArray();


        foreach (Config::get('pricelist.levels') as $level) {

            Storage::makeDirectory($fullpath . '/' . $level);
            foreach (Config::get('pricelist.currencies') as $currency) {

                $this->worksheet->getCell(Config::get('pricelist.currencyCell'))->setValue(Config::get('pricelist.' . $currency));
                $this->worksheet->getCell(Config::get('pricelist.weekCell'))->setValue($feed->week);
                $this->worksheet->getCell(Config::get('pricelist.dateCell'))->setValue(Carbon::now()->toDateString());

                foreach ($this->products as $row => $product) {
                    // TODO: Somethings bugs here..


                    if (!empty($product[$this->_productColumn])) {
                        ini_set('max_execution_time', 180);
                        $key = array_search($product[$this->_productColumn], array_column($prices, 'sku'));
                        if ($key !== TRUE) {
                            $value = ($currency == "EUR") ? $feed->prices[$key]->getEuro($feed->prices[$key]->$level) : $feed->prices[$key]->$level;
                            $this->worksheet->getCell(Config::get('pricelist.priceColumn') . $row)->setValue($value);
                        } else {
                            $value = Config::get('pricelist.noprice');
                            $this->worksheet->mergeCells(Config::get('pricelist.priceColumn') . $row . ":" . Config::get('pricelist.remarkColumn') . $row);
                            $this->worksheet->getCell(Config::get('pricelist.priceColumn') . $row)->setValue($value);
                        }

                    }
                }

                $this->spreadsheet->getProperties()
                    ->setCreator('xxx')
                    ->setTitle('xxx')
                    ->setSubject("Week: " . $feed->week)
                    ->setDescription('xxx');

                $this->spreadsheet->getActiveSheet()->getHeaderFooter()
                    ->setOddHeader('&C&H');

                $this->spreadsheet->getActiveSheet()->getPageSetup()->setPrintArea('A1:A1');
                $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, 'Xlsx');
                ob_start();
                $writer->save('php://output');
                $content = ob_get_contents();
                ob_end_clean();
                $file = "{$fullpath}/{$level}/xxx";
                Storage::disk('local')->put($file, $content);

            }

        }
        $zippath = storage_path('app/public'.$fullpath.'.zip');
        new GoodZipArchive(storage_path('app/'.$fullpath), $zippath);

        return $fullpath.'.zip';
    }
}