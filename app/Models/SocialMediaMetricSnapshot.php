<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SocialMediaMetricSnapshot extends Model {
    protected $fillable=['user_id','social_media_post_id','platform','views','reach','impressions','likes','comments','shares','saves','clicks','replies','engagements','engagement_rate','captured_at'];
    protected $casts=['views'=>'integer','reach'=>'integer','impressions'=>'integer','likes'=>'integer','comments'=>'integer','shares'=>'integer','saves'=>'integer','clicks'=>'integer','replies'=>'integer','engagements'=>'integer','engagement_rate'=>'float','captured_at'=>'datetime'];
    public function post(): BelongsTo { return $this->belongsTo(SocialMediaPost::class,'social_media_post_id'); }
}
