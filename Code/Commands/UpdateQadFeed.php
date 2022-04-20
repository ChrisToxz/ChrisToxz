<?php

namespace App\Console\Commands;

use App\Models\Feed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UpdateQADFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update stock from SPC QAD Feed into JSON';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Feed Start!');
        $start = microtime(true);
        $options = array(
            'http' => array(
                'timeout' => 600,
            ),
        );
        Log::info(env('QADFEED'));
        $context = stream_context_create($options); $retour =
        $start_get_feed = microtime(true);
        $retour = @file_get_contents(env('QADFEED'), false, $context);
        $end_get_feed = microtime(true);
        $DOM = new \DOMDocument();
        @$DOM->loadHTML($retour);

        $tables = $DOM->getElementsByTagName('table');
        $rows = $tables->item(0)->getElementsByTagName('tr');

        // Get headers
        $headers = [];
        $cols = $rows[0]->getElementsByTagName('td');
        foreach($cols as $y=>$col){
            $headers[$y] = $col->nodeValue;
        }


        $products = [];
        $allowed_products = Feed::active()->prices('sku')->get();

        $start_for = microtime(true);
        foreach($rows as $x=>$row){
            // skipheaders
            if($x < 3){
                continue;
            }

            $cols = $row->getElementsByTagName('td');

            $sku = str_replace("(Top 30)", "", $cols[0]->nodeValue);

            // If product is not in our price DB

            if(!$allowed_products->contains('sku', $sku)){
                continue;
            }

            // OTW air
            preg_match('/^=(.*)/', $cols[12]->nodeValue, $output);
            $otw_air = array_sum(explode('+', $output[1]));
            // real sea date
            $sea_arrival = "";
            if($cols[11]->nodeValue){
                preg_match('/\d{1,4}([.\-\/])\d{1,2}([.\-\/])\d{1,4}/', $cols[11]->nodeValue, $output);
                $sea_arrival = $output[0];
            }

            //new to sell calculation
            $tosell = (int)$cols[6]->nodeValue - ((int)$cols[15]->nodeValue + (int)$cols[16]->nodeValue);


            // TODO: Proper loop
            preg_match('/^=(.*)/', $cols[43]->nodeValue, $output);
            $sell_current = (array_key_exists(0, $output)) ? array_sum(explode('+', $output[1])) : '0';
            preg_match('/^=(.*)/', $cols[44]->nodeValue, $output);
            $sell_1 = (array_key_exists(0, $output)) ? array_sum(explode('+', $output[1])) : '0';
            preg_match('/^=(.*)/', $cols[45]->nodeValue, $output);
            $sell_2 = (array_key_exists(0, $output)) ? array_sum(explode('+', $output[1])) : '0';
            preg_match('/^=(.*)/', $cols[46]->nodeValue, $output);
            $sell_3 = (array_key_exists(0, $output)) ? array_sum(explode('+', $output[1])) : '0';
            preg_match('/^=(.*)/', $cols[47]->nodeValue, $output);
            $sell_4 = (array_key_exists(0, $output)) ? array_sum(explode('+', $output[1])) : '0';
            preg_match('/^=(.*)/', $cols[48]->nodeValue, $output);
            $sell_5 = (array_key_exists(0, $output)) ? array_sum(explode('+', $output[1])) : '0';



            $products[$sku] = array(
                "type" => $cols[2]->nodeValue,
                "description" => $cols[5]->nodeValue,
                "stock" => (int)$cols[6]->nodeValue,
                "tosell" => $tosell,
                "order" => array(
                    "sea" => (int)$cols[9]->nodeValue,
                    "xx" => (int)$cols[27]->nodeValue,
                    "xx" => (int)$cols[28]->nodeValue
                ),
                "otw" => array(
                    "sea" => (int)$cols[10]->nodeValue,
                    "sea_arrival" => $sea_arrival,
                    "air" => (int)$otw_air
                ),
                "sellout" => array(
                    "current" => (int)$sell_current,
                    "-1" => (int)$sell_1,
                    "-2" => (int)$sell_2,
                    "-3" => (int)$sell_3,
                    "-4" => (int)$sell_4,
                    "-5" => (int)$sell_5,
                ),
                "logistics" => array(
                    "innerqty" => (int)$cols[54]->nodeValue,
                    "outerqty" => (int)$cols[55]->nodeValue,
                ),
                "allocation" => array(
                    "remaining" => (int)$cols[57]->nodeValue,
                    "monthly" => (int)$cols[23]->nodeValue
                )
            );


        }
        $end_for = microtime(true);
        $json = array(
            'info' => array(array('qad_time' => now())),
            'products' => $products
        );

        Storage::put('public/qad.json', json_encode($json));
        Log::info('[STOCK]Feed duration: '. ($end_get_feed - $start_get_feed)/60 . 'secs');
        Log::info('[STOCK]Loop duration: '. ($end_for - $start_for)/60 . 'secs');
        Log::info('[STOCK]Total duration: '. (microtime(true) - $start)/60 . 'secs');
        return $products;
    }

}