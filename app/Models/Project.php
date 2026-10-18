<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\ProjectEngineers;
use PhpParser\Builder\Function_;

class Project extends Model
{
    use HasFactory, ModelTrait;

    private ?array $sectionPriceTotalsCache = null;

    public const REQUIRED_ENGINEER_TYPES = [
        'Structural',
        'Architectural',
        'Electrical',
        'Mechanical',
    ];

    protected $fillable = ["name", 'reopen_reason',"description",  "status", "user_id", "end_date" ,"reject_message", "reopen_reason"];

    protected $filters = ["name", "description",  "status", "user_id", "end_date" , "id"];

    protected $appends = [
        'total_price',
        'total_price_structural',
        'total_price_architectural',
        'total_price_electrical',
        'total_price_mechanical',
        'total_tax_structural',
        'total_tax_architectural',
        'total_tax_electrical',
        'total_tax_mechanical',
        'total_price_structural_before_tax',
        'total_price_architectural_before_tax',
        'total_price_electrical_before_tax',
        'total_price_mechanical_before_tax',
    ];
    public function scopeOfId($query, $id)
    {
        return $query->where('id', $id);
    }
    public function scopeOfName($query, $name)
    {
        return $query->where('name', 'LIKE', "%$name%");
    }

    public function scopeOfDescription($query, $description)
    {
        return $query->where('description', 'LIKE', "%$description%");
    }

