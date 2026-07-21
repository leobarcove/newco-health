<?php

namespace Database\Seeders;

use App\Modules\Prescribing\Models\FormularyItem;
use Illuminate\Database\Seeder;

/** Starter subset of the Nigerian Essential Medicines List — staff-curated in Filament thereafter. */
class FormularySeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['Artemether/Lumefantrine', 'tablet', '20/120 mg'],
            ['Paracetamol', 'tablet', '500 mg'],
            ['Paracetamol', 'syrup', '120 mg/5 ml'],
            ['Amoxicillin', 'capsule', '500 mg'],
            ['Amoxicillin/Clavulanate', 'tablet', '625 mg'],
            ['Ciprofloxacin', 'tablet', '500 mg'],
            ['Metronidazole', 'tablet', '400 mg'],
            ['Omeprazole', 'capsule', '20 mg'],
            ['Amlodipine', 'tablet', '5 mg'],
            ['Lisinopril', 'tablet', '10 mg'],
            ['Metformin', 'tablet', '500 mg'],
            ['Glibenclamide', 'tablet', '5 mg'],
            ['Loratadine', 'tablet', '10 mg'],
            ['Chlorpheniramine', 'tablet', '4 mg'],
            ['Oral Rehydration Salts', 'sachet', null],
            ['Zinc Sulphate', 'tablet', '20 mg'],
            ['Ibuprofen', 'tablet', '400 mg'],
            ['Ferrous Sulphate/Folic Acid', 'tablet', null],
            ['Albendazole', 'tablet', '400 mg'],
            ['Nystatin', 'cream', '100,000 IU/g'],
        ];

        foreach ($items as [$name, $form, $strength]) {
            FormularyItem::firstOrCreate(['name' => $name, 'form' => $form, 'strength' => $strength]);
        }
    }
}
