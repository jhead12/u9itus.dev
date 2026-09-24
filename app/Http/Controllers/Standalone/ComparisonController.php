<?php

namespace App\Http\Controllers\Standalone;

use App\Support\PoliticianDataRules;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

class ComparisonController
{
    public function index()
    {
        return view('standalone.public.compare', ['states' => PoliticianDataRules::ALLOWED_STATES]);
    }

    public function glossary()
    {
        return view('standalone.public.compare-glossary', [
            'entries' => \App\Support\OfficeGlossary::ENTRIES,
            'notes' => \App\Support\OfficeGlossary::placeNotes(),
        ]);
    }

    public function qr(Request $request)
    {
        $input = $request->validate(['query' => 'required|string|max:2000']);
        $url = url('/compare').'?'.$input['query'];
        $writer = new Writer(new ImageRenderer(new RendererStyle(220), new SvgImageBackEnd()));

        return response($writer->writeString($url))->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
