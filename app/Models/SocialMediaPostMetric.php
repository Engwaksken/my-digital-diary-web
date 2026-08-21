<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SocialMediaPostMetric extends Model {
    protected $fillable=['user_id','social_media_post_id','platform','external_post_id','views','reach','impressions','likes','comments','shares','saves','clicks','replies','engagements','engagement_rate','synced_at','raw_metrics'];
    protected $casts=['views'=>'integer','reach'=>'integer','impressions'=>'integer','likes'=>'integer','comments'=>'integer','shares'=>'integer','saves'=>'integer','clicks'=>'integer','replies'=>'integer','engagements'=>'integer','engagement_rate'=>'float','synced_at'=>'datetime','raw_metrics'=>'array'];
    public function post(): BelongsTo { return $this->belongsTo(SocialMediaPost::class,'social_media_post_id'); }
}