    public function scopeOfStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOfUserId($query, $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    public function scopeOfEndDate($query, $end_date)
    {
        return $query->where('end_date', $end_date);
    }



    # Relations

    public function users()
    {
        return $this->hasMany(ProjectEngineers::class , "project_id" ,"id");
    }

 
public function allEngineersAssigned(): bool
{
    $users = $this->relationLoaded('users')
        ? $this->users
        : $this->users()
            ->whereIn('type', self::REQUIRED_ENGINEER_TYPES)
            ->where('active', true)
            ->get();

    return $users
        ->whereIn('type', self::REQUIRED_ENGINEER_TYPES)
        ->where('active', true)
        ->pluck('type')
        ->unique()
        ->count() === count(self::REQUIRED_ENGINEER_TYPES);
}


    public function allEngineersUploadedResources(): bool
    {
        $sectionCount = $this->resources()
            ->whereNotNull('section_name')
            ->where('section_name', '<>', '')
            ->distinct('section_name')
            ->count('section_name');

        return $sectionCount >= 4;
    }

    public function resources()
    {
        return $this->hasMany(ProjectResources::class , "project_id" ,"id");
    }



    #total_project_cost
    public function getTotalProjectCostAttribute(): float
    {
        if ($this->relationLoaded('resources')) {
            return (float) $this->resources->sum(
                fn ($resource) => (float) $resource->price_after_tax * (float) $resource->quantity
            );
        }

        return (float) $this->resources()
            ->selectRaw('COALESCE(SUM(price_after_tax * quantity), 0) as total')
            ->value('total');
    }

    public function getTotalPriceAttribute(): float
    {
        if ($this->relationLoaded('resources')) {
            return (float) $this->resources->sum(
                fn ($resource) => (float) $resource->price_after_tax * (float) $resource->quantity
            );
        }

        return (float) $this->resources()
            ->selectRaw('COALESCE(SUM(price_after_tax * quantity), 0) as total')
            ->value('total');
    }




    public function getTotalProjectMaterialsAttribute(): int
    {
        if ($this->relationLoaded('resources')) {
            return (int) $this->resources->count();
        }

        return (int) $this->resources()->count();
    }


    public function getTotalProjectPriceAttribute(): float
    {
        if ($this->relationLoaded('resources')) {
            return (float) $this->resources->sum(
                fn ($resource) => (float) $resource->price_after_tax * (float) $resource->quantity
            );
        }

        return (float) $this->resources()
            ->selectRaw('COALESCE(SUM(price_after_tax * quantity), 0) as total')
            ->value('total');
    }



    public function getTotalProjectSectionWithoutTaxAttribute($sectionName): int
    {
        if ($this->relationLoaded('resources')) {
            return (int) $this->resources
                ->where('section_name', $sectionName)
                ->sum('quantity');
        }

        return (int) $this->resources()
            ->where('section_name', $sectionName)
            ->selectRaw('COALESCE(SUM(quantity), 0) as total')
            ->value('total');
    }

    public function getTotalSectionTaxAttribute($sectionName): float
    {
        if ($this->relationLoaded('resources')) {
            return (float) $this->resources
                ->where('section_name', $sectionName)
                ->sum(
                    fn ($resource) => (float) $resource->tax * (float) $resource->quantity
                );
        }

        return (float) $this->resources()
            ->where('section_name', $sectionName)
            ->selectRaw('COALESCE(SUM(tax * quantity), 0) as total')
            ->value('total');
    }


    public function getTotalSectionPriceAfterTaxAttribute($sectionName): float
    {
        if ($this->relationLoaded('resources')) {
            return (float) $this->resources
                ->where('section_name', $sectionName)
                ->sum(
                    fn ($resource) => (float) $resource->price_after_tax * (float) $resource->quantity
                );
        }

        return (float) $this->resources()
            ->where('section_name', $sectionName)
            ->selectRaw('COALESCE(SUM(price_after_tax * quantity), 0) as total')
            ->value('total');
    }

    public function getTotalPriceStructuralAttribute(): float
    {
        return $this->getSectionPriceTotals('Structural')['after_tax'];
    }

    public function getTotalPriceArchitecturalAttribute(): float
    {
        return $this->getSectionPriceTotals('Architectural')['after_tax'];
    }

    public function getTotalPriceElectricalAttribute(): float
    {
        return $this->getSectionPriceTotals('Electrical')['after_tax'];
    }

    public function getTotalPriceMechanicalAttribute(): float
    {
        return $this->getSectionPriceTotals('Mechanical')['after_tax'];
    }

    public function getTotalTaxStructuralAttribute(): float
    {
        return $this->getSectionPriceTotals('Structural')['tax'];
    }

    public function getTotalTaxArchitecturalAttribute(): float
    {
        return $this->getSectionPriceTotals('Architectural')['tax'];
    }

    public function getTotalTaxElectricalAttribute(): float
    {
        return $this->getSectionPriceTotals('Electrical')['tax'];
    }

    public function getTotalTaxMechanicalAttribute(): float
    {
        return $this->getSectionPriceTotals('Mechanical')['tax'];
    }

    public function getTotalPriceStructuralBeforeTaxAttribute(): float
    {
        return $this->getSectionPriceTotals('Structural')['before_tax'];
    }

    public function getTotalPriceArchitecturalBeforeTaxAttribute(): float
    {
        return $this->getSectionPriceTotals('Architectural')['before_tax'];
    }

    public function getTotalPriceElectricalBeforeTaxAttribute(): float
    {
        return $this->getSectionPriceTotals('Electrical')['before_tax'];
    }

    public function getTotalPriceMechanicalBeforeTaxAttribute(): float
    {
        return $this->getSectionPriceTotals('Mechanical')['before_tax'];
    }

    private function getSectionPriceTotals(string $sectionName): array
    {
        if ($this->sectionPriceTotalsCache === null) {
            $sectionKeys = array_map('strtolower', self::REQUIRED_ENGINEER_TYPES);
            $this->sectionPriceTotalsCache = array_fill_keys($sectionKeys, [
                'before_tax' => 0.0,
                'tax' => 0.0,
                'after_tax' => 0.0,
            ]);

            if ($this->relationLoaded('resources')) {
                foreach ($this->resources as $resource) {
                    $key = strtolower(trim((string) $resource->section_name));

                    if (! array_key_exists($key, $this->sectionPriceTotalsCache)) {
                        continue;
                    }

                    $quantity = (float) $resource->quantity;
                    $price = (float) $resource->price;
                    $priceAfterTax = $resource->price_after_tax !== null
                        ? (float) $resource->price_after_tax
                        : $price + ($price * (float) $resource->tax / 100);
                    $beforeTax = $price * $quantity;
                    $afterTax = $priceAfterTax * $quantity;

                    $this->sectionPriceTotalsCache[$key]['before_tax'] += $beforeTax;
                    $this->sectionPriceTotalsCache[$key]['tax'] += $afterTax - $beforeTax;
                    $this->sectionPriceTotalsCache[$key]['after_tax'] += $afterTax;
                }
            } else {
                $totals = $this->resources()
                    ->whereIn(DB::raw('LOWER(section_name)'), $sectionKeys)
                    ->selectRaw(
                        'LOWER(section_name) as section_key,
                        COALESCE(SUM(price * quantity), 0) as before_tax,
                        COALESCE(SUM((COALESCE(price_after_tax, price + (price * COALESCE(tax, 0) / 100)) - price) * quantity), 0) as tax,
                        COALESCE(SUM(COALESCE(price_after_tax, price + (price * COALESCE(tax, 0) / 100)) * quantity), 0) as after_tax'
                    )
                    ->groupByRaw('LOWER(section_name)')
                    ->get();

                foreach ($totals as $total) {
                    $this->sectionPriceTotalsCache[$total->section_key] = [
                        'before_tax' => (float) $total->before_tax,
                        'tax' => (float) $total->tax,
                        'after_tax' => (float) $total->after_tax,
                    ];
                }
            }
        }

        return $this->sectionPriceTotalsCache[strtolower($sectionName)] ?? [
            'before_tax' => 0.0,
            'tax' => 0.0,
            'after_tax' => 0.0,
        ];
    }


    /*


                'total_project_materials' => $this->total_project_materials,
            'total_price_structural_with_tax' => $this->total_price_structural,
            'total_price_architectural_with_tax' => $this->total_price_architectural,
            'total_price_electrical_with_tax' => $this->total_price_electrical,
            'total_price_mechanical_with_tax' => $this->total_price_mechanical,
            'total_tax_structural' => $this->total_tax_structural,
            'total_tax_architectural' => $this->total_tax_architectural,
            'total_tax_electrical' => $this->total_tax_electrical,
            'total_tax_mechanical' => $this->total_tax_mechanical,
            'total_price_structural_before_tax' => $this->total_price_structural_before_tax,
            'total_price_architectural_before_tax' => $this->total_price_architectural_before_tax,
            'total_price_electrical_before_tax' => $this->total_price_electrical_before_tax,
            'total_price_mechanical_before_tax' => $this->total_price_mechanical_before_tax,


            */

    

}
