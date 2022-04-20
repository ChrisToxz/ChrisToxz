<?php

namespace App\Http\Controllers;

use App\FeedImport;
use App\Jobs\UpdatePrices;
use App\Jobs\UpdateStock;
use App\Models\Feed;
use App\Models\Price;
use App\Parsers\HeroTable;
use App\Parsers\PriceTable;
use App\Readers\HeroReader;
use Carbon\Carbon;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\CssSelector\Parser\Reader;

class FeedController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $feed = Feed::Active();

        if($feed->exists()){
            return view('feed', ['table' => PriceTable::table($feed)]);
        }

    }

    public function upload($req, $location)
    {
        $req->validate([
            'file' => 'required|mimes:xls,xlsx|max:2048'
        ]);

        if($req->file()) {
            $file = $req->file;
            $filename = time().'_'.$file->getClientOriginalName();
            Storage::disk('local')->putFileAs(
                'uploads/'.$location,
                $file,
                $filename
            );
        }

        return $filename;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if($filename = $this->upload($request, 'feeds')){

            $reader = new \App\Readers\FeedReader($request->file);

            if($reader->validate()){
                $feed = new Feed;
                $feed->week = $reader->weeknum;
                $feed->exchange = $reader->exchange;
                $feed->file = $filename;
                $feed->save();

                foreach($reader->products as $product)
                {
                    $feed->prices()->create([
                        'SKU' => $product['A'],
                        'S1' => $product['B'],
                        'S2' => $product['C'],
                        'S3' => $product['D'],
                        'S4' => $product['E'],
                        'S5' => $product['F'],
                        'PRO' => $product['G'],

                    ]);
                }
            }
            return response()->json(['success'=>'Feed has been imported succesfully. Filename: '. $filename]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Feed  $feed
     * @return \Illuminate\Http\Response
     */
    public function show(Feed $feed)
    {
        return view('feed', ['feed' => $feed, 'table' => PriceTable::table($feed)]);
    }

    public function showHero(Feed $feed)
    {
        if(!$feed->id){
            $feed = Feed::active();
        }

        if($feed->exists()){
            return view('feed', ['feed' => $feed, 'table' => HeroTable::table($feed)]);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Feed  $feed
     * @return \Illuminate\Http\Response
     */
    public function edit(Feed $feed)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Feed  $feed
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, Feed $feed)
    {

    }


    public function setActive(Request $request)
    {
        Feed::DisableAll();
        $feed = Feed::findOrFail($request->id);
        $feed->active = '1';
        $feed->save();


        Artisan::call('update:prices');
        UpdateStock::dispatch();
        return response()->json(['success'=>'Feed ID: '.$feed->id.' has been set as active feed.<br><b>Page will reload in a few seconds</b>']);

    }

    public function updateHero(Request $request)
    {

        if ($filename = $this->upload($request, 'hero')) {
            $sku = array();
            $reader = new HeroReader($request->file);

            if ($reader->validate()) {
                $feed = Feed::find($request->id);
                $feed->herodate = Carbon::now();
                $feed->herofile = $filename;
                $feed->save();


                foreach ($reader->products as $hero) {
                    Price::where('feed_id', $request->id)->where('SKU', $hero['A'])->update(['hero' => $hero['B']]);
                    $sku[] = $hero['A'];
                }
            }
            Artisan::call('update:prices');
            return response()->json(['success' => 'HERO updated!', 'sku' => $sku]);
        }
    }

    public function generatePricelist(Request $request)
    {
        $feed = Feed::findOrFail($request->id);
        $pricelist = new \App\Parsers\PricelistParser();
        $zip = $pricelist->generatePricelist($feed);

        return response()->json(['success' => 'storage'.$zip]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Feed  $feed
     * @return \Illuminate\Http\Response
     */
    public function destroy(Feed $feed)
    {
        if($feed->delete()){
            return response()->json(['success'=>'Feed deleted!']);
        }
    }
}