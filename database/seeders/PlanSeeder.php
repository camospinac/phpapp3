<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. PLAN BRONCE (Antiguo Básico + Abierta)
        // Solo intereses quincenales, y al final devuelve capital.
        Plan::create([
            'name' => 'Plan Bronce',
            // 'investment_type' => 'abierta', <-- Si puedes eliminar esta columna en una migración, mejor. Si no, ponle null o un valor dummy.
            'description' => 'Recibe pagos quincenales de rendimiento y un pago final con tu capital.',
            'image_url' => 'https://placehold.co/600x400/cd7f32/FFF?text=Plan+Bronce', // Color bronce
            'calculation_type' => 'fixed_plus_final',
            'fixed_percentage' => 15.00,
            'percentages' => null,
            'closed_profit_percentage' => null, // No aplica
            'closed_duration_days' => null,     // No aplica
        ]);

        // 2. PLAN PLATA (Antiguo Premium + Abierta)
        // Capital + intereses divididos en 6 cuotas exactas.
        Plan::create([
            'name' => 'Plan Plata',
            // 'investment_type' => 'abierta',
            'description' => 'Amortización total: Paga tu inversión y utilidad en 6 cuotas quincenales iguales.',
            'image_url' => 'https://placehold.co/600x400/C0C0C0/000?text=Plan+Plata', // Color plata
            'calculation_type' => 'equal_installments',
            'fixed_percentage' => 15.00,
            'percentages' => null,
            'closed_profit_percentage' => null, // No aplica
            'closed_duration_days' => null,     // No aplica
        ]);

        // 3. PLAN ORO (El antiguo comportamiento "Cerrado")
        // Bloqueo a 90 días, un solo pago masivo al final.
        Plan::create([
            'name' => 'Plan Oro',
            // 'investment_type' => 'cerrada',
            'description' => 'Maximiza tus ganancias. Invierte a 90 días y recibe un único pago con alta rentabilidad.',
            'image_url' => 'https://placehold.co/600x400/FFD700/000?text=Plan+Oro', // Color oro
            'calculation_type' => 'single_payment', // Este es el tipo de cálculo que usamos en el Vue
            'fixed_percentage' => null,         // No aplica
            'percentages' => null,
            'closed_profit_percentage' => 50.00, // <-- Solo aplica aquí
            'closed_duration_days' => 90,        // <-- Solo aplica aquí
        ]);
    }
}