<?php

namespace App\Console\Commands;

use App\Models\Feed;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Version\Package\Version;

class UpdatePricesFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update prices into JSON';

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
        Log::info('[PRICES] Update price feed' );
        // get prices
        $feed = Feed::Active();
        //TODO: Fix date inconsistency
        if($feed->herodate){
            $herodate = Carbon::createFromFormat('Y-m-d H:i:s', $feed->herodate)->format('d-m-Y H:i');
        }else{
            $herodate = "0000-00-00 00:00:00";
        }
        $info = array(array(
            'version' => (new Version())->format('version-only'),
            'version_full' => (new Version())->format('full'),
            'id' => $feed->id,
            'week' => $feed->week,
            'exchange' => $feed->exchange,
            'file' => $feed->file,
            'herofile' => $feed->herofile,
            'dates' => array(
                'created_at' => $feed->created_at->format('d-m-Y H:i'),
                'updated_at' => $feed->updated_at->format('d-m-Y H:i'),
                'herodate' => $herodate,
                'generated_at' => now()->format('d-m-Y H:i'))
        ));
        $json = array(
            'info' => $info,
            'products' => $feed->prices
        );
        Log::info('[PRICES] Saving to JSON feed' );
        Storage::put('public/prices.json', json_encode($json));
        Log::info('[PRICES] Done!' );
        return 0;
    }
}