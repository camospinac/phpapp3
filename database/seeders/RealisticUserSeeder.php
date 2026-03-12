<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Cmixin\BusinessDay;

class RealisticUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando usuarios de prueba y suscripciones vencidas con la nueva lógica...');

        $plans = Plan::all();
        if ($plans->count() < 3) {
            $this->command->error('No se encontraron los 3 planes necesarios (Bronce, Plata, Oro). Por favor, corre el PlanSeeder primero.');
            return;
        }

        // Aseguramos que los planes existan por su tipo de cálculo para asignarlos correctamente
        $planBronce = $plans->where('calculation_type', 'fixed_plus_final')->first();
        $planPlata  = $plans->where('calculation_type', 'equal_installments')->first();
        $planOro    = $plans->where('calculation_type', 'single_payment')->first();

        // 🔹 Lista actualizada de usuarios con los nuevos datos
        $usersData = [
            [
                'nombres' => 'Sergio',
                'apellidos' => 'Cárdenas',
                'identification_type' => 'CEDULA CIUDANIA',
                'identification_number' => '78437372',
                'email' => 'comcargrupo@gmail.com',
                'celular' => '3107973703',
                'location' => 'Colombia, Cundinamarca, Girardot',
                'payment_receipt_path' => 'receipts/fake_receipt.jpg',
                'password_base' => 'Sergio78437372',
            ],
            [
                'nombres' => 'Jonathan',
                'apellidos' => 'Bedoya',
                'identification_type' => 'CEDULA CIUDANIA',
                'identification_number' => '80548903',
                'email' => 'jonathin12@hotmail.com',
                'celular' => '3118482910',
                'location' => 'Colombia, Cundinamarca, Girardot', // Asumido
                'payment_receipt_path' => 'receipts/fake_receipt.jpg',
                'password_base' => 'Jonathan80548903',
            ],
            [
                'nombres' => 'Carlos Iván',
                'apellidos' => 'Largo Cetina',
                'identification_type' => 'CEDULA CIUDANIA',
                'identification_number' => '1002393074',
                'email' => 'ivanlargo20@gmail.com',
                'celular' => '31423722996',
                'location' => 'Colombia, Cundinamarca, Girardot', // Asumido
                'payment_receipt_path' => 'receipts/fake_receipt.jpg',
                'password_base' => 'Carlos1002393074',
            ],
        ];

        foreach ($usersData as $userData) {
            User::withoutEvents(function () use ($userData, $planBronce, $planPlata, $planOro) {
                $existing = User::where('email', $userData['email'])->first();
                if ($existing) {
                    $this->command->warn("El usuario {$userData['email']} ya existe, saltando...");
                    return; // Saltamos si ya existe para no duplicar en re-ejecuciones
                }

                $user = User::create([
                    'nombres' => $userData['nombres'],
                    'apellidos' => $userData['apellidos'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password_base']),
                    'rol' => 'usuario',
                    'es_cuenta_prueba' => true,
                    'location' => $userData['location'],
                    'identification_type' => $userData['identification_type'],
                    'identification_number' => $userData['identification_number'],
                    'celular' => $userData['celular'],
                    'referral_code' => strtoupper(substr($userData['nombres'], 0, 4) . rand(1000, 9999)),
                ]);

                $this->command->info("✅ Usuario {$user->nombres} creado.");

                // --- 2. Crear las 3 suscripciones con antigüedad de 45 días (mes y medio) ---
                // Para que los pagos ya estén vencidos.
                $creationDate = Carbon::now()->subDays(45);
                
                $planesAsignar = [$planBronce, $planPlata, $planOro];

                foreach ($planesAsignar as $index => $plan) {
                    $subscription = $user->subscriptions()->create([
                        'plan_id' => $plan->id,
                        'sequence_id' => $index + 1,
                        // Valores aleatorios entre 500.000 (5 * 100k) y 2.000.000 (20 * 100k)
                        'initial_investment' => rand(5, 20) * 100000, 
                        'status' => 'active',
                        // 'contract_type' => ELIMINADO
                        'created_at' => $creationDate,
                        'updated_at' => $creationDate,
                    ]);

                    $this->createPaymentSchedule($subscription, $creationDate);
                }
            });
        }
    }

    // Adaptamos el método para recibir la fecha de creación "fake"
    protected function createPaymentSchedule(Subscription $subscription, Carbon $fakeStartDate)
    {
        $plan = $subscription->plan;
        $amount = (float) $subscription->initial_investment;
        $totalProfit = 0;

        // Aseguramos que la lógica de días hábiles esté disponible
        BusinessDay::enable('Carbon\Carbon', 'es_CO');

        // --- LÓGICA BASADA ÚNICAMENTE EN EL TIPO DE CÁLCULO DEL PLAN ---

        // 1. PLAN ORO (Pago Único)
        if ($plan->calculation_type === 'single_payment') {
            $profitPercentage = (float) ($plan->closed_profit_percentage ?? 50);
            $durationDays = (int) ($plan->closed_duration_days ?? 90);

            $baseProfit = $amount * ($profitPercentage / 100);
            $totalProfit = $baseProfit * 3; // Lógica anterior: 1 mes * 3 meses
            $totalPayment = $amount + $totalProfit;
            
            $dueDate = $fakeStartDate->copy()->addDays($durationDays);

            $subscription->payments()->create([
                'amount' => round($totalPayment, 2),
                'percentage' => $profitPercentage,
                'status' => 'pending',
                'payment_due_date' => $dueDate->toDateString(),
            ]);

        } 
        // 2. PLAN BRONCE (Pagos fijos + final)
        elseif ($plan->calculation_type === 'fixed_plus_final' && $plan->fixed_percentage) {
            $currentDueDate = $fakeStartDate->copy()->addDays(15);
            $fixedPercentage = (float) $plan->fixed_percentage;
            $fixedPayment = $amount * ($fixedPercentage / 100);
            
            for ($i = 1; $i <= 5; $i++) {
                if (!$currentDueDate->isBusinessDay()) {
                    $currentDueDate = $currentDueDate->nextBusinessDay();
                }

                $subscription->payments()->create([
                    'amount' => round($fixedPayment, 2),
                    'percentage' => $fixedPercentage,
                    'status' => 'pending',
                    'payment_due_date' => $currentDueDate->toDateString(),
                ]);
                $currentDueDate->addDays(15);
            }

            if (!$currentDueDate->isBusinessDay()) {
                $currentDueDate = $currentDueDate->nextBusinessDay();
            }

            $finalPayment = $amount + $fixedPayment;
            $subscription->payments()->create([
                'amount' => round($finalPayment, 2),
                'percentage' => $fixedPercentage,
                'status' => 'pending',
                'payment_due_date' => $currentDueDate->toDateString(),
            ]);

            $totalProfit = $fixedPayment * 6;

        } 
        // 3. PLAN PLATA (Cuotas Iguales)
        elseif ($plan->calculation_type === 'equal_installments' && $plan->fixed_percentage) {
            $currentDueDate = $fakeStartDate->copy()->addDays(15);
            $fixedPercentage = (float) $plan->fixed_percentage;
            $fixedPayment = $amount * ($fixedPercentage / 100);
            $totalProfit = $fixedPayment * 6;
            
            $totalToPay = $amount + $totalProfit;
            $installment = $totalToPay / 6;

            for ($i = 1; $i <= 6; $i++) {
                if (!$currentDueDate->isBusinessDay()) {
                    $currentDueDate = $currentDueDate->nextBusinessDay();
                }

                $subscription->payments()->create([
                    'amount' => round($installment, 2),
                    'percentage' => null,
                    'status' => 'pending',
                    'payment_due_date' => $currentDueDate->toDateString(),
                ]);
                $currentDueDate->addDays(15);
            }
        }

        // Guardamos la utilidad total
        $subscription->profit_amount = $totalProfit;
        $subscription->save();
    }
}