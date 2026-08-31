<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $created = 0;
 
        $roots = [
            'Labor' => ['en' => 'Labor', 'ar' => 'عمالة'],
            'Nonlabor' => ['en' => 'Nonlabor', 'ar' => 'غير عمالة'],
            'Material' => ['en' => 'Material', 'ar' => 'مواد'],
        ];
 
        $rootIds = [];
 
        foreach ($roots as $key => $name) {
            $category = Category::firstOrCreate(
                ['parent_id' => null, 'name->en' => $name['en']],
                ['name' => $name]
            );
 
            $rootIds[$key] = $category->id;
            $created += $category->wasRecentlyCreated ? 1 : 0;
        }
 
        // Sub-categories, in dependency order (EQ must exist before BKL).
        $subCategories = [
            ['key' => 'EQ', 'parent' => $rootIds['Nonlabor'],
                'en' => 'Machinary and Equipement', 'ar' => 'الآليات والمعدات'],
            ['key' => 'LABOUR', 'parent' => $rootIds['Labor'],
                'en' => 'Labours', 'ar' => 'عمالة'],
            ['key' => 'DLBR', 'parent' => $rootIds['Labor'],
                'en' => 'DIRECT LABOUR', 'ar' => 'عمالة مباشرة'],
        ];
 
        $subIds = [];
 
        foreach ($subCategories as $sub) {
            $category = Category::firstOrCreate(
                ['parent_id' => $sub['parent'], 'name->en' => $sub['en']],
                ['name' => ['en' => $sub['en'], 'ar' => $sub['ar']]]
            );
 
            $subIds[$sub['key']] = $category->id;
            $created += $category->wasRecentlyCreated ? 1 : 0;
        }
 
        // BKL is nested under EQ, so it's created after EQ exists.
        $bkl = Category::firstOrCreate(
            ['parent_id' => $subIds['EQ'], 'name->en' => 'Backhoe Loader'],
            ['name' => ['en' => 'Backhoe Loader', 'ar' => 'حفار لودر']]
        );
        $created += $bkl->wasRecentlyCreated ? 1 : 0;
 
        $this->command->info("Done. Categories created: {$created} (7 total expected: 3 roots + EQ, LABOUR, DLBR, BKL).");
    }

}
