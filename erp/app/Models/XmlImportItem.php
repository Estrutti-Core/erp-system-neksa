<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XmlImportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'xml_import_id',
        'product_id',
        'supplier_product_code',
        'supplier_product_name',
        'quantity',
        'unit_price',
        'total_price',
        'cfop',
        'ncm',
        'resolved',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total_price' => 'decimal:2',
        'resolved' => 'boolean',
    ];

    public function xmlImport(): BelongsTo
    {
        return $this->belongsTo(XmlImport::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
