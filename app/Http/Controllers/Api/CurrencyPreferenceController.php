<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $settings=SiteSetting::current();
        return response()->json(['data'=>[
            'selected'=>$request->user()->preferredCurrencyCode(),
            'options'=>$settings->currencyOptions(),
        ]]);
    }
    public function update(Request $request)
    {
        $settings=SiteSetting::current();
        $allowed=collect($settings->currencyOptions())->pluck('code')->map(fn($v)=>strtoupper($v))->all();
        $data=$request->validate(['preferred_currency_code'=>['required','string',Rule::in($allowed)]]);
        $request->user()->update(['preferred_currency_code'=>strtoupper($data['preferred_currency_code'])]);
        return $this->show($request);
    }
}
