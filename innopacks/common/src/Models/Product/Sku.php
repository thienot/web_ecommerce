<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace InnoShop\Common\Models\Product;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InnoShop\Common\Models\BaseModel;
use InnoShop\Common\Models\Product;
use InnoShop\Common\Services\ProductPriceService;

class Sku extends BaseModel
{
    protected $table = 'product_skus';

    protected $fillable = [
        'product_id', 'images', 'model', 'code', 'price', 'origin_price', 'quantity', 'is_default', 'position',
        'variants',
    ];

    protected $casts = [
        'images'   => 'array',
        'variants' => 'array',
    ];

    protected $appends = ['image'];

    /**
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function variants(): HasMany
    {
        return $this->hasMany(SkuVariant::class, 'product_sku_id');
    }

    /**
     * @return array
     */
    public function getLocaleLabels(): array
    {
        $labels    = [];
        $variables = $this->product->variables;
        if (empty($variables) || empty($this->variants)) {
            return [];
        }

        $localeCode = front_locale_code();
        foreach ($this->variants as $key => $value) {
            $labels[] = [
                'name'  => $variables[$key]['name'][$localeCode]                   ?? '',
                'value' => $variables[$key]['values'][$value]['name'][$localeCode] ?? '',
            ];
        }

        return $labels;
    }

    /**
     * Get sku final price.
     * @return mixed
     */
    public function getFinalPrice(): mixed
    {
        $price = ProductPriceService::getInstance($this)->getFinal();
        $data  = [
            'sku'   => $this,
            'price' => $price,
        ];
        $data = fire_hook_filter('model.sku.final_price', $data);

        return $data['price'];
    }

    /**
     * Get image path from SKU or SPU.
     *
     * @return string
     */
    public function getImagePath(): string
    {
        $skuImage = $this->image;
        if ($skuImage) {
            return $skuImage;
        }

        return $this->product->image ?? '';
    }

    /**
     * Get image url form SKU or SPU.
     *
     * @param  int  $width
     * @param  int  $height
     * @return string
     * @throws Exception
     */
    public function getImageUrl(int $width = 100, int $height = 100): string
    {
        $imagePath = $this->getImagePath();

        return image_resize($imagePath, $width, $height);
    }

    /**
     * @return string
     */
    public function getImageAttribute(): string
    {
        $images = $this->images ?? [];

        return $images[0] ?? '';
    }

    /**
     * Get sku final price.
     * @return mixed
     */
    public function getPriceFormatAttribute(): string
{
    if (empty($this->price)) return 'Liên hệ';
    return number_format($this->price, 0, ',', '.') . ' VNĐ';
}

public function getOriginPriceFormatAttribute(): string
{
    if (empty($this->origin_price)) return '';
    return number_format($this->origin_price, 0, ',', '.') . ' VNĐ';
}

public function getFinalPriceFormat(): string
{
    $price = $this->getFinalPrice();
    if (empty($price)) return 'Liên hệ';
    return number_format($price, 0, ',', '.') . ' VNĐ';
}

    /**
     * @return string
     */
    public function getImagePathAttribute(): string
    {
        return $this->image ?? '';
    }

    /**
     * @return string
     */
    public function getVariantLabelAttribute(): string
    {
        $vLabel = '';
        $labels = $this->getLocaleLabels();
        if (empty($labels)) {
            return '';
        }

        foreach ($labels as $label) {
            $vLabel .= $label['name'].':'.$label['value'].'; ';
        }

        $data = [
            'vLabel' => $vLabel,
            'sku'    => $this,
        ];
        $data = fire_hook_filter('model.sku.variant_label_attribute', $data);

        return trim($data['vLabel']);
    }
}
