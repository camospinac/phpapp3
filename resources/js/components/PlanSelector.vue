<script setup lang="ts">
import { computed, watch, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PlusCircle, MinusCircle } from 'lucide-vue-next';
import { LoaderCircle } from 'lucide-vue-next';

const currentStep = ref(1);
interface PaymentMethod {
    id: number;
    name: string;
    account_details: string; 
    logo_path: string | null;      
}

const props = defineProps<{
    plans: Plan[];
    totalAvailable: number;
    paymentMethods: PaymentMethod[]; 
}>();

const getLogoUrl = (path: string | null) => {
    if (!path) return 'https://placehold.co/100x100/gray/white?text=Logo';
    return `/storage/${path}`;
}

const transferMethods = ref(props.paymentMethods.map(method => ({
    name: method.name,
    logo: getLogoUrl(method.logo_path),
    phone: method.account_details, 
})));

const selectedTransferMethod = ref(transferMethods.value.length > 0 ? transferMethods.value[0] : null);

const formatCurrency = (amount: number | string) => {
    const number = Number(amount);
    if (isNaN(number)) return '';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: 'COP', minimumFractionDigits: 0
    }).format(number);
};

const formattedAmount = computed(() => {
    if (form.amount === '') return '';
    return formatCurrency(form.amount);
});

// --- INTERFACES --- (Se eliminó investment_type)
interface Plan {
    id: number;
    name: string;
    description: string;
    image_url: string | null;
    calculation_type: string;
    fixed_percentage: number | null;
    closed_profit_percentage: number | null;
    closed_duration_days: number | null;
    percentages: number[] | null;
}

const emit = defineEmits(['submit']);

// --- FORMULARIO --- (Se eliminó investment_contract_type)
const form = useForm({
    plan_id: null as number | null,
    amount: '',
    receipt: null as File | null,
    payment_method: 'balance',
});

// --- WATCHERS ---
watch(() => form.payment_method, (newMethod) => {
    if (newMethod === 'balance') {
        form.receipt = null;
    }
});
watch(() => form.amount, (newValue) => {
    // ... tu lógica de corrección de monto
});

// --- LÓGICA DE CÁLCULO REFACTORIZADA ---
const calculatedPayments = computed(() => {
    if (!form.amount || !form.plan_id) return [];
    const amount = parseFloat(form.amount);
    if (isNaN(amount) || amount <= 0) return [];
    
    const selectedPlan = props.plans.find(p => p.id === form.plan_id);
    if (!selectedPlan) return [];

    const payments = [];

    // 1. Lógica para PLAN ORO (Antiguo Cerrado) -> 'single_payment'
    if (selectedPlan.calculation_type === 'single_payment') {
        const percentage = selectedPlan.closed_profit_percentage ?? 50;
        const duration = selectedPlan.closed_duration_days ?? 90;

        const baseProfit = amount * (percentage / 100);
        const totalProfit = baseProfit * 3;
        const totalPayment = amount + totalProfit;

        let finalDate = new Date();
        finalDate.setDate(finalDate.getDate() + duration);

        payments.push({
            label: `Pago Único a ${duration} días`,
            value: totalPayment,
            date: finalDate.toISOString().split('T')[0]
        });

    }
    // 2. Lógica para PLAN BRONCE (Antiguo Básico + Abierta) -> 'fixed_plus_final'
    else if (selectedPlan.calculation_type === 'fixed_plus_final' && selectedPlan.fixed_percentage) {
        let dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 15);
        const fixedPayment = amount * (selectedPlan.fixed_percentage / 100);

        // 5 Pagos de solo intereses
        for (let i = 1; i <= 5; i++) {
            payments.push({
                label: `Pago ${i}`,
                value: fixedPayment,
                date: dueDate.toISOString().split('T')[0]
            });
            dueDate.setDate(dueDate.getDate() + 15);
        }

        // Pago 6 (Final: Interés + Capital)
        // BUG CORREGIDO: Ahora la fecha es correcta (+15 días después del pago 5)
        const finalPayment = amount + fixedPayment;
        payments.push({
            label: `Pago Final (Interés + Capital)`,
            value: finalPayment,
            date: dueDate.toISOString().split('T')[0]
        });

    } 
    // 3. Lógica para PLAN PLATA (Antiguo Premium + Abierta) -> 'equal_installments'
    else if (selectedPlan.calculation_type === 'equal_installments' && selectedPlan.fixed_percentage) {
        let dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 15);
        
        const fixedPayment = amount * (selectedPlan.fixed_percentage / 100);
        const totalProfit = fixedPayment * 6;
        const totalToPay = amount + totalProfit;
        const installment = totalToPay / 6;

        for (let i = 1; i <= 6; i++) {
            payments.push({
                label: `Pago ${i} de 6`,
                value: installment,
                date: dueDate.toISOString().split('T')[0]
            });
            dueDate.setDate(dueDate.getDate() + 15);
        }
    }

    return payments.map(p => ({
        ...p,
        formattedValue: new Intl.NumberFormat('es-CO', {
            style: 'currency', currency: 'COP', minimumFractionDigits: 0
        }).format(p.value)
    }));
});

