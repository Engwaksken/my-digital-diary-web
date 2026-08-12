<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignedDocumentPlacement extends Model
{
    protected $fillable = [
        'signed_document_id', 'signature_id', 'page_number',
        'x_percent', 'y_percent', 'width_percent', 'height_percent',
    ];

    public function signedDocument()
    {
        return $this->belongsTo(SignedDocument::class);
    }

    public function signature()
    {
        return $this->belongsTo(Signature::class);
    }
}
