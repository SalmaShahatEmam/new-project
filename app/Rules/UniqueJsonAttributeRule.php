<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueJsonAttributeRule implements ValidationRule
{
    protected $attributeName;
    protected $table;
    protected $column;
    protected $ignore;
    protected $scopeColumn;
    protected $scopeColumnValue;

    public function __construct($attributeName, $table, $column, $ignore = null, $scopeColumn = null, $scopeColumnValue = null)
    {
        $this->attributeName = $attributeName;
        $this->table = $table;
        $this->column = $column;
        $this->ignore = $ignore;
        $this->scopeColumn = $scopeColumn;
        $this->scopeColumnValue = $scopeColumnValue;
    }

    /**
     * Run the validation rule.
     *
     * @param \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table($this->table)
            ->whereRaw('json_unquote(json_extract(`' . $this->column . '`, \'$.' . $this->attributeName . '\')) = ?', [$value]);

        if(!is_null($this->scopeColumn)){
            $query->where($this->scopeColumn, $this->scopeColumnValue);
        }
        if (!is_null($this->ignore)) {
            $query->where('id', '!=', $this->ignore);
        }

        $count = $query->count();

        if ($count !== 0) {
            $fail(__('validation.unique'));
        }
    }
}