// --- FUNCIÓN DE ENVÍO ---
const handleSubmit = () => {
    emit('submit', form, () => {
        currentStep.value = 1;
    });
};

const nextStep = () => {
    if (form.payment_method === 'balance') {
        handleSubmit();
    } else {
        currentStep.value = 2;
    }
};

const increaseAmount = () => {
    const min = 200000;
    const max = 10000000;

    let currentValue = parseFloat(form.amount) || 0;
    let step;

    if (currentValue < 2000000) {
        step = 100000; 
    } else {
        step = 1000000; 
    }

    if (currentValue < min) {
        currentValue = min;
    } else if (currentValue < max) {
        currentValue += step;
    }

    if (currentValue > max) {
        currentValue = max;
    }

    form.amount = String(currentValue);
};

const decreaseAmount = () => {
    const min = 200000;

    let currentValue = parseFloat(form.amount);
    let step;

    if (currentValue <= 2000000) {
        step = 100000; 
    } else {
        step = 1000000; 
    }

    if (currentValue > min) {
        currentValue -= step;
    }

    if (currentValue < min) {
        currentValue = min;
    }

    form.amount = String(currentValue);
};

// --- LÓGICA DE ARCHIVOS ---
const handleFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) return;

    if (!file.type.startsWith('image/')) {
        form.setError('receipt', 'El archivo debe ser una imagen (JPG, PNG, etc.).');
        form.receipt = null;
        target.value = ''; 
        return;
    }

    const maxSize = 5 * 1024 * 1024; 
    if (file.size > maxSize) {
        form.setError('receipt', 'La imagen es muy pesada (máx 5MB). Por favor, usa una más pequeña o comprímela.');
        form.receipt = null;
        target.value = '';
        return;
    }

    form.clearErrors('receipt');
    form.receipt = file;
};

const receiptPreview = computed(() => {
    if (!form.receipt) return null;
    return URL.createObjectURL(form.receipt);
});

</script>

<template>
    <form @submit.prevent="handleSubmit" class="flex flex-col gap-8">
        <div v-if="currentStep === 1" class="grid gap-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="plan in plans" :key="plan.id" @click="form.plan_id = plan.id"
                    class="rounded-xl border bg-card text-card-foreground shadow transition-all duration-200 cursor-pointer"
                    :class="{ 'ring-2 ring-primary border-primary': form.plan_id === plan.id }">
                    <img :src="plan.image_url ?? 'https://placehold.co/600x400/gray/white?text=Sin+Imagen'"
                        alt="Imagen del Plan" class="aspect-video w-full rounded-t-xl object-cover">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold tracking-tight">{{ plan.name }}</h3>
                        <p class="mt-2 text-sm text-muted-foreground">{{ plan.description }}</p>
                    </div>
                </div>
            </div>

            <div v-if="form.plan_id" class="grid gap-6 animate-in fade-in-50">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t"></span>
                    </div>
                    <div class="relative flex justify-center text-xs uppercase">
                        <span class="bg-background px-2 text-muted-foreground">Define tu inversión</span>
                    </div>
                </div>
