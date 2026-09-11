<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsProvider;
use App\Services\SmsService;
use Illuminate\Http\Request;

class AdminSmsProviderController extends Controller
{
    public function index()
    {
        return view('admin.sms-providers.index', [
            'providers' => SmsProvider::orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['is_default'] = $request->boolean('is_default');
        $data['extra_headers'] = $this->jsonOrEmpty($request->input('extra_headers'));
        $data['extra_payload'] = $this->jsonOrEmpty($request->input('extra_payload'));

        if ($data['is_default']) {
            SmsProvider::query()->update(['is_default' => false]);
        }

        SmsProvider::create($data);
        return back()->with('success', 'SMS provider saved.');
    }

    public function update(Request $request, SmsProvider $smsProvider)
    {
        $data = $this->validated($request, false);
        if (! filled($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }
        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['is_default'] = $request->boolean('is_default');
        $data['extra_headers'] = $this->jsonOrEmpty($request->input('extra_headers'));
        $data['extra_payload'] = $this->jsonOrEmpty($request->input('extra_payload'));

        if ($data['is_default']) {
            SmsProvider::whereKeyNot($smsProvider->id)->update(['is_default' => false]);
        }

        $smsProvider->update($data);
        return back()->with('success', 'SMS provider updated.');
    }

    public function destroy(SmsProvider $smsProvider)
    {
        $smsProvider->delete();
        return back()->with('success', 'SMS provider deleted.');
    }

    public function test(Request $request, SmsService $sms)
    {
        $data = $request->validate([
            'phone' => ['required','string','max:40'],
            'message' => ['nullable','string','max:300'],
        ]);

        $result = $sms->send($data['phone'], $data['message'] ?: 'My Digital Diary SMS provider test.');
        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Test SMS sent.' : 'The provider returned an error.');
    }

    private function validated(Request $request, bool $requireKey = true): array
    {
        return $request->validate([
            'name'=>['required','string','max:120'],'endpoint'=>['required','url','max:1000'],
            'http_method'=>['required','in:POST,GET'],'auth_type'=>['required','in:bearer,header,basic,none'],
            'api_key'=>[$requireKey ? 'nullable' : 'nullable','string','max:4000'],
            'sender_id'=>['nullable','string','max:80'],'recipient_field'=>['required','string','max:80'],
            'message_field'=>['required','string','max:80'],'sender_field'=>['nullable','string','max:80'],
        ]);
    }

    private function jsonOrEmpty($value): array
    {
        if (! filled($value)) return [];
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
