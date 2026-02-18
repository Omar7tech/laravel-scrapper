<?php

namespace App\Http\Controllers;

use Acme\Client;
use Illuminate\Support\Collection;
use Symfony\Component\BrowserKit;
use Symfony\Component\DomCrawler\Crawler;

class HomeController extends Controller
{
    public function index()
    {
        $data = collect(["data" => []]);
        $client = new Client();
        $response = $client->request('GET', 'https://goodmorninglb.com');
        $html = $response->html();
        $crawler = new Crawler($html);
        $element = $crawler->filter('div > figure > img')
            ->each(
                function ($node) {
                    $src = $node->attr('src');
                    return $src;
                }
            );
        $data->put('data', $element);
        dd($data);

        return view('welcome', compact('html'));
    }
}
