@extends('layouts.app')
@section('title','Post Performance')
@section('content')
@php
$metrics=$post->metrics->keyBy('platform');
$labels=['instagram'=>'Instagram','facebook'=>'Facebook','x'=>'X (Twitter)','tiktok'=>'TikTok','linkedin'=>'LinkedIn','whatsapp_status'=>'WhatsApp Status','whatsapp_channel'=>'WhatsApp Channel'];
@endphp
<div class="space-y-4">
  <div class="apple-surface rounded-2xl p-5">
    <div class="flex flex-wrap justify-between gap-3">
      <div><div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">Post Performance</div><h1 class="mt-1 text-xl font-black">{{ $post->title }}</h1><p class="mt-1 text-sm text-slate-500">Track views, reach, likes, comments, shares, saves, clicks and replies.</p></div>
      <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('social-media-planner.analytics.sync',$post) }}">
          @csrf
          <button class="apple-btn rounded-xl px-4 py-2 text-sm font-bold"><i class="fa-solid fa-rotate mr-1"></i>Sync Now</button>
        </form>
        <a href="{{ route('social-media-planner.reports.index') }}" class="apple-btn rounded-xl px-4 py-2 text-sm font-bold">Back to Reports</a>
      </div>
    </div>
  </div>
  @foreach((array)$post->platforms as $platform)
  @php $m=$metrics->get($platform); $syncStatus=data_get($m?->raw_metrics,'sync_status'); $syncError=data_get($m?->raw_metrics,'sync_error'); $available=(array)data_get($m?->raw_metrics,'available_metrics',[]); @endphp
  <section class="apple-surface rounded-2xl p-5">
    <div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-black">{{ $labels[$platform] ?? ucwords(str_replace('_',' ',$platform)) }}</h2><p class="text-xs text-slate-500">Last updated: {{ optional($m?->synced_at)->timezone(auth()->user()->timezone ?: 'Africa/Kampala')->format('d M Y H:i') ?: 'Not yet synced' }}</p>@if($syncStatus==='error')<p class="mt-1 text-xs font-bold text-rose-600">API sync needs attention: {{ $syncError }}</p>@elseif($syncStatus==='ok')<p class="mt-1 text-xs font-bold text-emerald-600">Fetched automatically from the connected {{ $labels[$platform] ?? $platform }} API.</p>@endif</div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">Engagement {{ number_format((float)($m?->engagement_rate ?? 0),2) }}%</span></div>
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
      @foreach(['Views'=>$m?->views??0,'Reach'=>$m?->reach??0,'Likes'=>$m?->likes??0,'Comments'=>$m?->comments??0,'Shares'=>$m?->shares??0,'Saves'=>$m?->saves??0,'Clicks'=>$m?->clicks??0,'Replies'=>$m?->replies??0] as $label=>$value)
      <div class="rounded-xl border border-slate-200 p-3"><div class="text-xl font-black">{{ number_format((int)$value) }}</div><div class="text-[10px] font-bold text-slate-500">{{ $label }}</div></div>
      @endforeach
    </div>
    <form method="POST" action="{{ route('social-media-planner.analytics.update',$post) }}" class="mt-5">
      @csrf @method('PUT') <input type="hidden" name="platform" value="{{ $platform }}">
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach(['views'=>'Views','reach'=>'Reach','impressions'=>'Impressions','likes'=>'Likes / Reactions','comments'=>'Comments','shares'=>'Shares','saves'=>'Saves','clicks'=>'Clicks','replies'=>'Replies'] as $name=>$label)
        <div><label class="text-xs font-bold">{{ $label }}</label><input type="number" min="0" name="{{ $name }}" value="{{ old($name,$m?->{$name}??0) }}" class="pm-input mt-1 w-full"></div>
        @endforeach
        <div><label class="text-xs font-bold">External Post ID</label><input name="external_post_id" value="{{ old('external_post_id',$m?->external_post_id??'') }}" class="pm-input mt-1 w-full"></div>
      </div>
      <div class="mt-4 flex justify-end"><button class="btn-primary rounded-xl px-4 py-2 text-sm font-bold text-white">Update Performance</button></div>
    </form>
  </section>
  @endforeach
</div>
@endsection