<!-- 
                <div class="relative">
                    <div class="relative flex justify-center text-xs uppercase">
                        <Label class="text-base">¿Qué contrato te conviene ??</Label>
                        
                    </div>
                </div>

                <details class="group border rounded-lg overflow-hidden">
                    <summary class="flex items-center justify-between p-4 cursor-pointer hover:bg-muted">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">🕊️</span>
                            <h4 class="font-semibold">Contrato Abierto</h4>
                        </div>
                        <span class="transition-transform duration-300 group-open:rotate-180">▼</span>
                    </summary>
                    <div class="p-4 border-t text-sm text-muted-foreground space-y-3">
                        <p><strong>Mayor control, liquidez y libertad.</strong></p>
                        <p>Ideal para quienes desean controlar de cerca sus ingresos y mantener flexibilidad a lo largo
                            del
                            tiempo.</p>
                        <div class="p-3 bg-muted/50 rounded-md border">
                            <p><strong>Ejemplo de aplicación:</strong><br>
                                ☕ Doña Rosa, 52 años, vendedora de café. Con esfuerzo salió adelante y hoy invierte en
                                un
                                Contrato Abierto que le da liquidez y ganancias cada 15 días. Así vive más holgada y con
                                nuevas oportunidades para su negocio y su familia.</p>
                        </div>
                    </div>
                </details>

                <details class="group border rounded-lg overflow-hidden">
                    <summary class="flex items-center justify-between p-4 cursor-pointer hover:bg-muted">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">🔒</span>
                            <h4 class="font-semibold">Contrato Cerrado</h4>
                        </div>
                        <span class="transition-transform duration-300 group-open:rotate-180">▼</span>
                    </summary>
                    <div class="p-4 border-t text-sm text-muted-foreground space-y-3">
                        <p><strong>Mayor rendimiento, concentración y a largo plazo.</strong></p>
                        <p>Ideal para quienes buscan maximizar resultados sin necesidad de retiros mensuales.</p>
                        <div class="p-3 bg-muted/50 rounded-md border">
                            <p><strong>Ejemplo de aplicación:</strong><br>
                                📱 Julián, 38 años, emprendedor. Empezó arreglando celulares con lo justo, pero nunca
                                dejó
                                de soñar. Hoy invierte en un contrato cerrado de 3 meses que le da rentabilidad y el
                                impulso
                                para cumplir metas y seguir creciendo.</p>
                        </div>
                    </div>
                </details> -->

         
                <div class="grid gap-2">
                    <Label for="amount" class="text-center text-lg">Inversión</Label>

                    <div class="flex items-center justify-center gap-2">
                        <Button type="button" @click="decreaseAmount" variant="outline" size="icon"
                            class="h-14 w-14 rounded-full [touch-action:manipulation]"
                            :disabled="parseFloat(form.amount) <= 200000">
                            <MinusCircle class="h-8 w-8" />
                        </Button>

                        <Input id="amount" type="text" :value="formattedAmount" required placeholder="Inversión"
                            readonly class="h-16 w-48 text-center font-bold text-2xl" />

                        <Button type="button" @click="increaseAmount" variant="outline" size="icon"
                            class="h-14 w-14 rounded-full [touch-action:manipulation]"
                            :disabled="parseFloat(form.amount) >= 10000000">
                            <PlusCircle class="h-8 w-8" />
                        </Button>
                    </div>

                    <InputError :message="form.errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label class="text-base">Método de Pago</Label>
                    <div class="flex items-center space-x-4 rounded-md border p-2 bg-background">
                        <label
                            class="flex items-center space-x-2 cursor-pointer p-2 rounded-md flex-1 justify-center transition-colors"
                            :class="{ 'bg-muted': form.payment_method === 'transfer' }">
                            <input type="radio" v-model="form.payment_method" value="transfer" class="sr-only" />
                            <span>Transferencia</span>
                        </label>
                        <label
                            class="flex items-center space-x-2 cursor-pointer p-2 rounded-md flex-1 justify-center transition-colors"
                            :class="{ 'bg-muted': form.payment_method === 'balance' }">
                            <input type="radio" v-model="form.payment_method" value="balance" class="sr-only" />
                            <span>Usar Saldo Disponible</span>
                        </label>
                    </div>
                    <p v-if="form.payment_method === 'balance'" class="text-sm text-muted-foreground">
                        Saldo disponible: {{ totalAvailable }}
                    </p>
                    <InputError :message="form.errors.payment_method" />
                </div>

                <div v-if="calculatedPayments.length > 0" class="grid gap-2">
                    <Label class="text-base">Vista Previa de Pagos</Label>
                    <div class="rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="border-b">
                                <tr class="text-muted-foreground">
                                    <th class="p-2 text-left font-medium">Descripción</th>
                                    <th class="p-2 text-right font-medium">Monto</th>
                                    <th class="p-2 text-right font-medium">Fecha Pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(payment, index) in calculatedPayments" :key="index"
                                    class="border-b last:border-none">
                                    <td class="p-2 text-left font-bold">{{ payment.label }}</td>
                                    <td class="p-2 text-right font-mono">{{ payment.formattedValue }}</td>
                                    <td class="p-2 text-right font-mono text-muted-foreground">{{ payment.date }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <Button type="button" @click="nextStep" class="w-full"
                    :disabled="!form.plan_id || !form.amount || form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                    {{ form.payment_method === 'balance' ? 'Finalizar Inversión' : 'Siguiente' }}
                </Button>
            </div>
        </div>
        <div v-else-if="currentStep === 2" class="grid gap-6">
            <div class="grid gap-4">
                <Label class="text-base font-semibold">Elige dónde realizar el pago</Label>

                <div class="grid grid-cols-2 gap-4">
                    <div v-for="method in transferMethods" :key="method.name" @click="selectedTransferMethod = method"
                        class="flex flex-col items-center justify-center p-6 border-2 rounded-xl cursor-pointer transition-all hover:bg-slate-50"
                        :class="{ 'ring-4 ring-primary border-primary bg-primary/5': selectedTransferMethod?.name === method.name }">

                        <img :src="method.logo" :alt="method.name" class="h-24 w-24 object-contain mb-4">

                        <span class="text-lg font-bold text-center">{{ method.name }}</span>
                    </div>
                </div>
            </div>
            <div class="p-4 rounded-lg bg-muted text-center">
                <p class="text-sm text-muted-foreground">Realiza la transferencia al siguiente número de {{
                    selectedTransferMethod?.name }}:</p>
                <p class="text-2xl font-mono font-bold my-2">{{ selectedTransferMethod?.phone }}</p>
            </div>
            <div v-if="form.payment_method === 'transfer'" class="grid gap-4">
                <Label for="receipt">Adjunta tu comprobante de pago</Label>

                <Input id="receipt" type="file" @change="handleFileSelect"
                    class="file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-primary-foreground hover:file:bg-primary/90"
                    accept="image/*" required />

                <div v-if="receiptPreview" class="mt-2 relative w-full h-40 rounded-lg border overflow-hidden bg-muted">
                    <img :src="receiptPreview" class="w-full h-full object-contain" />
                    <p class="absolute bottom-2 right-2 bg-black/50 text-white text-[10px] px-2 py-1 rounded">Vista
                        previa</p>
                </div>

                <InputError :message="form.errors.receipt" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="button" variant="outline" @click="currentStep = 1" class="w-1/3">Atrás</Button>
                <Button type="submit" class="w-2/3" :disabled="form.processing || !form.receipt">
                    <template v-if="form.processing">
                        <LoaderCircle class="h-4 w-4 animate-spin mr-2" />
                        Subiendo Comprobante...
                    </template>
                    <template v-else>
                        Confirmar y Generar
                    </template>
                </Button>
            </div>

        </div>
    </form>
</template>